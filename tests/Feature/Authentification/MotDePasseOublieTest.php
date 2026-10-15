<?php

use App\Models\App\User;
use App\Notifications\Authentification\ReinitialisationMotDePasseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

// EF-M0-03, ENF-SEC-02 : réinitialisation du mot de passe (Fortify), routes en français.
uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
});

it('envoie un lien de réinitialisation en français', function () {
    $eleveur = User::factory()->create(['email' => 'eleveur@exemple.fr']);

    $this->get(route('password.request'))->assertOk()->assertSee('Mot de passe oublié');
    $this->post(route('password.email'), ['email' => 'eleveur@exemple.fr'])->assertSessionHas('status');

    Notification::assertSentTo($eleveur, ReinitialisationMotDePasseNotification::class);
});

it('n\'envoie jamais de lien de réinitialisation à un compte Entra ID', function () {
    $agent = User::factory()->entra()->create(['email' => 'technicien@gds24.fr']);

    $this->post(route('password.email'), ['email' => 'technicien@gds24.fr']);

    Notification::assertNotSentTo($agent, ReinitialisationMotDePasseNotification::class);
});

it('réinitialise le mot de passe avec un nouveau mot de passe d\'au moins 12 caractères', function () {
    hibp();
    $eleveur = User::factory()->create(['email' => 'eleveur@exemple.fr']);
    $this->post(route('password.email'), ['email' => 'eleveur@exemple.fr']);

    $jeton = null;
    Notification::assertSentTo($eleveur, ReinitialisationMotDePasseNotification::class, function ($notification) use (&$jeton) {
        $jeton = $notification->token;

        return true;
    });

    $this->get(route('password.reset', ['token' => $jeton, 'email' => 'eleveur@exemple.fr']))->assertOk();

    $this->post(route('password.update'), [
        'token' => $jeton, 'email' => 'eleveur@exemple.fr',
        'password' => 'court', 'password_confirmation' => 'court',
    ])->assertSessionHasErrors('password');

    $this->post(route('password.update'), [
        'token' => $jeton, 'email' => 'eleveur@exemple.fr',
        'password' => 'nouveau-mot-de-passe-2026', 'password_confirmation' => 'nouveau-mot-de-passe-2026',
    ])->assertRedirect(route('login'));

    expect(Hash::check('nouveau-mot-de-passe-2026', (string) $eleveur->fresh()?->password))->toBeTrue();
});
