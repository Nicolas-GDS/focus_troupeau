<?php

use App\Livewire\Admin\Journal;
use App\Livewire\Compte\Consentements;
use App\Livewire\Elevages\Fiche;
use App\Models\App\User;
use App\Models\Fourrage\Elevage;
use App\Services\Administration\ElevagesService;
use App\Services\Import\ImportSagemaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Support\JeuCloisonnement;

// Recette du lot L7 (§ 9.3 « administration complète ») : parcours d'un élevage importé de SAGEMA jusqu'à l'éleveur
// qui gère ses consentements et exporte ses données, l'administrateur qui en lit la trace dans le journal, et le
// pilotage. Procédure : docs/recette/L7_recette.md.
uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    Mail::fake();
    Queue::fake();
    $this->jeu = JeuCloisonnement::creer();
});

it('enchaîne import SAGEMA, convention, invitation, consentements, export et journal', function () {
    $tech = $this->jeu->technicien24;
    app(ImportSagemaService::class)->elevages((string) file_get_contents(base_path('tests/Fixtures/sagema/elevages.csv')), 24, $tech, true);
    app(ImportSagemaService::class)->indicateurs((string) file_get_contents(base_path('tests/Fixtures/sagema/indicateurs.csv')), $tech, true);
    $elevage = Elevage::query()->withoutGlobalScopes()->findOrFail(DB::table('prive.elevage_identite')->where('num_cheptel_ede', 'FR24000001')->value('elevage_id'));

    $this->actingAs($tech);
    Livewire::test(Fiche::class, ['elevage' => $elevage])
        ->assertSee('EARL du Ruisseau')
        ->set('identite.consentement_donnees', true)->call('enregistrer')->assertHasNoErrors()
        ->call('creerConvention')->assertHasNoErrors()
        ->set('invitation', ['email' => 'paul.lenoir@exemple.test', 'nom' => 'Lenoir', 'prenom' => 'Paul'])->call('inviter')->assertHasNoErrors();

    $eleveur = User::query()->where('email', 'paul.lenoir@exemple.test')->sole();
    $eleveur->forceFill(['notice_rgpd_acceptee_le' => now()])->save(); // première connexion : notice RGPD acceptée
    $this->actingAs($eleveur);
    Livewire::test(Consentements::class)->call('accorderReferences', $elevage->id)->assertHasNoErrors();
    $this->get(route('export.donnees', $elevage->id))->assertOk();
    $this->get(route('dossier.sanitaire', $elevage->id))->assertOk()->assertSee('Non-délivrances');

    $this->actingAs($this->jeu->admin24);
    Livewire::test(Journal::class)->set('elevage', $elevage->code_pseudo)
        ->assertSee('creation convention')->assertSee('invitation compte')->assertSee('accord consentement')->assertSee('export donnees');
    $this->get(route('pilotage'))->assertOk()->assertSee('Couverture des références');

    expect(app(ElevagesService::class)->prochainCode())->toMatch('/^ELV-\d{4}$/');
});
