<?php

use App\Jobs\EnvoyerNotification;
use App\Livewire\Elevages\Fiche;
use App\Models\App\User;
use App\Models\Fourrage\Analyse;
use App\Models\Fourrage\Elevage;
use App\Services\Administration\AnonymisationService;
use App\Services\Dossier\SaisieRefusee;
use App\Services\Pdf\RenduPdf;
use Database\Seeders\JeuJ1Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Support\JeuCloisonnement;

// EF-M9-04 (export rationnement), EF-M9-05 (portabilité RGPD) et anonymisation sur demande (§ 2.4) sur J1 :
// publié seulement pour l'éleveur, archive complète, cloisonnement (T-40), anonymisation irréversible et journalisée.
uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake([EnvoyerNotification::class]);
    Mail::fake();
    app()->instance(RenduPdf::class, new class implements RenduPdf
    {
        public function pdf(string $html, string $piedDePage): string
        {
            return "%PDF-1.7\n".md5($html);
        }
    });
    (new JeuJ1Seeder)->run();
    $this->elevage = Elevage::query()->withoutGlobalScopes()->where('code_pseudo', JeuJ1Seeder::CODE)->sole();
    $this->eleveur = User::query()->where('email', JeuJ1Seeder::ELEVEUR)->sole();
    $this->technicien = User::query()->where('email', JeuJ1Seeder::TECHNICIEN)->sole();
});

it('exporte les valeurs des lots pour le rationnement, publiées seulement pour l\'éleveur (EF-M9-04)', function () {
    $this->actingAs($this->eleveur);
    $csv = $this->get(route('export.rationnement', $this->elevage->id))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->getContent();
    $lignes = array_map(fn ($l) => str_getcsv($l, ';', '"', ''), explode("\n", trim(substr((string) $csv, 3))));

    expect($lignes[0])->toContain('UFL_2018 (UFL/kg MS)', 'PDI (g/kg MS)', 'CU (mg/kg MS)', 'MS (% brut)')
        ->and($lignes)->toHaveCount(4)
        ->and(collect($lignes)->pluck(0)->all())->toContain('Foin 2025', 'Foin 2026', 'Enrubannage 2026')
        ->and($lignes[1][array_search('UFL_2018 (UFL/kg MS)', $lignes[0], true)])->toBe('0,67');

    // une analyse non publiée n'apparaît pas pour l'éleveur, mais pour le technicien
    $analyse = Analyse::query()->withoutGlobalScopes()->whereIn('prelevement_id', DB::table('fourrage.prelevement')->where('elevage_id', $this->elevage->id)->select('id'))->first();
    DB::table('fourrage.interpretation')->where('analyse_id', $analyse->id)->update(['statut' => 'valide']);
    expect(substr_count((string) $this->get(route('export.rationnement', $this->elevage->id))->getContent(), "\n"))->toBe(3);
    $this->actingAs($this->technicien);
    expect(substr_count((string) $this->get(route('export.rationnement', $this->elevage->id))->getContent(), "\n"))->toBe(4)
        ->and(DB::table('app.journal')->where('action', 'export_rationnement')->count())->toBe(3);
});

it('produit l\'archive des données de l\'élevage (EF-M9-05)', function () {
    $this->actingAs($this->eleveur);
    $reponse = $this->get(route('export.donnees', $this->elevage->id))->assertOk()->assertHeader('Content-Type', 'application/zip');
    $zip = new ZipArchive;
    $zip->open($reponse->baseResponse->getFile()->getPathname());
    $noms = array_map(fn ($i) => $zip->getNameIndex($i), range(0, $zip->numFiles - 1));

    expect($noms)->toContain('LISEZ-MOI.txt', 'donnees/identite.csv', 'donnees/analyses.csv', 'donnees/inventaires.csv', 'donnees/lots_stock.csv', 'donnees/consentements.csv', 'fiches/bilan-autonomie.pdf')
        ->and((string) $zip->getFromName('donnees/lots_stock.csv'))->toContain('Foin 2025')
        ->and((string) $zip->getFromName('LISEZ-MOI.txt'))->toContain('Seuls les résultats et interprétations publiés')
        ->and(DB::table('app.journal')->where('action', 'export_donnees')->where('user_id', $this->eleveur->id)->exists())->toBeTrue();
});

it('cloisonne les exports (T-40)', function () {
    $jeu = JeuCloisonnement::creer();
    $this->actingAs($jeu->eleveur24a);
    $this->get(route('export.rationnement', $this->elevage->id))->assertNotFound();
    $this->get(route('export.donnees', $this->elevage->id))->assertNotFound();
    $this->actingAs($jeu->technicien47);
    $this->get(route('export.donnees', $this->elevage->id))->assertNotFound();
    $this->actingAs($jeu->veterinaire);
    $this->get(route('export.rationnement', $jeu->elevage24a->id))->assertForbidden();
});

it('anonymise l\'élevage sur demande : identité effacée, comptes anonymisés, convention résiliée', function () {
    DB::table('prive.elevage_identite')->insert(['elevage_id' => $this->elevage->id, 'raison_sociale' => 'EARL J1', 'num_cheptel_ede' => 'FR24999999', 'courriel' => 'j1@exemple.test', 'consentement_donnees' => true]);
    $jeu = JeuCloisonnement::creer();
    $this->actingAs($jeu->admin24);
    expect(fn () => app(AnonymisationService::class)->anonymiser($this->elevage, 'Demande', $this->technicien))->toThrow(SaisieRefusee::class);

    Livewire::test(Fiche::class, ['elevage' => $this->elevage])
        ->call('anonymiser')->assertHasErrors('anonymisation')
        ->set('motifAnonymisation', 'Courrier du 15/10/2026')->call('anonymiser')->assertHasNoErrors();

    $identite = DB::table('prive.elevage_identite')->where('elevage_id', $this->elevage->id)->first();
    $eleveur = $this->eleveur->refresh();
    expect([$identite->raison_sociale, $identite->num_cheptel_ede, $identite->courriel])->toBe([JeuJ1Seeder::CODE, null, null])
        ->and([$eleveur->actif, $eleveur->nom, str_ends_with($eleveur->email, '@anonymise.invalid')])->toBe([false, 'Compte anonymisé', true])
        ->and(DB::table('app.elevage_acces')->where('elevage_id', $this->elevage->id)->where('role', 'eleveur')->exists())->toBeFalse()
        ->and(DB::table('app.convention')->where('elevage_id', $this->elevage->id)->where('statut', '!=', 'resiliee')->exists())->toBeFalse()
        ->and(DB::table('stock.lot_stock')->where('elevage_id', $this->elevage->id)->exists())->toBeTrue()
        ->and(DB::table('app.journal')->where('action', 'anonymisation_elevage')->where('elevage_id', $this->elevage->id)->exists())->toBeTrue();
});
