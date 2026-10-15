<?php

use App\Models\App\Journal;
use App\Models\App\User;
use App\Services\Authentification\GroupesEntra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Contracts\Factory;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

// T-31 — EF-M0-04 : connexion Entra ID ; rôles dérivés des groupes ; refus pour un compte hors groupes.
uses(RefreshDatabase::class);

const GROUPE_TECH_24 = '11111111-1111-1111-1111-111111111111';
const GROUPE_TECH_47 = '22222222-2222-2222-2222-222222222222';
const GROUPE_VETO_24 = '33333333-3333-3333-3333-333333333333';
const GROUPE_ADMIN_GLOBAL = '44444444-4444-4444-4444-444444444444';
const GROUPE_SANS_ROLE = '99999999-9999-9999-9999-999999999999';

beforeEach(function () {
    config([
        'entra.actif' => true,
        'entra.groupes' => [
            GROUPE_TECH_24 => [['role' => 'technicien', 'gds' => 24]],
            GROUPE_TECH_47 => [['role' => 'technicien', 'gds' => 47]],
            GROUPE_VETO_24 => [['role' => 'veterinaire_gds', 'gds' => 24]],
            GROUPE_ADMIN_GLOBAL => [['role' => 'admin_global', 'gds' => '*']],
            GROUPE_SANS_ROLE => [['role' => 'eleveur', 'gds' => 24]],   // rôle non attribuable par Entra
        ],
    ]);
});

/**
 * @param  list<string>  $groupes
 */
function retourEntra(array $groupes, string $oid = 'oid-agent-1', string $email = 'agent@gds24.fr'): TestResponse
{
    $utilisateur = (new SocialiteUser)->setRaw([
        'id' => $oid,
        'mail' => $email,
        'givenName' => 'Camille',
        'surname' => 'Martin',
        'groups' => $groupes,
    ])->map(['id' => $oid, 'email' => $email, 'name' => 'Camille Martin']);

    // Un nouveau faux à chaque appel : Mockery garderait sinon la réponse du premier.
    $pilote = Mockery::mock();
    $pilote->shouldReceive('user')->andReturn($utilisateur);
    Socialite::swap(Mockery::mock(Factory::class, fn ($faux) => $faux->shouldReceive('driver')->andReturn($pilote)));

    return test()->get(route('entra.retour', ['code' => 'x', 'state' => 'y']));
}

it('crée le compte à la première connexion avec le rôle et le GDS des groupes (T-31)', function () {
    retourEntra([GROUPE_TECH_24, 'groupe-sans-rapport'])->assertRedirect('/');

    $agent = User::query()->where('entra_oid', 'oid-agent-1')->sole();
    $this->assertAuthenticatedAs($agent);
    expect($agent->email)->toBe('agent@gds24.fr')
        ->and($agent->nomComplet())->toBe('Camille Martin')
        ->and($agent->password)->toBeNull()
        ->and($agent->getRoleNames()->all())->toBe(['technicien'])
        ->and($agent->gds()->pluck('id')->all())->toBe([24])
        ->and(Journal::query()->where('action', 'connexion')->sole()->detail)->toEqual(['methode' => 'entra'])
        ->and(Journal::query()->where('action', 'attribution_roles')->sole()->detail)
        ->toEqual(['origine' => 'groupes_entra', 'roles' => ['technicien'], 'gds' => [24]]);
});

it('cumule les rôles et les GDS de plusieurs groupes (chargé de mission bi-GDS)', function () {
    retourEntra([GROUPE_TECH_24, GROUPE_TECH_47, GROUPE_VETO_24]);

    $agent = User::query()->where('entra_oid', 'oid-agent-1')->sole();
    expect($agent->getRoleNames()->sort()->values()->all())->toBe(['technicien', 'veterinaire_gds'])
        ->and($agent->gds()->pluck('id')->sort()->values()->all())->toBe([24, 47]);
});

it('rattache le super-administrateur à tous les GDS actifs', function () {
    retourEntra([GROUPE_ADMIN_GLOBAL]);

    $agent = User::query()->where('entra_oid', 'oid-agent-1')->sole();
    expect($agent->hasRole('admin_global'))->toBeTrue()
        ->and($agent->gds()->pluck('id')->sort()->values()->all())->toBe([24, 47]);
});

it('refuse un compte hors groupes, sans le créer, et journalise le refus (T-31)', function () {
    retourEntra(['groupe-sans-rapport'])->assertRedirect(route('login'))->assertSessionHasErrors('email');

    $this->assertGuest();
    expect(User::query()->count())->toBe(0)
        ->and(Journal::query()->where('action', 'connexion_refusee')->sole()->detail)
        ->toEqual(['methode' => 'entra', 'motif' => 'hors_groupes', 'email' => 'agent@gds24.fr']);
});

it('refuse un groupe qui attribuerait un rôle réservé aux comptes propres', function () {
    retourEntra([GROUPE_SANS_ROLE])->assertSessionHasErrors('email');

    $this->assertGuest();
    expect(User::query()->count())->toBe(0);
});

it('refuse un agent sorti de tous les groupes, même s\'il a déjà un compte', function () {
    retourEntra([GROUPE_TECH_24]);
    $this->post(route('logout'));

    retourEntra([])->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('ne réécrit pas les rôles gérés dans l\'application aux connexions suivantes', function () {
    retourEntra([GROUPE_TECH_24]);
    $agent = User::query()->where('entra_oid', 'oid-agent-1')->sole();
    $agent->syncRoles(['technicien', 'admin']);
    $agent->gds()->sync([24, 47]);
    $this->post(route('logout'));

    retourEntra([GROUPE_TECH_24])->assertRedirect('/');

    expect($agent->fresh()?->getRoleNames()->sort()->values()->all())->toBe(['admin', 'technicien'])
        ->and($agent->gds()->pluck('id')->sort()->values()->all())->toBe([24, 47]);
});

it('rattache par l\'e-mail un compte existant créé avant sa première connexion Entra', function () {
    $existant = User::factory()->create(['email' => 'agent@gds24.fr', 'password' => null]);

    retourEntra([GROUPE_TECH_24]);

    expect($existant->fresh()?->entra_oid)->toBe('oid-agent-1')
        ->and(User::query()->count())->toBe(1);
    $this->assertAuthenticatedAs($existant);
});

it('refuse un compte Entra désactivé dans l\'application', function () {
    User::factory()->entra()->inactif()->create(['entra_oid' => 'oid-agent-1', 'email' => 'agent@gds24.fr']);

    retourEntra([GROUPE_TECH_24])->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('n\'expose pas la connexion Entra tant qu\'elle n\'est pas configurée', function () {
    config(['entra.actif' => false]);

    $this->get(route('entra'))->assertNotFound();
    $this->get(route('entra.retour'))->assertNotFound();
    $this->get(route('login'))->assertDontSee('compte GDS (Microsoft)');
});

it('lit la correspondance groupes → rôles depuis ENTRA_GROUPES', function () {
    expect(GroupesEntra::lire('g1:technicien:24, g2:admin_global:*,g1:veterinaire_gds:24,,incomplet'))->toBe([
        'g1' => [['role' => 'technicien', 'gds' => 24], ['role' => 'veterinaire_gds', 'gds' => 24]],
        'g2' => [['role' => 'admin_global', 'gds' => '*']],
    ]);
});
