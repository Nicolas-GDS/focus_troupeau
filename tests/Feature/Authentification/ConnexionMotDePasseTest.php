<?php

use App\Models\App\Journal;
use App\Models\App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;

// EF-M0-03 (connexion e-mail + mot de passe), ENF-SEC-02 (verrouillage progressif), ENF-SEC-08 (journal).
uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

function connecter(string $email, string $motDePasse): TestResponse
{
    return test()->post(route('login.store'), ['email' => $email, 'password' => $motDePasse]);
}

it('affiche la page de connexion', function () {
    $this->get('/connexion')->assertOk()->assertSee('Adresse e-mail')->assertSee('Recevoir un lien de connexion');
});

it('redirige un visiteur non connecté vers la connexion', function () {
    $this->get('/accueil')->assertRedirect(route('login'));
});

it('connecte un éleveur avec son e-mail et son mot de passe', function () {
    $eleveur = User::factory()->create(['email' => 'eleveur@exemple.fr', 'password' => 'un-mot-de-passe-solide']);

    connecter('Eleveur@Exemple.fr', 'un-mot-de-passe-solide')->assertRedirect('/');

    $this->assertAuthenticatedAs($eleveur);
    expect($eleveur->fresh()?->derniere_connexion)->not->toBeNull()
        ->and(Journal::query()->where('action', 'connexion')->where('user_id', $eleveur->id)->first()?->detail)
        ->toEqual(['methode' => 'mot_de_passe']);
});

it('refuse un mot de passe faux et journalise l\'échec sans le mot de passe', function () {
    User::factory()->create(['email' => 'eleveur@exemple.fr', 'password' => 'un-mot-de-passe-solide']);

    connecter('eleveur@exemple.fr', 'mauvais-mot-de-passe')->assertSessionHasErrors('email');

    $this->assertGuest();
    $echec = Journal::query()->where('action', 'connexion_echec')->sole();
    expect($echec->detail)->toEqual(['methode' => 'mot_de_passe', 'email' => 'eleveur@exemple.fr'])
        ->and(json_encode($echec->detail))->not->toContain('mauvais-mot-de-passe');
});

it('refuse un compte désactivé', function () {
    User::factory()->inactif()->create(['email' => 'ancien@exemple.fr', 'password' => 'un-mot-de-passe-solide']);

    connecter('ancien@exemple.fr', 'un-mot-de-passe-solide')->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('refuse la connexion par mot de passe à un compte Entra ID', function () {
    User::factory()->entra()->create(['email' => 'technicien@gds24.fr']);

    connecter('technicien@gds24.fr', '')->assertSessionHasErrors();
    $this->assertGuest();
});

it('verrouille la connexion après 5 échecs, même avec le bon mot de passe (ENF-SEC-02)', function () {
    User::factory()->create(['email' => 'eleveur@exemple.fr', 'password' => 'un-mot-de-passe-solide']);

    foreach (range(1, 5) as $essai) {
        connecter('eleveur@exemple.fr', 'faux-'.$essai);
    }

    connecter('eleveur@exemple.fr', 'un-mot-de-passe-solide')
        ->assertSessionHasErrors(['email' => 'Trop de tentatives de connexion. Réessayez dans 60 secondes.']);
    $this->assertGuest();
    expect(Journal::query()->where('action', 'verrouillage_connexion')->count())->toBe(1);

    $this->travel(61)->seconds();
    connecter('eleveur@exemple.fr', 'un-mot-de-passe-solide')->assertRedirect('/');
});

it('double la durée du verrouillage à chaque nouvel échec', function () {
    User::factory()->create(['email' => 'eleveur@exemple.fr', 'password' => 'un-mot-de-passe-solide']);

    foreach (range(1, 5) as $essai) {
        connecter('eleveur@exemple.fr', 'faux-'.$essai);
    }
    $this->travel(61)->seconds();
    connecter('eleveur@exemple.fr', 'encore-faux');

    connecter('eleveur@exemple.fr', 'un-mot-de-passe-solide')
        ->assertSessionHasErrors(['email' => 'Trop de tentatives de connexion. Réessayez dans 120 secondes.']);
});

it('remet le compteur d\'échecs à zéro après une connexion réussie', function () {
    User::factory()->create(['email' => 'eleveur@exemple.fr', 'password' => 'un-mot-de-passe-solide']);

    foreach (range(1, 4) as $essai) {
        connecter('eleveur@exemple.fr', 'faux-'.$essai);
    }
    connecter('eleveur@exemple.fr', 'un-mot-de-passe-solide')->assertRedirect('/');
    $this->post(route('logout'));

    connecter('eleveur@exemple.fr', 'faux-5');
    connecter('eleveur@exemple.fr', 'un-mot-de-passe-solide')->assertRedirect('/');
});

it('déconnecte l\'utilisateur et journalise la déconnexion', function () {
    $eleveur = User::factory()->create();

    $this->actingAs($eleveur)->post(route('logout'))->assertRedirect('/');

    $this->assertGuest();
    expect(Journal::query()->where('action', 'deconnexion')->where('user_id', $eleveur->id)->exists())->toBeTrue();
});
