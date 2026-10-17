<?php

use App\Models\App\Notification as NotificationApp;
use App\Models\App\User;
use App\Models\Dossier\InterpretationVisite;
use App\Models\Fourrage\Alerte;
use App\Models\Fourrage\Analyse;
use App\Services\Dossier\FicheVisiteService;
use App\Services\Dossier\InterpretationVisiteService;
use App\Services\Dossier\RedactionVisite;
use App\Services\Interpretation\InterpretationService;
use App\Services\Interpretation\RedactionInterpretation;
use App\Services\Pdf\RenduBrowsershot;
use App\Services\Pdf\RenduPdf;
use Database\Seeders\JeuJ3Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\RenduChromium;

// Recette du lot L3 sur J3 (§ 9.1, § 9.3 : « première fiche de visite publiée ») : les neuf lots d'ELV-0001 du
// document 04, les constats du prototype retrouvés par la grille, le circuit complet jusqu'à la fiche publiée
// (EF-M2-04 à EF-M2-07), sa lecture par l'éleveur (E02, E03), T-06 et T-26. Procédure manuelle :
// docs/recette/L3_recette_J3.md.
uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Storage::fake('local');
    app()->instance(RenduPdf::class, new class implements RenduPdf
    {
        public function pdf(string $html, string $piedDePage): string
        {
            return '%PDF-1.7 J3';
        }
    });

    (new JeuJ3Seeder)->run();
    $this->elevage = JeuJ3Seeder::elevageJ3();
    $this->visite = JeuJ3Seeder::visiteJ3();
    $this->tech = User::query()->where('email', JeuJ3Seeder::TECHNICIEN)->sole();
    $this->eleveur = User::query()->where('email', JeuJ3Seeder::ELEVEUR)->sole();
    $this->analyses = Analyse::query()->withoutGlobalScopes()->where('num_echantillon_labo', 'like', 'J3-%')->orderBy('num_echantillon_labo')->get();
    $this->actingAs($this->tech);
});

/** Interprète les neuf lots comme sur le prototype (utilisation conseillée de la figure 2, niveau retenu 2) et les valide ; la synthèse par lot, absente du prototype, est un texte de recette. */
function interpreterLotsJ3(): void
{
    $service = app(InterpretationService::class);

    foreach (test()->analyses as $i => $analyse) {
        $lot = $service->creerBrouillon($analyse, test()->tech);
        $service->enregistrer($lot, new RedactionInterpretation(synthese: 'Lecture du lot pour la recette J3 (le prototype ne donne pas de synthèse par lot).',
            utilisationConseillee: JeuJ3Seeder::UTILISATIONS[$i], niveauRetenu: 2));
        $service->valider($lot->refresh(), test()->tech);
    }
}

/** Rédige l'interprétation de visite du prototype (messages de la figure 2, plan de la figure 4), la valide et la publie. */
function publierVisiteJ3(): InterpretationVisite
{
    $service = app(InterpretationVisiteService::class);
    $iv = $service->creerBrouillon(test()->visite, test()->tech);

    foreach (JeuJ3Seeder::ACTIONS as $action) {
        $service->ajouterAction($iv, $action, test()->tech);
    }
    $service->enregistrer($iv, new RedactionVisite('Vos fourrages 2025-2026 : ce qu\'ils apportent, ce qu\'ils ne couvrent pas',
        'Neuf lots analysés : le cuivre et le zinc manquent partout ; les foins de prairie manquent d\'azote, les lots de luzerne le compensent.', null, 2), test()->tech);
    $service->remplacerMessages($iv, array_map(fn (array $m) => [...$m, 'origine' => 'technicien', 'reference_objet' => null], JeuJ3Seeder::MESSAGES));
    $service->valider($iv->refresh(), test()->tech);

    return $service->publier($iv->refresh(), test()->tech);
}

it('charge J3 : neuf bulletins contrôlés, rattachés à une visite en cours de la campagne 2025-2026, rejouable', function () {
    (new JeuJ3Seeder)->run();

    expect($this->analyses)->toHaveCount(9)
        ->and($this->analyses->pluck('statut')->unique()->all())->toBe([Analyse::STATUT_CONTROLE])
        ->and(DB::table('fourrage.prelevement')->where('elevage_id', $this->elevage->id)->count())->toBe(9)
        ->and(DB::table('fourrage.prelevement')->where('visite_id', $this->visite->id)->where('campagne_code', '2025-2026')->count())->toBe(9)
        ->and($this->visite->statut)->toBe('en_cours')
        ->and($this->eleveur->can('view', $this->elevage))->toBeTrue();
});

it('refuse de charger J3 en production', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => (new JeuJ3Seeder)->run())->toThrow(RuntimeException::class);
});

