<?php

use App\Livewire\Dossier\Interpretation\EcranVisite;
use App\Livewire\Dossier\Interpretation\PlanActions;
use App\Models\Dossier\Action;
use App\Models\Dossier\InterpretationVisite;
use App\Services\Dossier\EauService;
use App\Services\Dossier\InterpretationVisiteService;
use App\Services\Dossier\RedactionVisite;
use App\Services\Dossier\VisiteService;
use Database\Seeders\JeuJ4Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Support\JeuCloisonnement;

// E21 — écran d'interprétation de visite et de publication (EF-M2-04 à EF-M2-07) ; ENF-SEC-04 ; T-40 sur la route
// GET /visites/{visite}/interpretation (J4).
uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Notification::fake();
    $this->jeu = JeuCloisonnement::creer();
    $this->visite = app(VisiteService::class)->creer($this->jeu->elevage24a, $this->jeu->technicien24, '2026-01-15', 'initiale');
    $this->actingAs($this->jeu->technicien24);
    $eau = app(EauService::class)->creer($this->visite, 'Source', '2026-01-15', null, ['EAU_FER' => ['valeur' => 250]]);
    app(EauService::class)->valider($eau);
    auth()->logout();
});

function publierVisiteEssai(): InterpretationVisite
{
    $service = app(InterpretationVisiteService::class);
    $tech = test()->jeu->technicien24;
    $iv = $service->creerBrouillon(test()->visite, $tech);
    $service->ajouterAction($iv, ['priorite' => 1, 'responsable' => 'eleveur', 'titre' => 'Nettoyer les abreuvoirs'], $tech);
    $service->enregistrer($iv, new RedactionVisite('L\'eau à surveiller', 'Le fer de la source dépasse le repère.', null, 1), $tech);
    $service->valider($iv->refresh(), $tech);

    return $service->publier($iv->refresh(), $tech);
}

it('T-40 : ouvre l\'écran dans le périmètre ; les comptes externes ne voient que la version publiée', function (string $profil, int $statut) {
    $this->actingAs($this->jeu->{$profil})->get(route('visites.interpretation', $this->visite->id))->assertStatus($statut);
})->with([
    ['technicien24', 200], ['chargeDeMission', 200], ['veterinaireConseil24', 200], ['admin24', 200], ['adminGlobal', 200],
    ['technicien47', 404], ['eleveur47', 404], ['eleveur24a', 403], ['veterinaire', 403],
]);

it('T-40 : ouvre la visite publiée à l\'éleveur et au vétérinaire consenti, sans les éléments internes', function () {
    publierVisiteEssai();
    $url = route('visites.interpretation', $this->visite->id);

    $this->actingAs($this->jeu->eleveur24a)->get($url)->assertOk()
        ->assertSee('Le fer de la source dépasse le repère.')
        ->assertSee('Nettoyer les abreuvoirs')
        ->assertSee('J\'ai fait cette action', false)
        ->assertDontSee('4. Croiser')
        ->assertDontSee('Publier la visite');
    $this->actingAs($this->jeu->veterinaire)->get($url)->assertOk()->assertDontSee('J\'ai fait cette action', false);
    $this->actingAs($this->jeu->eleveur47)->get($url)->assertNotFound();
});

it('T-40 : n\'affiche aucun élevage hors périmètre', function () {
    $this->actingAs($this->jeu->technicien24)->get(route('visites.interpretation', $this->visite->id))->assertOk()
        ->assertSee('J4-24A')
        ->assertDontSee('J4-47')
        ->assertDontSee(JeuJ4Seeder::ELEVAGES['J4-47']['raison_sociale']);
});

it('conduit le circuit depuis l\'écran : brouillon, messages, validation, publication', function () {
    $this->actingAs($this->jeu->technicien24);

    $ecran = Livewire::test(EcranVisite::class, ['visite' => $this->visite])
        ->assertSee('4. Croiser : ce que montre la visite')
        ->call('creer')
        ->assertHasNoErrors()
        ->assertSee('Niveau 1 — vigilance')
        ->set('synthese', 'Le fer de la source dépasse le repère.')
        ->set('niveauRetenu', '1')
        ->call('valider')
        ->assertHasNoErrors();

    $iv = app(InterpretationVisiteService::class)->courante($this->visite);
    expect($iv?->statut)->toBe(InterpretationVisite::STATUT_VALIDE);

    $ecran->call('publier')->assertHasNoErrors();
    expect($iv?->refresh()->statut)->toBe(InterpretationVisite::STATUT_PUBLIE);

    $ecran->call('nouvelleVersion')->assertSee('Version 2');
});

it('refuse au vétérinaire conseil la rédaction et la publication', function () {
    $this->actingAs($this->jeu->technicien24);
    app(InterpretationVisiteService::class)->creerBrouillon($this->visite, $this->jeu->technicien24);

    $this->actingAs($this->jeu->veterinaireConseil24);
    Livewire::test(EcranVisite::class, ['visite' => $this->visite])
        ->assertDontSee('Valider l\'interprétation de visite')
        ->call('enregistrer')->assertForbidden();
    Livewire::test(PlanActions::class, ['visiteId' => $this->visite->id])
        ->set('action.titre', 'Action')->call('ajouterAction')->assertForbidden();
});

it('ajoute une action et son contrôle depuis le plan, puis l\'éleveur la marque faite après publication', function () {
    $this->actingAs($this->jeu->technicien24);
    app(InterpretationVisiteService::class)->creerBrouillon($this->visite, $this->jeu->technicien24);

    $plan = Livewire::test(PlanActions::class, ['visiteId' => $this->visite->id])
        ->set('action.titre', 'Analyse d\'eau complète')->set('action.priorite', '3')->set('action.quand', 'Sous 3 mois')
        ->call('ajouterAction')
        ->assertHasNoErrors()
        ->assertSee('Analyse d\'eau complète');
    $action = Action::query()->sole();
    $plan->set("controles.{$action->id}", ['mesure' => 'Analyse d\'eau', 'cible' => 'Bactériologie, nitrates, sulfates', 'echeance' => '2026-04-15'])
        ->call('ajouterControle', $action->id)
        ->assertHasNoErrors()
        ->assertSee('Bactériologie, nitrates, sulfates');

    $service = app(InterpretationVisiteService::class);
    $iv = $service->courante($this->visite);
    $service->enregistrer($iv, new RedactionVisite(null, 'Synthèse.', null, 1), $this->jeu->technicien24);
    $service->valider($iv->refresh(), $this->jeu->technicien24);
    $service->publier($iv->refresh(), $this->jeu->technicien24);

    $this->actingAs($this->jeu->eleveur24a);
    Livewire::test(PlanActions::class, ['visiteId' => $this->visite->id])
        ->call('marquerFaite', $action->id)
        ->assertHasNoErrors()
        ->assertSee('Faite le');

    expect($action->refresh()->statut)->toBe(Action::STATUT_FAITE);
});
