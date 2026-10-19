<?php

use App\Livewire\Admin\Utilisateurs;
use App\Models\App\User;
use App\Notifications\Authentification\InvitationNotification;
use App\Services\Administration\ComptesService;
use App\Services\Dossier\SaisieRefusee;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Support\JeuCloisonnement;

// E23 — comptes (EF-M10-01, § 2.2 à § 2.4) : création avec invitation, rôles et GDS dans le périmètre de
// l'administrateur, rattachement aux élevages, désactivation, journal (ENF-SEC-08).
uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->jeu = JeuCloisonnement::creer();
    $this->service = app(ComptesService::class);
});

it('crée un compte éleveur invité par e-mail et le rattache à un élevage du GDS', function () {
    $this->actingAs($this->jeu->admin24);
    Livewire::test(Utilisateurs::class)
        ->set('nouveau', ['email' => 'Nouvel.Eleveur@exemple.test', 'nom' => 'Dupont', 'prenom' => 'Anne', 'telephone' => '', 'roles' => ['eleveur'], 'gds' => []])
        ->call('creer')->assertHasNoErrors()->assertSee('Compte créé ; invitation envoyée.')
        ->set('elevageARattacher', 'J4-24B')->call('rattacher')->assertHasNoErrors()->assertSee('J4-24B (éleveur)')
        ->set('elevageARattacher', 'J4-47')->call('rattacher')->assertHasErrors('rattachement');

    $user = User::query()->where('email', 'nouvel.eleveur@exemple.test')->sole();
    Notification::assertSentTo($user, InvitationNotification::class);
    expect($user->hasRole('eleveur'))->toBeTrue()
        ->and(DB::table('app.elevage_acces')->where('user_id', $user->id)->where('elevage_id', $this->jeu->elevage24b->id)->exists())->toBeTrue()
        ->and(DB::table('app.journal')->where('action', 'creation_compte')->where('objet', 'app.user:'.$user->id)->where('user_id', $this->jeu->admin24->id)->exists())->toBeTrue();
});

it('limite l\'administrateur à ses GDS et à ses rôles ; le super-administrateur attribue tout', function () {
    $admin = $this->jeu->admin24;

    expect(fn () => $this->service->creer(['email' => 'a@x.test', 'nom' => 'A', 'roles' => ['technicien'], 'gds' => [47]], $admin))->toThrow(SaisieRefusee::class)
        ->and(fn () => $this->service->creer(['email' => 'b@x.test', 'nom' => 'B', 'roles' => ['admin_global'], 'gds' => []], $admin))->toThrow(SaisieRefusee::class)
        ->and(fn () => $this->service->creer(['email' => 'c@x.test', 'nom' => 'C', 'roles' => ['technicien'], 'gds' => []], $admin))->toThrow(SaisieRefusee::class)
        ->and(fn () => $this->service->creer(['email' => $this->jeu->technicien24->email, 'nom' => 'D', 'roles' => ['eleveur'], 'gds' => []], $admin))->toThrow(SaisieRefusee::class)
        ->and($this->service->visibles($admin)->pluck('id'))->not->toContain($this->jeu->technicien47->id)->toContain($this->jeu->technicien24->id)
        ->and(fn () => $this->service->modifier($this->jeu->technicien47, ['nom' => 'X', 'roles' => ['technicien'], 'gds' => [24]], $admin))->toThrow(SaisieRefusee::class);

    $tech = $this->service->creer(['email' => 'tech.47@x.test', 'nom' => 'Tech', 'roles' => ['technicien'], 'gds' => [47]], $this->jeu->adminGlobal, false);
    expect($tech->hasRole('technicien'))->toBeTrue()->and(DB::table('app.user_gds')->where('user_id', $tech->id)->pluck('gds_id')->all())->toBe([47]);
});

it('conserve les rattachements hors de portée, journalise les rôles et refuse de se retirer l\'administration', function () {
    $charge = $this->jeu->chargeDeMission; // technicien + admin, GDS 24 et 47
    $this->service->modifier($charge, ['nom' => $charge->nom, 'roles' => ['technicien', 'admin', 'veterinaire_gds'], 'gds' => [24]], $this->jeu->admin24);

    expect(DB::table('app.user_gds')->where('user_id', $charge->id)->orderBy('gds_id')->pluck('gds_id')->all())->toBe([24, 47])
        ->and($charge->refresh()->hasRole('veterinaire_gds'))->toBeTrue()
        ->and(DB::table('app.journal')->where('action', 'attribution_roles')->where('objet', 'app.user:'.$charge->id)->exists())->toBeTrue()
        ->and(fn () => $this->service->modifier($this->jeu->admin24, ['nom' => 'A', 'roles' => ['technicien'], 'gds' => [24]], $this->jeu->admin24))->toThrow(SaisieRefusee::class)
        ->and(fn () => $this->service->changerActivation($this->jeu->admin24, false, $this->jeu->admin24))->toThrow(SaisieRefusee::class);
});

it('désactive puis réactive un compte et renvoie l\'invitation', function () {
    $this->actingAs($this->jeu->admin24);
    $eleveur = $this->jeu->eleveur24a;

    Livewire::test(Utilisateurs::class, ['compte' => $eleveur->id])
        ->assertSee($eleveur->email)->call('activer', false)->assertSee('Compte désactivé.')->assertSee('Réactiver')
        ->call('activer', true)->assertSee('Compte réactivé.')
        ->call('inviter')->assertSee('Invitation envoyée.');

    expect($eleveur->refresh()->actif)->toBeTrue()
        ->and(DB::table('app.journal')->whereIn('action', ['desactivation_compte', 'reactivation_compte', 'invitation_compte'])->count())->toBe(3);
    Notification::assertSentTo($eleveur, InvitationNotification::class);
});

it('réserve l\'écran aux administrateurs (T-40)', function () {
    $this->actingAs($this->jeu->technicien24);
    $this->get(route('admin.utilisateurs'))->assertForbidden();
    $this->actingAs($this->jeu->admin24);
    $this->get(route('admin.utilisateurs'))->assertOk()->assertSee('Nouveau compte');
    expect(fn () => Livewire::test(Utilisateurs::class)->call('choisir', $this->jeu->technicien47->id))->toThrow(ModelNotFoundException::class);
});
