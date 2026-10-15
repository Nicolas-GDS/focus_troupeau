<?php

use App\Models\App\Consentement;
use App\Models\App\Convention;
use App\Models\App\ElevageAcces;
use App\Models\App\User;
use App\Models\Fourrage\Elevage;
use App\Scopes\ElevageScope;
use App\Scopes\GdsScope;
use App\Services\Cloisonnement\Perimetre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\JeuCloisonnement;

// Chapitre 6.3 : GdsScope et ElevageScope ; EF-M0-01 (aucun élevage d'un GDS non rattaché), § 2.2 (portée des rôles).
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->jeu = JeuCloisonnement::creer();
});

/**
 * @return list<string>
 */
function elevagesVus(User $user): array
{
    test()->actingAs($user);

    return Elevage::query()->orderBy('code_pseudo')->pluck('code_pseudo')->all();
}

it('limite chaque compte aux élevages de sa portée (§ 2.2)', function (string $compte, array $attendus) {
    expect(elevagesVus($this->jeu->{$compte}))->toBe($attendus);
})->with([
    'technicien du GDS 24' => ['technicien24', ['J4-24A', 'J4-24B']],
    'technicien du GDS 47' => ['technicien47', ['J4-47']],
    'chargé de mission bi-GDS' => ['chargeDeMission', ['J4-24A', 'J4-24B', 'J4-47']],
    'vétérinaire conseil du GDS 24' => ['veterinaireConseil24', ['J4-24A', 'J4-24B']],
    'administrateur du GDS 24' => ['admin24', ['J4-24A', 'J4-24B']],
    'super-administrateur' => ['adminGlobal', ['J4-24A', 'J4-24B', 'J4-47']],
    'éleveur de 24A' => ['eleveur24a', ['J4-24A']],
    'éleveur du 47' => ['eleveur47', ['J4-47']],
    'vétérinaire consenti sur 24A' => ['veterinaire', ['J4-24A']],
]);

it('applique le même périmètre aux conventions et aux consentements', function () {
    $this->actingAs($this->jeu->technicien47);
    expect(Convention::query()->pluck('elevage_id')->all())->toBe([$this->jeu->elevage47->id])
        ->and(Consentement::query()->count())->toBe(0);

    $this->actingAs($this->jeu->eleveur24a);
    expect(Convention::query()->pluck('elevage_id')->all())->toBe([$this->jeu->elevage24a->id])
        ->and(Consentement::query()->pluck('id')->all())->toBe([$this->jeu->consentementVeterinaire->id]);
});

it('rend un élevage hors périmètre introuvable, même par son identifiant', function () {
    $this->actingAs($this->jeu->technicien24);

    expect(Elevage::query()->find($this->jeu->elevage47->id))->toBeNull()
        ->and(Convention::query()->where('elevage_id', $this->jeu->elevage47->id)->exists())->toBeFalse();
});

it('retire l\'accès du vétérinaire dès la révocation du consentement', function () {
    expect(elevagesVus($this->jeu->veterinaire))->toBe(['J4-24A']);

    $this->jeu->consentementVeterinaire->update(['revoque_le' => now()]);

    expect(elevagesVus($this->jeu->veterinaire))->toBe([]);
});

it('n\'ouvre rien à un vétérinaire rattaché sans consentement qui le désigne', function () {
    $autreVeterinaire = User::factory()->create();
    $autreVeterinaire->assignRole('veterinaire');
    $autreVeterinaire->elevages()->attach($this->jeu->elevage24b->id, [
        'role' => ElevageAcces::ROLE_VETERINAIRE,
        'consentement_id' => $this->jeu->consentementVeterinaire->id,   // consentement d'un autre vétérinaire
    ]);

    expect(elevagesVus($autreVeterinaire))->toBe([]);
});

it('n\'ouvre rien à un compte sans rôle ni rattachement', function () {
    expect(elevagesVus(User::factory()->create()))->toBe([]);
});

it('limite le rattachement d\'un agent à ses GDS, même s\'il est aussi rattaché à un élevage', function () {
    $this->jeu->technicien24->elevages()->attach($this->jeu->elevage47->id, ['role' => ElevageAcces::ROLE_ELEVEUR]);

    expect(elevagesVus($this->jeu->technicien24))->toBe(['J4-24A', 'J4-24B']);
});

it('ne restreint pas les traitements sans utilisateur en console (tâches planifiées, files)', function () {
    expect(Elevage::query()->count())->toBe(3);
});

it('refuse tout dans une requête web sans utilisateur connecté', function () {
    app()->instance(Perimetre::class, new Perimetre(console: false));

    expect(Elevage::query()->count())->toBe(0)
        ->and(Convention::query()->count())->toBe(0)
        ->and(Consentement::query()->count())->toBe(0);
});

it('se désactive explicitement pour une requête transversale', function () {
    $this->actingAs($this->jeu->technicien24);

    expect(Elevage::query()->withoutGlobalScopes([GdsScope::class, ElevageScope::class])->count())->toBe(3);
});
