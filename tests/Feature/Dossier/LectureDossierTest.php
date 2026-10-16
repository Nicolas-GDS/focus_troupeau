<?php

use App\Livewire\Dossier\Interpretation\PlanActions;
use App\Livewire\Dossier\Lecture\Aliments;
use App\Livewire\Dossier\Lecture\Matrice;
use App\Models\Dossier\Action;
use App\Models\Dossier\EtiquetteAliment;
use App\Models\Dossier\InterpretationVisite;
use App\Services\Dossier\EauService;
use App\Services\Dossier\InterpretationVisiteService;
use App\Services\Dossier\MesuresService;
use App\Services\Dossier\RedactionVisite;
use App\Services\Dossier\VisiteService;
use App\Services\Interpretation\InterpretationService;
use App\Services\Interpretation\RedactionInterpretation;
use App\Services\Pdf\RenduPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\AnalyseEssai;
use Tests\Support\JeuCloisonnement;

// Dossier élevage en lecture (lot L3, étape 7) : accueil E02 (EF-M1-01), synthèse de visite E03 (EF-M1-09), volets
// E04 à E11 (EF-M1-02 à EF-M1-08, EF-M2-06). L'éleveur et le vétérinaire d'élevage ne lisent que le publié, dans la
// dernière version publiée ; les agents lisent aussi le non publié, signalé (§ 6.3). Objets des routes : T-40.
uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Notification::fake();
    Storage::fake('local');
    app()->instance(RenduPdf::class, new class implements RenduPdf
    {
        public function pdf(string $html, string $piedDePage): string
        {
            return '%PDF-1.7';
        }
    });

    $this->jeu = JeuCloisonnement::creer();
    $this->tech = $this->jeu->technicien24;
    $this->eleveur = $this->jeu->eleveur24a;
    $this->elevage = $this->jeu->elevage24a;
    $this->actingAs($this->tech);

    $this->visite = app(VisiteService::class)->creer($this->elevage, $this->tech, '2026-01-15', 'initiale');
    $this->analyse = AnalyseEssai::creer($this->elevage, ['MS' => 86, 'MAT' => 61, 'CU' => 4.2, 'FE' => 166, 'K' => 18]);
    app(VisiteService::class)->rattacherPrelevements($this->visite, [$this->analyse->prelevement_id]);
    $lots = app(InterpretationService::class);
    $lot = $lots->creerBrouillon($this->analyse, $this->tech);
    $lots->enregistrer($lot, new RedactionInterpretation(synthese: 'Foin pauvre en azote.', niveauRetenu: 2, utilisationConseillee: 'Vaches taries'));
    $lots->valider($lot->refresh(), $this->tech);
    $eau = app(EauService::class)->creer($this->visite, 'Forage du bas', '2026-01-15', null, ['EAU_FER' => ['valeur' => 39]]);
    app(EauService::class)->valider($eau);
    app(MesuresService::class)->ajouter($this->visite, $this->tech, '2026-01-15', 'GLYCEMIE', 42, 'BOV', 'prepa_velage');
    app(MesuresService::class)->validerVisite($this->visite, $this->tech);

    $service = app(InterpretationVisiteService::class);
    $iv = $service->creerBrouillon($this->visite, $this->tech);
    $this->action = $service->ajouterAction($iv, ['priorite' => 1, 'responsable' => 'eleveur', 'titre' => 'Blocs de sel iodé à volonté', 'jalon' => 'tarissement'], $this->tech);
    $service->ajouterAction($iv, ['priorite' => 2, 'responsable' => 'technicien', 'titre' => 'Recompter les balles'], $this->tech);
    $service->ajouterControle($this->action, 'GLYCEMIE', 'au-dessus du repère', today()->addMonth()->toDateString(), $this->tech);
    $service->enregistrer($iv, new RedactionVisite('Vos fourrages 2025-2026', 'Vos foins manquent d\'azote et de cuivre.', null, 2), $this->tech);
    $service->remplacerMessages($iv, [['gravite' => 1, 'texte' => 'Surveillez l\'eau du forage.', 'pilier' => 'hydratation', 'origine' => 'technicien', 'reference_objet' => null],
        ['gravite' => 2, 'texte' => 'Complétez le cuivre des vaches taries.', 'pilier' => 'nutrition', 'origine' => 'technicien', 'reference_objet' => null]]);
    $service->valider($iv->refresh(), $this->tech);
    $this->publiee = $service->publier($iv->refresh(), $this->tech);

    // Bulletin hors visite, interprétation en brouillon : invisible de l'éleveur
    $this->brouillon = AnalyseEssai::creer($this->elevage, ['MS' => 30, 'CU' => 9.9]);
    $lots->creerBrouillon($this->brouillon, $this->tech);
});

