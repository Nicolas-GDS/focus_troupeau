<?php

use App\Models\App\Consentement;
use App\Models\App\Convention;
use App\Models\App\ElevageAcces;
use App\Models\App\Gds;
use App\Models\App\Journal;
use App\Models\App\User;
use App\Models\Fourrage\Elevage;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// Chapitre 5.2 : schéma app ; EF-M0-01 (multi-GDS), EF-M0-06 (convention), EF-M0-07 (consentements), EF-M0-08.
uses(RefreshDatabase::class);

it('crée les tables du schéma app', function (string $table) {
    expect(DB::selectOne('SELECT to_regclass(?) IS NOT NULL AS existe', [$table])->existe)->toBeTrue();
})->with([
    'app.gds', 'app.user', 'app.user_gds', 'app.elevage_acces', 'app.convention', 'app.consentement',
    'app.parametre_defaut', 'app.parametre_elevage', 'app.notification', 'app.journal', 'app.failed_jobs',
]);

it('déclare les GDS 24 et 47 avec leur couleur locale', function () {
    expect(Gds::query()->orderBy('id')->get(['id', 'code', 'couleur_locale'])->toArray())->toBe([
        ['id' => 24, 'code' => '24', 'couleur_locale' => '#006BA0'],
        ['id' => 47, 'code' => '47', 'couleur_locale' => '#D4820A'],
    ]);
});

it('rattache les élevages existants à leur GDS par le département (reprise § 5.2)', function () {
    // Les vues de référence du lot L4 dépendent de fourrage.elevage.gds_id : retirées puis remises autour du rejeu.
    $references = require database_path('migrations/2026_10_17_400100_references_positionnement.php');
    $migration = require database_path('migrations/2026_10_15_000100_schema_app.php');
    $references->down();
    $migration->down();

    DB::insert("INSERT INTO fourrage.elevage (code_pseudo, departement) VALUES ('REPRISE-1', '47')");
    $migration->up();
    $references->up();

    expect(DB::table('fourrage.elevage')->where('code_pseudo', 'REPRISE-1')->value('gds_id'))->toBe(47);
});

it('impose un GDS à chaque élevage', function () {
    DB::insert("INSERT INTO fourrage.elevage (code_pseudo, departement) VALUES ('SANS-GDS', '24')");
})->throws(QueryException::class);

it('rend les adresses e-mail uniques sans tenir compte de la casse', function () {
    User::factory()->create(['email' => 'eleveur@exemple.fr']);
    User::factory()->create(['email' => 'Eleveur@Exemple.FR']);
})->throws(QueryException::class);

it('hache le mot de passe et ne le sérialise jamais', function () {
    $user = User::factory()->create(['password' => 'un-mot-de-passe-solide']);

    expect(Hash::check('un-mot-de-passe-solide', (string) $user->password))->toBeTrue()
        ->and($user->toArray())->not->toHaveKeys(['password', 'remember_token']);
});

it('autorise un compte Entra ID sans mot de passe local', function () {
    $user = User::factory()->entra()->create();

    expect($user->password)->toBeNull()->and($user->entra_oid)->not->toBeNull();
});

it('met à jour maj_le à chaque modification', function () {
    $user = User::factory()->create();
    DB::table('app.user')->where('id', $user->id)->update(['maj_le' => now()->subDay()]);

    DB::table('app.user')->where('id', $user->id)->update(['nom' => 'Nouveau nom']);

    expect($user->fresh()?->maj_le->isToday())->toBeTrue();
});

it('relie un technicien à un ou plusieurs GDS', function () {
    $chargeDeMission = User::factory()->entra()->create();
    $chargeDeMission->gds()->attach([24, 47]);

    expect($chargeDeMission->gds()->pluck('id')->sort()->values()->all())->toBe([24, 47])
        ->and(Gds::query()->findOrFail(47)->utilisateurs()->pluck('app.user.id')->all())->toBe([$chargeDeMission->id]);
});

it('rattache un compte éleveur à plusieurs élevages (EF-M0-08)', function () {
    $eleveur = User::factory()->create();
    [$site1, $site2] = Elevage::factory()->count(2)->create();

    $eleveur->elevages()->attach($site1->id, ['role' => ElevageAcces::ROLE_ELEVEUR]);
    $eleveur->elevages()->attach($site2->id, ['role' => ElevageAcces::ROLE_ELEVEUR]);

    $elevages = $eleveur->elevages()->get();
    expect($elevages)->toHaveCount(2)
        ->and($elevages->first()?->acces)->toBeInstanceOf(ElevageAcces::class)
        ->and($elevages->first()?->acces->role)->toBe('eleveur');
});

it('refuse un rôle de rattachement inconnu', function () {
    $user = User::factory()->create();
    $user->elevages()->attach(Elevage::factory()->create()->id, ['role' => 'technicien']);
})->throws(QueryException::class);

it('relie le rattachement d\'un vétérinaire à son consentement', function () {
    $veterinaire = User::factory()->create();
    $consentement = Consentement::factory()->veterinaire($veterinaire)->create();
    $veterinaire->elevages()->attach($consentement->elevage_id, [
        'role' => ElevageAcces::ROLE_VETERINAIRE,
        'consentement_id' => $consentement->id,
    ]);

    expect($veterinaire->elevages()->first()?->acces->consentement?->is($consentement))->toBeTrue();
});

it('exige le vétérinaire désigné pour un consentement « vétérinaire »', function () {
    Consentement::factory()->create(['type' => Consentement::TYPE_VETERINAIRE]);
})->throws(QueryException::class);

it('distingue un consentement actif d\'un consentement révoqué', function () {
    $elevage = Elevage::factory()->create();
    Consentement::factory()->create(['elevage_id' => $elevage->id]);
    Consentement::factory()->revoque()->create(['elevage_id' => $elevage->id]);

    expect($elevage->consentements()->actifs()->count())->toBe(1)
        ->and($elevage->consentements()->whereNotNull('revoque_le')->first()?->estActif())->toBeFalse();
});

it('porte le GDS, les dates et le statut d\'une convention', function () {
    $elevage = Elevage::factory()->gds(47)->create();
    $convention = Convention::factory()->create(['elevage_id' => $elevage->id]);

    expect($convention->gds_id)->toBe(47)
        ->and($convention->estActive())->toBeTrue()
        ->and($convention->date_fin->isFuture())->toBeTrue()
        ->and(Convention::factory()->echue()->create()->estActive())->toBeFalse();
});

it('trace l\'auteur de la création et de la dernière modification d\'une convention (EF-GEN-06)', function () {
    $technicien = User::factory()->entra()->create();
    $collegue = User::factory()->entra()->create();

    $this->actingAs($technicien);
    $convention = Convention::factory()->create();

    $this->actingAs($collegue);
    $convention->update(['date_fin' => now()->addYear()->toDateString()]);

    expect($convention->fresh()?->cree_par)->toBe($technicien->id)
        ->and($convention->fresh()?->maj_par)->toBe($collegue->id)
        ->and($convention->creePar?->is($technicien))->toBeTrue();
});

it('refuse un statut de convention inconnu', function () {
    Convention::factory()->create(['statut' => 'suspendue']);
})->throws(QueryException::class);

it('enregistre une entrée de journal avec son détail', function () {
    $journal = Journal::query()->create([
        'action' => 'connexion',
        'detail' => ['methode' => 'mot_de_passe'],
        'ip' => '192.0.2.10',
    ]);

    expect($journal->fresh()?->detail)->toBe(['methode' => 'mot_de_passe'])
        ->and($journal->fresh()?->horodatage)->not->toBeNull();
});
