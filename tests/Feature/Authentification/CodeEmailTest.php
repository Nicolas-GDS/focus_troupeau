<?php

use App\Models\App\Journal;
use App\Models\App\User;
use App\Notifications\Authentification\CodeConnexionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

// EF-M0-03 et ENF-SEC-03 : double authentification par code envoyé par e-mail (optionnelle pour l'éleveur).
uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    Notification::fake();
    $this->eleveur = User::factory()->create([
        'email' => 'eleveur@exemple.fr',
        'password' => 'un-mot-de-passe-solide',
        'deux_facteurs_email' => true,
    ]);
});

function codeEnvoye(User $user): string
{
    $code = null;
    Notification::assertSentTo($user, CodeConnexionNotification::class, function (CodeConnexionNotification $notification) use (&$code) {
        $code = $notification->code;

        return true;
    });

    return (string) $code;
}

function etapeMotDePasse(): TestResponse
{
    return test()->post(route('login.store'), ['email' => 'eleveur@exemple.fr', 'password' => 'un-mot-de-passe-solide']);
}

it('demande un code par e-mail après un mot de passe juste, sans connecter', function () {
    etapeMotDePasse()->assertRedirect(route('connexion.code'));

    $this->assertGuest();
    expect(codeEnvoye($this->eleveur))->toMatch('/^\d{6}$/');
    $this->get(route('connexion.code'))->assertOk()->assertSee('Code reçu par e-mail');
});

it('n\'envoie aucun code si le mot de passe est faux', function () {
    $this->post(route('login.store'), ['email' => 'eleveur@exemple.fr', 'password' => 'mauvais'])->assertSessionHasErrors('email');

    Notification::assertNothingSent();
});

it('connecte avec le bon code et journalise la méthode', function () {
    etapeMotDePasse();

    $this->post(route('connexion.code.verifier'), ['code' => codeEnvoye($this->eleveur)])->assertRedirect('/');

    $this->assertAuthenticatedAs($this->eleveur);
    expect(Journal::query()->where('action', 'connexion')->sole()->detail)->toEqual(['methode' => 'mot_de_passe_code']);
});

it('refuse un code faux', function () {
    etapeMotDePasse();

    $this->post(route('connexion.code.verifier'), ['code' => '000000'])->assertSessionHasErrors('code');
    $this->assertGuest();
});

it('invalide le code après 5 essais faux', function () {
    etapeMotDePasse();
    $code = codeEnvoye($this->eleveur);
    $faux = $code === '111111' ? '222222' : '111111';

    foreach (range(1, 5) as $essai) {
        $this->post(route('connexion.code.verifier'), ['code' => $faux]);
    }

    $this->post(route('connexion.code.verifier'), ['code' => $code])->assertSessionHasErrors('code');
    $this->assertGuest();
});

it('refuse un code expiré', function () {
    etapeMotDePasse();
    $code = codeEnvoye($this->eleveur);

    $this->travel(11)->minutes();

    $this->post(route('connexion.code.verifier'), ['code' => $code])->assertSessionHasErrors('code');
    $this->assertGuest();
});

it('ne permet d\'utiliser un code qu\'une fois', function () {
    etapeMotDePasse();
    $code = codeEnvoye($this->eleveur);
    $this->post(route('connexion.code.verifier'), ['code' => $code])->assertRedirect('/');
    $this->post(route('logout'));

    $this->withSession(['connexion.code' => ['user_id' => $this->eleveur->id, 'remember' => false]])
        ->post(route('connexion.code.verifier'), ['code' => $code])->assertSessionHasErrors('code');
    $this->assertGuest();
});

it('annule l\'ancien code quand un nouveau est envoyé', function () {
    etapeMotDePasse();
    $ancien = codeEnvoye($this->eleveur);
    Notification::fake();

    $this->post(route('connexion.code.renvoyer'));
    $nouveau = codeEnvoye($this->eleveur);

    if ($ancien !== $nouveau) {
        $this->post(route('connexion.code.verifier'), ['code' => $ancien])->assertSessionHasErrors('code');
    }
    $this->post(route('connexion.code.verifier'), ['code' => $nouveau])->assertRedirect('/');
});

it('renvoie vers la connexion si aucune connexion n\'est en attente de code', function () {
    $this->get(route('connexion.code'))->assertRedirect(route('login'));
    $this->post(route('connexion.code.verifier'), ['code' => '123456'])->assertRedirect(route('login'));
});
