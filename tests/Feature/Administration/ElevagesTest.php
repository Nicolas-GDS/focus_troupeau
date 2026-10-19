<?php

use App\Jobs\RappelConventions;
use App\Livewire\Elevages\Fiche;
use App\Livewire\Elevages\Liste;
use App\Models\App\Convention;
use App\Models\App\Notification as NotificationApp;
use App\Models\App\User;
use App\Models\Fourrage\Elevage;
use App\Notifications\Authentification\InvitationNotification;
use App\Services\Administration\ElevagesService;
use App\Services\Dossier\SaisieRefusee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Support\JeuCloisonnement;

// E23 — élevages (EF-M0-02) : création d'un élevage et de son identité, convention, invitation des comptes éleveurs
// sous condition du consentement au traitement des données (§ 2.5), rappel d'échéance (EF-M0-06), cloisonnement.
uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    Mail::fake();
    Queue::fake();
    $this->jeu = JeuCloisonnement::creer();
});

it('crée un élevage avec un code pseudonyme, puis sa convention et le compte de l\'éleveur (EF-M0-02)', function () {
    $this->actingAs($this->jeu->technicien24);
    Livewire::test(Liste::class)
        ->set('nouveau.raison_sociale', 'GAEC des Peupliers')->set('nouveau.num_cheptel_ede', 'fr 24123456')->set('nouveau.code_postal', '24100')
        ->call('creer')->assertHasNoErrors()->assertRedirect();

    $elevage = Elevage::query()->whereIn('id', DB::table('prive.elevage_identite')->where('raison_sociale', 'GAEC des Peupliers')->select('elevage_id'))->sole();
    expect($elevage->code_pseudo)->toMatch('/^ELV-\d{4}$/')->and([$elevage->gds_id, $elevage->departement])->toBe([24, '24'])
        ->and(DB::table('prive.elevage_identite')->where('elevage_id', $elevage->id)->value('num_cheptel_ede'))->toBe('FR24123456');

    $fiche = Livewire::test(Fiche::class, ['elevage' => $elevage])
        ->set('invitation', ['email' => 'gaec@exemple.test', 'nom' => 'Martin', 'prenom' => ''])->call('inviter')->assertHasErrors('invitation')
        ->set('identite.consentement_donnees', true)->call('enregistrer')->assertHasNoErrors()
        ->call('inviter')->assertHasNoErrors()->assertSee('gaec@exemple.test')
        ->call('creerConvention')->assertHasNoErrors()->assertSee('Active');

    $user = User::query()->where('email', 'gaec@exemple.test')->sole();
    Notification::assertSentTo($user, InvitationNotification::class);
    expect(Convention::query()->withoutGlobalScopes()->where('elevage_id', $elevage->id)->where('statut', 'active')->count())->toBe(1)
        ->and(DB::table('app.journal')->whereIn('action', ['creation_elevage', 'invitation_compte', 'creation_convention'])->where('elevage_id', $elevage->id)->count())->toBe(3);

    $fiche->set('prolongation', now()->addYears(2)->toDateString())->call('prolonger', Convention::query()->withoutGlobalScopes()->where('elevage_id', $elevage->id)->sole()->id)->assertHasNoErrors();
});

it('contrôle le numéro EDE et le GDS de création', function () {
    $service = app(ElevagesService::class);
    $base = ['gds_id' => 24, 'raison_sociale' => 'Test'];

    expect(fn () => $service->creer([...$base, 'num_cheptel_ede' => '24123456'], $this->jeu->technicien24))->toThrow(SaisieRefusee::class)
        ->and(fn () => $service->creer([...$base, 'gds_id' => 47], $this->jeu->technicien24))->toThrow(SaisieRefusee::class);
    $service->creer([...$base, 'num_cheptel_ede' => 'FR11111111'], $this->jeu->technicien24);
    expect(fn () => $service->creer([...$base, 'num_cheptel_ede' => 'FR11111111'], $this->jeu->technicien24))->toThrow(SaisieRefusee::class)
        ->and($service->prochainCode())->toMatch('/^ELV-\d{4}$/');
});

it('cloisonne la fiche et la liste (T-40)', function () {
    $this->actingAs($this->jeu->technicien47);
    $this->get(route('elevages.fiche', $this->jeu->elevage24a->id))->assertNotFound();
    $this->get(route('elevages'))->assertOk()->assertSee('J4-47')->assertDontSee('J4-24A');
    $this->actingAs($this->jeu->veterinaireConseil24);
    $this->get(route('elevages'))->assertForbidden();
    $this->actingAs($this->jeu->eleveur24a);
    $this->get(route('elevages.fiche', $this->jeu->elevage24a->id))->assertForbidden();
});

it('rappelle l\'échéance au technicien une seule fois (EF-M0-06)', function () {
    Convention::query()->withoutGlobalScopes()->where('elevage_id', $this->jeu->elevage24a->id)->update(['date_fin' => now()->addDays(20)->toDateString()]);
    Convention::query()->withoutGlobalScopes()->where('elevage_id', $this->jeu->elevage24b->id)->update(['date_fin' => now()->addDays(90)->toDateString()]);

    app()->call([new RappelConventions, 'handle']);
    app()->call([new RappelConventions, 'handle']);

    $rappels = NotificationApp::query()->where('type', RappelConventions::TYPE)->get();
    expect($rappels->pluck('elevage_id')->unique()->values()->all())->toBe([$this->jeu->elevage24a->id])
        ->and($rappels->pluck('user_id')->all())->toContain($this->jeu->technicien24->id)
        ->and($rappels->where('user_id', $this->jeu->technicien24->id))->toHaveCount(1)
        ->and($rappels->first()->objet)->toContain('échéance le '.now()->addDays(20)->format('d/m/Y'));
});
