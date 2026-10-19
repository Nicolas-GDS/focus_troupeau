<?php

use App\Jobs\ClotureConventions;
use App\Livewire\Admin\Conventions;
use App\Livewire\Admin\Gds;
use App\Livewire\Admin\Journal;
use App\Models\App\Convention;
use App\Services\Administration\ConventionsService;
use App\Services\Dossier\SaisieRefusee;
use App\Services\JournalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Support\JeuCloisonnement;

// E23 — conventions (EF-M0-06, § 2.4), GDS (EF-M0-01) et journal d'audit (EF-M10-01, ENF-SEC-08).
uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->jeu = JeuCloisonnement::creer();
    $this->service = app(ConventionsService::class);
    $this->convention = Convention::query()->withoutGlobalScopes()->where('elevage_id', $this->jeu->elevage24a->id)->where('statut', 'active')->sole();
});

it('prolonge, réactive une convention échue et refuse une seconde convention active', function () {
    $tech = $this->jeu->technicien24;

    expect(fn () => $this->service->creer($this->jeu->elevage24a, ['date_debut' => '2027-01-01', 'date_fin' => '2027-12-31'], $tech))->toThrow(SaisieRefusee::class)
        ->and(fn () => $this->service->prolonger($this->convention, $this->convention->date_fin->copy()->subDay()->toDateString(), $tech))->toThrow(SaisieRefusee::class);

    $this->convention->update(['date_fin' => now()->subDays(3)->toDateString()]);
    (new ClotureConventions)->handle(app(JournalService::class));
    expect($this->convention->refresh()->statut)->toBe('echue');

    $this->service->prolonger($this->convention, now()->addYear()->toDateString(), $tech);
    expect($this->convention->refresh()->statut)->toBe('active')
        ->and(DB::table('app.journal')->where('action', 'prolongation_convention')->where('elevage_id', $this->jeu->elevage24a->id)->exists())->toBeTrue();
});

it('résilie une convention depuis l\'écran de l\'administrateur, motif obligatoire', function () {
    $this->actingAs($this->jeu->admin24);
    Livewire::test(Conventions::class)
        ->assertSee('J4-24A')->assertDontSee('J4-47')
        ->call('choisir', $this->convention->id)
        ->call('resilier')->assertHasErrors('convention')
        ->set('motif', 'Cessation d\'activité')->call('resilier')->assertHasNoErrors()->assertSee('Convention résiliée.');

    expect($this->convention->refresh()->statut)->toBe('resiliee')
        ->and($this->convention->commentaire)->toContain('Cessation d\'activité')
        ->and(fn () => $this->service->prolonger($this->convention, now()->addYear()->toDateString(), $this->jeu->admin24))->toThrow(SaisieRefusee::class);
});

it('modifie le contact de son GDS ; seul le super-administrateur crée un GDS', function () {
    $this->actingAs($this->jeu->admin24);
    Livewire::test(Gds::class)->assertSee('GDS 24')->assertDontSee('GDS 47')->assertDontSee('Nouveau GDS')
        ->set('fiches.24.contact_email', 'contact@gds24.test')->call('enregistrer', 24)->assertHasNoErrors();
    expect(DB::table('app.gds')->where('id', 24)->value('contact_email'))->toBe('contact@gds24.test');

    $this->actingAs($this->jeu->adminGlobal);
    Livewire::test(Gds::class)->assertSee('GDS 47')
        ->set('nouveau', ['id' => '33', 'code' => '33', 'nom' => 'GDS Gironde', 'couleur_locale' => '#123456', 'contact_email' => ''])->call('creer')->assertHasNoErrors();
    expect(DB::table('app.gds')->where('id', 33)->value('nom'))->toBe('GDS Gironde');
});

it('montre à l\'administrateur le journal de ses GDS, filtrable, et le refuse au technicien', function () {
    $journal = app(JournalService::class);
    $journal->ecrire('publication_visite', $this->jeu->technicien24->id, 'dossier.visite:1', [], $this->jeu->elevage24a->id);
    $journal->ecrire('publication_visite', $this->jeu->technicien47->id, 'dossier.visite:2', [], $this->jeu->elevage47->id);

    $this->actingAs($this->jeu->admin24);
    Livewire::test(Journal::class)->assertSee('dossier.visite:1')->assertDontSee('dossier.visite:2')
        ->set('elevage', 'J4-24B')->assertDontSee('dossier.visite:1');
    $this->actingAs($this->jeu->adminGlobal);
    Livewire::test(Journal::class)->assertSee('dossier.visite:2');

    $this->actingAs($this->jeu->technicien24);
    $this->get(route('admin.journal'))->assertForbidden();
});