it('retrouve par la grille les constats du prototype (document 04, figure 2)', function () {
    interpreterLotsJ3();
    $alertes = Alerte::query()->withoutGlobalScopes()->where('origine', 'automatique')->get();
    $lots = fn (string $parametre, string $classe) => $alertes->where('parametre_code', $parametre)->where('classe', $classe)->pluck('interpretation_id')->unique()->count();

    // « 9 lots sur 9 sous le seuil de carence en cuivre (7 mg/kg MS) et 8 sous 30 mg/kg MS de zinc »
    expect($lots('CU', 'carence_probable'))->toBe(9)
        ->and($lots('ZN', 'tres_bas'))->toBe(8)
        // « 2/8 lots avec un déficit marqué d'azote soluble (BPR < −30) » ; « traces de terre dans 1 lot (fer > 300) »
        ->and($lots('BPR', 'deficit_azote_soluble'))->toBe(2)
        ->and($lots('FE', 'contamination_possible'))->toBe(1)
        // « aucun lot contaminé par la terre au sens des cendres (MM < 100 g/kg MS) »
        ->and($alertes->where('parametre_code', 'MM')->count())->toBe(0)
        ->and(DB::table('fourrage.interpretation')->pluck('niveau_propose')->unique()->all())->toBe([2]);
});

it('publie la première fiche de visite J3 et la rend lisible par l\'éleveur (T-26, E02, E03)', function () {
    interpreterLotsJ3();
    $iv = publierVisiteJ3();
    $html = (string) Storage::disk('local')->get((string) $iv->fiche_html);

    expect($iv->statut)->toBe(InterpretationVisite::STATUT_PUBLIE)
        ->and($this->visite->refresh()->statut)->toBe('publiee')
        ->and(DB::table('fourrage.interpretation')->where('statut', 'publie')->count())->toBe(9);
    foreach (JeuJ3Seeder::LOTS as $lot) {
        expect($html)->toContain(e($lot['nom']));
    }
    expect($html)->toContain('Associer systématiquement foin de prairie et luzerne')
        ->not->toContain('ELV-0001 (ELV-0001)')->toContain('Sélénium')->toContain('Grille d\'interprétation version')->toContain('ELV-0001')
        ->toContain('Cette fiche ne remplace ni un calcul de ration ni un diagnostic vétérinaire.')
        ->and(NotificationApp::query()->where('user_id', $this->eleveur->id)->where('canal', 'email')->exists())->toBeTrue()
        ->and(NotificationApp::query()->where('user_id', $this->eleveur->id)->where('canal', 'sms')->exists())->toBeFalse();

    $this->actingAs($this->eleveur);
    $this->get(route('accueil'))->assertOk()
        ->assertSee('Vos fourrages 2025-2026')->assertSee('Niveau 2 — alerte')
        ->assertSeeInOrder(['Cuivre et zinc', 'Vos foins de prairie permanente', 'Ce que l&#039;analyse ne dit pas'], false)
        ->assertSee('Associer systématiquement foin de prairie et luzerne')->assertDontSee('Calculer la complémentation minérale');
    $this->get(route('dossier.visite', [$this->elevage->id, $this->visite->id]))->assertOk()
        ->assertSee('Enrubannage de Ray Grass')->assertSee(JeuJ3Seeder::UTILISATIONS[4]);
    $this->get(route('dossier.matrice', $this->elevage->id))->assertOk()->assertSee('Campagne 2025-2026')->assertSee('317');
});

it('T-06 sur J3 : une nouvelle version publiée archive la première, dont le PDF reste téléchargeable', function () {
    interpreterLotsJ3();
    $v1 = publierVisiteJ3();
    app(FicheVisiteService::class)->genererPdf($v1);

    $service = app(InterpretationVisiteService::class);
    $v2 = $service->nouvelleVersion($v1->refresh(), $this->tech);
    $service->enregistrer($v2, new RedactionVisite($v2->titre, 'Synthèse corrigée après la restitution en élevage.', null, 2), $this->tech);
    $service->valider($v2->refresh(), $this->tech);
    $service->publier($v2->refresh(), $this->tech);

    expect($v1->refresh()->statut)->toBe(InterpretationVisite::STATUT_ARCHIVE)
        ->and(Storage::disk('local')->exists((string) $v1->fiche_pdf))->toBeTrue();
    $this->actingAs($this->eleveur)->get(route('dossier.visite', [$this->elevage->id, $this->visite->id]))
        ->assertSee('Synthèse corrigée après la restitution en élevage.')->assertSee('version 2');
});

it('T-26 sur J3 : rend la fiche des neuf lots en PDF avec Chromium en moins de 10 s', function () {
    if (! is_file((string) config('fiches.chrome'))) {
        $this->markTestSkipped('Chromium absent de cet environnement (image Docker de l\'application requise).');
    }

    interpreterLotsJ3();
    $iv = publierVisiteJ3();
    [$pdf, $duree] = RenduChromium::mesurer(fn () => (new RenduBrowsershot)->pdf((string) Storage::disk('local')->get((string) $iv->fiche_html), app(FicheVisiteService::class)->piedDePage($iv)));

    expect($pdf)->toStartWith('%PDF')->and(RenduChromium::dansLeSeuil($duree))->toBeTrue("Rendu en {$duree} s (seuil de T-26 : 10 s)");
});
