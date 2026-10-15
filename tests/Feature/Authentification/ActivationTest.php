<?php

use App\Models\App\Journal;
use App\Models\App\User;
use App\Notifications\Authentification\InvitationNotification;
use App\Services\Authentification\LienConnexionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

// § 2.4 et EF-M0-02 : invitation puis activation par définition du mot de passe ;
// EF-M0-03 / ENF-SEC-02 : 12 caractères minimum, mots de passe compromis refusés.
uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->technicien = User::factory()->entra()->create();
    $this->eleveur = User::factory()->create(['email' => 'nouvel.eleveur@exemple.fr', 'password' => null]);
});

function lienActivation(User $user): string
{
    $url = null;
    Notification::assertSentTo($user, InvitationNotification::class, function (InvitationNotification $notification) use (&$url) {
        $url = $notification->url;

        return true;
    });

    return (string) $url;
}

function activer(string $lien, string $motDePasse, ?string $confirmation = null): TestResponse
{
    return test()->post($lien, ['password' => $motDePasse, 'password_confirmation' => $confirmation ?? $motDePasse]);
}

it('envoie une invitation et en garde l\'auteur', function () {
    app(LienConnexionService::class)->inviter($this->eleveur, $this->technicien);

    expect(lienActivation($this->eleveur))->toContain('/activation/')
        ->and(DB::table('app.lien_connexion')->sole()->cree_par)->toBe($this->technicien->id);
});

it('active le compte : mot de passe enregistré, connexion ouverte, activation journalisée', function () {
    hibp();
    app(LienConnexionService::class)->inviter($this->eleveur, $this->technicien);
    $lien = lienActivation($this->eleveur);

    $this->get($lien)->assertOk()->assertSee('Choisissez votre mot de passe');
    activer($lien, 'prairie-fleurie-2026')->assertRedirect('/');

    $this->assertAuthenticatedAs($this->eleveur);
    expect(Hash::check('prairie-fleurie-2026', (string) $this->eleveur->fresh()?->password))->toBeTrue()
        ->and(Journal::query()->where('action', 'activation_compte')->where('user_id', $this->eleveur->id)->exists())->toBeTrue();
});

it('refuse un mot de passe de moins de 12 caractères', function () {
    hibp();
    app(LienConnexionService::class)->inviter($this->eleveur);

    activer(lienActivation($this->eleveur), 'court-2026')->assertSessionHasErrors('password');
    $this->assertGuest();
});

it('refuse un mot de passe présent dans une fuite connue', function () {
    $motDePasse = 'motdepasse-fuite-2026';
    hibp($motDePasse);
    app(LienConnexionService::class)->inviter($this->eleveur);

    activer(lienActivation($this->eleveur), $motDePasse)
        ->assertSessionHasErrors(['password' => 'Ce mot de passe figure dans une fuite de données connue. Choisissez-en un autre.']);
    $this->assertGuest();
});

it('refuse un lien d\'activation déjà utilisé', function () {
    hibp();
    app(LienConnexionService::class)->inviter($this->eleveur);
    $lien = lienActivation($this->eleveur);
    activer($lien, 'prairie-fleurie-2026');
    $this->post(route('logout'));

    $this->get($lien)->assertOk()->assertSee('expiré ou déjà utilisé');
    activer($lien, 'autre-mot-de-passe-2026')->assertSessionHasErrors('password');
    expect(Hash::check('prairie-fleurie-2026', (string) $this->eleveur->fresh()?->password))->toBeTrue();
});

it('refuse un lien d\'activation expiré', function () {
    app(LienConnexionService::class)->inviter($this->eleveur);
    $lien = lienActivation($this->eleveur);

    $this->travel(8)->days();

    $this->get($lien)->assertForbidden();
    activer($lien, 'prairie-fleurie-2026')->assertForbidden();
});