it('affiche à l\'éleveur l\'accueil de sa dernière visite publiée (EF-M1-01)', function () {
    $this->actingAs($this->eleveur);

    $page = $this->get(route('accueil'))->assertOk();

    $page->assertSee('Vos fourrages 2025-2026')->assertSee('Niveau 2 — alerte')
        ->assertSeeInOrder(['Complétez le cuivre des vaches taries.', 'Surveillez l\'eau du forage.'])
        ->assertSee('Blocs de sel iodé à volonté')->assertDontSee('Recompter les balles')
        ->assertSee('au-dessus du repère')
        ->assertSee(route('dossier.visite', [$this->elevage->id, $this->visite->id]), false)
        ->assertSee('Cette fiche ne remplace ni un calcul de ration ni un diagnostic vétérinaire.')
        ->assertSee('lot L6');
});

it('restitue la visite publiée dans l\'ordre des six étapes, valeurs avec repère et source (E03, EF-M1-09, EF-GEN-02)', function () {
    $this->actingAs($this->eleveur);

    $this->get(route('dossier.visite', [$this->elevage->id, $this->visite->id]))->assertOk()
        ->assertSeeInOrder(['1. Collecter', '2. Analyser', '3. Mesurer', '4. Croiser', '5. Agir', '6. Suivre'])
        ->assertSee('Vos foins manquent d&#039;azote et de cuivre.', false)
        ->assertSee('Vaches taries')->assertSee('Repère :')->assertSee('Source et statut')
        ->assertSee('Forage du bas')->assertSee('Glycémie', false)
        ->assertSee('Blocs de sel iodé à volonté')->assertSee('Télécharger la fiche (PDF, version 1)')
        ->assertSee("Grille d'interprétation version", false);
});

it('ne montre à l\'éleveur que la version publiée pendant qu\'une nouvelle version se prépare (T-06)', function () {
    $service = app(InterpretationVisiteService::class);
    $v2 = $service->nouvelleVersion($this->publiee, $this->tech);
    $service->enregistrer($v2, new RedactionVisite('Brouillon de la version 2', 'Texte non publié.', null, 2), $this->tech);

    $this->actingAs($this->eleveur);
    $this->get(route('dossier.visite', [$this->elevage->id, $this->visite->id]))->assertOk()
        ->assertSee('Vos fourrages 2025-2026')->assertDontSee('Brouillon de la version 2')->assertDontSee('Texte non publié.');
    $this->get(route('accueil'))->assertSee('Vos fourrages 2025-2026')->assertDontSee('Brouillon de la version 2');

    // E11 : même pour le technicien, le plan montré est celui de la version publiée
    $this->actingAs($this->tech);
    Livewire::test(PlanActions::class, ['visiteId' => $this->visite->id, 'publiee' => true])
        ->assertViewHas('interpretation', fn (InterpretationVisite $i) => $i->id === $this->publiee->id);
});

it('refuse à l\'éleveur une visite non publiée et une visite lue sous un autre élevage', function () {
    $brouillon = app(VisiteService::class)->creer($this->elevage, $this->tech, '2026-03-01', 'suivi');
    $autre = app(VisiteService::class)->creer($this->jeu->elevage24b, $this->tech, '2026-03-01', 'suivi');

    $this->actingAs($this->eleveur);
    $this->get(route('dossier.visite', [$this->elevage->id, $brouillon->id]))->assertForbidden();
    $this->get(route('dossier.visite', [$this->elevage->id, $autre->id]))->assertNotFound();
    $this->get(route('visites'))->assertDontSee('03/2026');

    $this->actingAs($this->tech);
    $this->get(route('dossier.visite', [$this->elevage->id, $autre->id]))->assertNotFound();
    $this->get(route('dossier.visite', [$this->elevage->id, $brouillon->id]))->assertOk()->assertSee('pas encore rédigée');
});

it('ne liste à l\'éleveur que les bulletins publiés ; le technicien voit le brouillon, signalé (E04, § 6.3)', function () {
    $this->actingAs($this->eleveur);
    $this->get(route('dossier.fourrages', $this->elevage->id))->assertOk()
        ->assertSee($this->analyse->num_echantillon_labo)->assertDontSee($this->brouillon->num_echantillon_labo);
    $this->get(route('dossier.fourrage', [$this->elevage->id, $this->brouillon->prelevement_id]))->assertNotFound();
    $this->get(route('dossier.fourrage', [$this->elevage->id, $this->analyse->prelevement_id]))->assertOk()
        ->assertSee('Foin pauvre en azote.')->assertSee('Vaches taries')->assertSee('Repère :')->assertSee('Où se situe ce lot dans la campagne ?');

    $this->actingAs($this->tech);
    $this->get(route('dossier.fourrages', $this->elevage->id))->assertOk()
        ->assertSee($this->brouillon->num_echantillon_labo)->assertSee('Brouillon — non publié');
    $this->get(route('dossier.fourrage', [$this->elevage->id, $this->brouillon->prelevement_id]))->assertOk();
});

