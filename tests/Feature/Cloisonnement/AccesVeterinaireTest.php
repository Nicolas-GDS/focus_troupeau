<?php

use App\Models\App\Consentement;
use App\Models\App\User;
use App\Notifications\Authentification\LienConnexionNotification;
use App\Services\Cloisonnement\AccesCompte;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\Support\JeuCloisonnement;

// EF-M0-05 : le compte du vétérinaire d'élevage est actif lorsque au moins un consentement actif le désigne.
uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    $this->jeu = JeuCloisonnement::creer();
    $this->jeu->veterinaire->update(['email' => 'veto@cabinet.fr', 'password' => 'un-mot-de-passe-solide']);
});

it('connecte le vétérinaire désigné par un consentement actif', function () {
    $this->post(route('login.store'), ['email' => 'veto@cabinet.fr', 'password' => 'un-mot-de-passe-solide'])
        ->assertRedirect('/');

    $this->assertAuthenticatedAs($this->jeu->veterinaire);
});

it('refuse le vétérinaire dont tous les consentements sont révoqués', function () {
    $this->jeu->consentementVeterinaire->update(['revoque_le' => now()]);

    $this->post(route('login.store'), ['email' => 'veto@cabinet.fr', 'password' => 'un-mot-de-passe-solide'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('n\'envoie pas de lien de connexion à un vétérinaire sans consentement actif', function () {
    Notification::fake();
    $this->jeu->consentementVeterinaire->update(['revoque_le' => now()]);

    $this->post(route('connexion.lien.demander'), ['email' => 'veto@cabinet.fr']);

    Notification::assertNotSentTo($this->jeu->veterinaire, LienConnexionNotification::class);
});

it('rouvre l\'accès dès qu\'un nouveau consentement le désigne', function () {
    $acces = app(AccesCompte::class);
    $this->jeu->consentementVeterinaire->update(['revoque_le' => now()]);
    expect($acces->peutSeConnecter($this->jeu->veterinaire))->toBeFalse();

    Consentement::factory()->veterinaire($this->jeu->veterinaire)->create(['elevage_id' => $this->jeu->elevage24b->id]);

    expect($acces->peutSeConnecter($this->jeu->veterinaire))->toBeTrue();
});

it('n\'applique pas cette condition aux éleveurs ni aux agents', function () {
    $acces = app(AccesCompte::class);

    expect($acces->peutSeConnecter($this->jeu->eleveur24a))->toBeTrue()
        ->and($acces->peutSeConnecter($this->jeu->technicien24))->toBeTrue()
        ->and($acces->peutSeConnecter(User::factory()->inactif()->create()))->toBeFalse();
});
