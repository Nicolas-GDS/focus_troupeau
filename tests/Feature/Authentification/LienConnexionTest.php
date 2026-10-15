<?php

use App\Models\App\Journal;
use App\Models\App\User;
use App\Notifications\Authentification\LienConnexionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

// T-30 — EF-M0-03, ENF-SEC-02 : lien de connexion expiré après 15 minutes, inutilisable deux fois.
uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->eleveur = User::factory()->create(['email' => 'eleveur@exemple.fr']);
});

function lienEnvoye(User $user): string
{
    $url = null;
    Notification::assertSentTo($user, LienConnexionNotification::class, function (LienConnexionNotification $notification) use (&$url) {
        $url = $notification->url;

        return true;
    });

    return (string) $url;
}

function demanderLien(string $email): TestResponse
{
    return test()->post(route('connexion.lien.demander'), ['email' => $email]);
}

it('envoie un lien de connexion valable 15 minutes', function () {
    demanderLien('Eleveur@exemple.fr')->assertSessionHas('status');

    Notification::assertSentTo($this->eleveur, LienConnexionNotification::class, fn ($n) => $n->minutes === 15);
});

it('répond de la même façon pour une adresse inconnue, sans rien envoyer', function () {
    $connue = demanderLien('eleveur@exemple.fr')->getSession()?->get('status');
    Notification::fake();

    demanderLien('inconnu@exemple.fr')->assertSessionHas('status', $connue);
    Notification::assertNothingSent();
});

it('n\'envoie pas de lien à un compte Entra ID ni à un compte désactivé', function () {
    $agent = User::factory()->entra()->create(['email' => 'technicien@gds24.fr']);
    $inactif = User::factory()->inactif()->create(['email' => 'ancien@exemple.fr']);

    demanderLien('technicien@gds24.fr');
    demanderLien('ancien@exemple.fr');

    Notification::assertNotSentTo([$agent, $inactif], LienConnexionNotification::class);
});

it('n\'épuise pas le lien à son ouverture : la connexion demande une confirmation', function () {
    demanderLien('eleveur@exemple.fr');
    $lien = lienEnvoye($this->eleveur);

    $this->get($lien)->assertOk()->assertSee('Me connecter');
    $this->get($lien)->assertOk()->assertSee('Me connecter');
    $this->assertGuest();
});

it('connecte l\'éleveur par le lien et journalise la méthode', function () {
    demanderLien('eleveur@exemple.fr');

    $this->post(lienEnvoye($this->eleveur))->assertRedirect('/');

    $this->assertAuthenticatedAs($this->eleveur);
    expect(Journal::query()->where('action', 'connexion')->sole()->detail)->toEqual(['methode' => 'lien']);
});

it('rend le lien inutilisable une seconde fois (T-30)', function () {
    demanderLien('eleveur@exemple.fr');
    $lien = lienEnvoye($this->eleveur);
    $this->post($lien)->assertRedirect('/');
    $this->post(route('logout'));

    $this->post($lien)->assertRedirect(route('connexion.lien'))->assertSessionHasErrors('email');
    $this->assertGuest();
    $this->get($lien)->assertOk()->assertSee('Lien expiré ou déjà utilisé');
});

it('refuse le lien après 15 minutes (T-30)', function () {
    demanderLien('eleveur@exemple.fr');
    $lien = lienEnvoye($this->eleveur);

    $this->travel(16)->minutes();

    $this->get($lien)->assertForbidden();
    $this->post($lien)->assertForbidden();
    $this->assertGuest();
});

it('accepte encore le lien juste avant 15 minutes', function () {
    demanderLien('eleveur@exemple.fr');
    $lien = lienEnvoye($this->eleveur);

    $this->travel(14)->minutes();

    $this->post($lien)->assertRedirect('/');
    $this->assertAuthenticatedAs($this->eleveur);
});

it('refuse un lien dont la signature a été modifiée', function () {
    demanderLien('eleveur@exemple.fr');
    $lien = lienEnvoye($this->eleveur);

    $this->post(preg_replace('/signature=[0-9a-f]+/', 'signature='.str_repeat('0', 64), $lien))->assertForbidden();
    $this->assertGuest();
});

it('ne stocke jamais le jeton en clair', function () {
    demanderLien('eleveur@exemple.fr');
    preg_match('#/connexion/lien/([^?]+)#', lienEnvoye($this->eleveur), $morceaux);

    $ligne = DB::table('app.lien_connexion')->sole();
    expect($ligne->jeton_hash)->toBe(hash('sha256', $morceaux[1]))
        ->and($ligne->jeton_hash)->not->toBe($morceaux[1]);
});

it('rédige l\'e-mail du lien entièrement en français', function () {
    $courriel = (string) (new LienConnexionNotification('http://localhost/connexion/lien/x', 15))
        ->toMail($this->eleveur)->render();

    expect($courriel)->toContain('Me connecter')
        ->toContain('valable 15 minutes')
        ->toContain('Si le bouton')
        ->toContain('adresse ci-dessous')
        ->not->toContain('trouble clicking')
        ->not->toContain('All rights reserved');
});
