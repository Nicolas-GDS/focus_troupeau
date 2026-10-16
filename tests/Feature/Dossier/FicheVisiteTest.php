<?php

use App\Jobs\GenererFichePdf;
use App\Models\Dossier\InterpretationVisite;
use App\Services\Dossier\EauService;
use App\Services\Dossier\FicheVisiteService;
use App\Services\Dossier\InterpretationVisiteService;
use App\Services\Dossier\MesuresService;
use App\Services\Dossier\RedactionVisite;
use App\Services\Dossier\VisiteService;
use App\Services\Interpretation\InterpretationService;
use App\Services\Interpretation\RedactionInterpretation;
use App\Services\Pdf\RenduBrowsershot;
use App\Services\Pdf\RenduPdf;
use Database\Seeders\JeuJ4Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\Support\AnalyseEssai;
use Tests\Support\JeuCloisonnement;

// Fiche de visite figée (EF-M2-07, EF-GEN-08, § 8.4) : HTML et PDF archivés à la publication, T-06 (PDF v1 conservé),
// T-26 (gabarit « fiche de visite » : charte bi-entité, mentions de limites, version de grille, moins de 10 s),
// téléchargement signé et cloisonné (T-40).
uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Notification::fake();
    Storage::fake('local');
    app()->instance(RenduPdf::class, new class implements RenduPdf
    {
        public function pdf(string $html, string $piedDePage): string
        {
            return "%PDF-1.7\n% fiche d'essai\n".md5($html);
        }
    });

    $this->jeu = JeuCloisonnement::creer();
    $this->tech = $this->jeu->technicien24;
    $this->actingAs($this->tech);
    $this->visite = app(VisiteService::class)->creer($this->jeu->elevage24a, $this->tech, '2026-01-15', 'initiale');
    $analyse = AnalyseEssai::creer($this->jeu->elevage24a, ['MS' => 86, 'MAT' => 61, 'CU' => 4.2, 'FE' => 166, 'K' => 18]);
    app(VisiteService::class)->rattacherPrelevements($this->visite, [$analyse->prelevement_id]);
    $lots = app(InterpretationService::class);
    $lot = $lots->creerBrouillon($analyse, $this->tech);
    $lots->enregistrer($lot, new RedactionInterpretation(synthese: 'Foin pauvre.', niveauRetenu: 2, utilisationConseillee: 'Vaches en lactation'));
    $lots->valider($lot->refresh(), $this->tech);
    $eau = app(EauService::class)->creer($this->visite, 'Source', '2026-01-15', null, ['EAU_FER' => ['valeur' => 39]]);
    app(EauService::class)->valider($eau);
    app(MesuresService::class)->ajouter($this->visite, $this->tech, '2026-01-15', 'GLYCEMIE', 42, 'BOV', 'prepa_velage');
    app(MesuresService::class)->validerVisite($this->visite, $this->tech);
});

function publierFiche(string $synthese = 'Vos foins manquent d\'azote et de cuivre.'): InterpretationVisite
{
    $service = app(InterpretationVisiteService::class);
    $iv = $service->creerBrouillon(test()->visite, test()->tech);
    $service->ajouterAction($iv, ['priorite' => 1, 'responsable' => 'eleveur', 'titre' => 'Blocs de sel iodé à volonté', 'jalon' => 'tarissement'], test()->tech);
    $service->enregistrer($iv, new RedactionVisite('Vos fourrages 2025-2026 : ce qu\'ils apportent', $synthese, null, 2), test()->tech);
    $service->valider($iv->refresh(), test()->tech);

    return $service->publier($iv->refresh(), test()->tech);
}

it('fige la fiche HTML à la publication et confie le PDF au job (EF-M2-07)', function () {
    $iv = publierFiche();

    expect($iv->fiche_html)->toBe('fiches/24/'.$this->jeu->elevage24a->id.'/visite-'.$this->visite->id.'-v1.html')
        ->and(Storage::disk('local')->exists($iv->fiche_html))->toBeTrue();
    Queue::assertPushed(GenererFichePdf::class, fn (GenererFichePdf $job) => $job->interpretationVisiteId === $iv->id);

    (new GenererFichePdf($iv->id))->handle(app(FicheVisiteService::class));

    expect($iv->refresh()->fiche_pdf)->toEndWith('-v1.pdf')
        ->and(Storage::disk('local')->get($iv->fiche_pdf))->toStartWith('%PDF');
});