it('refuse un lot lu sous un autre élevage (404)', function () {
    $autre = AnalyseEssai::creer($this->jeu->elevage24b, ['MS' => 85]);

    $this->get(route('dossier.fourrage', [$this->elevage->id, $autre->prelevement_id]))->assertNotFound();
});

it('montre la matrice minérale, l\'eau, les animaux, le bilan et l\'historique publiés (E05 à E07, E09, E10)', function () {
    $this->actingAs($this->eleveur);

    Livewire::test(Matrice::class, ['elevage' => $this->elevage])
        ->assertSee('Campagne non déterminée')->assertSee('4,2')->assertDontSee('9,9')->assertSee('lot L5');
    $this->get(route('dossier.eau', $this->elevage->id))->assertOk()->assertSee('Forage du bas');
    $this->get(route('dossier.animaux', $this->elevage->id))->assertOk()->assertSee('Glycémie', false)->assertSee('15/01/2026');
    $this->get(route('dossier.sanitaire', $this->elevage->id))->assertOk()->assertSee('Événements sanitaires déclarés');
    $this->get(route('dossier.historique', $this->elevage->id))->assertOk()
        ->assertSee('Visite du 15/01/2026')->assertSee('Blocs de sel iodé à volonté')->assertSee("Ce qui a changé d'une campagne à l'autre", false);
});

it('fait de l\'élevage de l\'adresse l\'élevage courant du sélecteur (EF-M0-08)', function () {
    $this->actingAs($this->tech);

    $this->get(route('dossier.eau', $this->jeu->elevage24b->id))->assertOk();

    expect(session('elevage_courant'))->toBe($this->jeu->elevage24b->id);
});

it('laisse l\'éleveur ajouter une étiquette hors visite, à valider par le technicien (E08, EF-M1-06)', function () {
    $this->actingAs($this->eleveur);

    Livewire::test(Aliments::class, ['elevage' => $this->elevage])
        ->set('nature', 'seau')->set('libelle', 'Seau du commerce')->set('dose', '50')
        ->set('composition', [['code' => 'ZN', 'valeur' => '4000', 'unite' => 'mg/kg']])
        ->call('ajouter')->assertHasNoErrors()
        ->assertSee('Seau du commerce')->assertSee('Saisie');

    $etiquette = EtiquetteAliment::query()->where('libelle_eleveur', 'Seau du commerce')->sole();
    expect($etiquette->visite_id)->toBeNull()
        ->and($etiquette->statut)->toBe(EtiquetteAliment::STATUT_SAISIE)
        ->and($etiquette->cree_par)->toBe($this->eleveur->id);

    // le vétérinaire d'élevage ne lit pas une étiquette non publiée ; il ne peut pas en ajouter
    $this->actingAs($this->jeu->veterinaire);
    Livewire::test(Aliments::class, ['elevage' => $this->elevage])
        ->assertDontSee('Seau du commerce')->assertViewHas('peutAjouter', false)
        ->call('ajouter')->assertForbidden();
});

it('laisse l\'éleveur marquer son action faite depuis le plan publié (E11, EF-M2-06)', function () {
    $this->actingAs($this->eleveur);

    $this->get(route('dossier.plan', $this->elevage->id))->assertOk()
        ->assertSee('Plan de la visite du 15/01/2026')->assertSee("J'ai fait cette action", false);
    Livewire::test(PlanActions::class, ['visiteId' => $this->visite->id, 'publiee' => true])
        ->call('marquerFaite', $this->action->id)->assertHasNoErrors();

    expect($this->action->refresh()->statut)->toBe(Action::STATUT_FAITE);
    $this->get(route('accueil'))->assertDontSee('Blocs de sel iodé à volonté');
});

it('ouvre le dossier publié au vétérinaire d\'élevage consenti (§ 2.5)', function () {
    $this->actingAs($this->jeu->veterinaire);

    $this->get(route('dossier.visite', [$this->elevage->id, $this->visite->id]))->assertOk()->assertSee('Vos fourrages 2025-2026');
    $this->get(route('dossier.fourrages', $this->elevage->id))->assertOk()->assertDontSee($this->brouillon->num_echantillon_labo);

    $this->jeu->consentementVeterinaire->update(['revoque_le' => now()]);
    $this->get(route('dossier.visite', [$this->elevage->id, $this->visite->id]))->assertNotFound();
});