it('T-26 : reprend la structure du prototype, la charte bi-entité, les limites et la version de grille', function () {
    $iv = publierFiche();
    $html = (string) Storage::disk('local')->get((string) $iv->fiche_html);

    expect($html)->toContain('data-gds="24"')
        ->toContain('data:image/jpeg;base64')                  // logo de l'entité émettrice intégré
        ->toContain('Réseau GDS France')
        ->toContain('Vos fourrages 2025-2026 : ce qu&#039;ils apportent')
        ->toContain('L\'essentiel')
        ->toContain('Vos fourrages')
        ->toContain('Matrice de vigilance minérale')
        ->toContain('Vaches en lactation')
        ->toContain('Vos animaux')
        ->toContain('Votre plan d\'action')
        ->toContain('Tarissement')
        ->toContain('Cette fiche ne remplace ni un calcul de ration ni un diagnostic vétérinaire.')
        ->toContain('Éléments non dosés dans les fourrages de la visite :')
        ->toContain('Grille d\'interprétation version 4')
        ->toContain('repère à valider')
        ->not->toContain('J4-47')
        ->not->toContain(JeuJ4Seeder::ELEVAGES['J4-47']['raison_sociale'])
        ->not->toContain('/fonts/');                            // aucune ressource externe : fiche autonome

    expect(app(FicheVisiteService::class)->piedDePage($iv))->toContain('grille v4')->toContain('pageNumber')->toContain('GDS France');
});

it('T-06 : la fiche de la version 1 reste archivée et téléchargeable par le technicien après la version 2', function () {
    $v1 = publierFiche();
    (new GenererFichePdf($v1->id))->handle(app(FicheVisiteService::class));
    $pdfV1 = Storage::disk('local')->get((string) $v1->refresh()->fiche_pdf);

    $service = app(InterpretationVisiteService::class);
    $v2 = $service->nouvelleVersion($v1, $this->tech);
    $service->enregistrer($v2, new RedactionVisite('Titre corrigé', 'Synthèse corrigée.', null, 2), $this->tech);
    $service->valider($v2->refresh(), $this->tech);
    $service->publier($v2->refresh(), $this->tech);
    (new GenererFichePdf($v2->id))->handle(app(FicheVisiteService::class));

    expect($v2->refresh()->fiche_pdf)->not->toBe($v1->fiche_pdf)
        ->and(Storage::disk('local')->get((string) $v1->refresh()->fiche_pdf))->toBe($pdfV1)
        ->and((string) Storage::disk('local')->get((string) $v2->fiche_html))->toContain('Synthèse corrigée.');

    $lienV1 = URL::temporarySignedRoute('visites.fiche.pdf', now()->addMinutes(5), ['visite' => $this->visite->id, 'version' => 1]);
    $this->actingAs($this->tech)->get($lienV1)->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->actingAs($this->jeu->eleveur24a)->get($lienV1)->assertForbidden();
});

it('T-40 : sert la fiche publiée par lien signé, dans le périmètre seulement ; produit le PDF à la demande', function (string $profil, int $statut) {
    publierFiche();
    $lien = URL::temporarySignedRoute('visites.fiche.pdf', now()->addMinutes(5), ['visite' => $this->visite->id, 'version' => 1]);

    $this->actingAs($this->jeu->{$profil})->get($lien)->assertStatus($statut);
})->with([
    ['technicien24', 200], ['eleveur24a', 200], ['veterinaire', 200], ['veterinaireConseil24', 200], ['adminGlobal', 200],
    ['technicien47', 404], ['eleveur47', 404],
]);

it('refuse un lien non signé ou une version non publiée', function () {
    publierFiche();

    $this->actingAs($this->tech)->get(route('visites.fiche.pdf', ['visite' => $this->visite->id, 'version' => 1]))->assertForbidden();
    $this->actingAs($this->tech)->get(URL::temporarySignedRoute('visites.fiche.pdf', now()->addMinutes(5), ['visite' => $this->visite->id, 'version' => 2]))->assertNotFound();
});

it('T-26 : rend la fiche en PDF avec Chromium en moins de 10 s', function () {
    if (! is_file((string) config('fiches.chrome'))) {
        $this->markTestSkipped('Chromium absent de cet environnement (image Docker de l\'application requise).');
    }

    $iv = publierFiche();
    $service = app(FicheVisiteService::class);
    $debut = microtime(true);
    $pdf = (new RenduBrowsershot)->pdf((string) Storage::disk('local')->get((string) $iv->fiche_html), $service->piedDePage($iv));
    $duree = microtime(true) - $debut;

    expect($pdf)->toStartWith('%PDF')
        ->and(strlen($pdf))->toBeGreaterThan(10_000)
        ->and($duree)->toBeLessThan(10.0);
});
