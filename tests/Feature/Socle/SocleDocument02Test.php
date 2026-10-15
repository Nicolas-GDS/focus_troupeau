<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

// Chapitre 5.1 et 5.7 : socle du document 02 exécuté par la migration 0001_socle_document02.
uses(RefreshDatabase::class);

function migrationSocle(): object
{
    return require database_path('migrations/0001_socle_document02.php');
}

it('crée les cinq schémas du socle', function () {
    $schemas = collect(DB::select(
        "SELECT nspname FROM pg_namespace WHERE nspname IN ('ref','prive','fourrage','sig','valo')"
    ))->pluck('nspname');

    expect($schemas->sort()->values()->all())->toBe(['fourrage', 'prive', 'ref', 'sig', 'valo']);
});

it('installe les extensions PostGIS, pgcrypto et unaccent', function () {
    $extensions = collect(DB::select('SELECT extname FROM pg_extension'))->pluck('extname');

    expect($extensions)->toContain('postgis', 'pgcrypto', 'unaccent');
});

it('crée les tables, vues et fonctions du socle', function (string $objet) {
    expect(DB::selectOne('SELECT to_regclass(?) IS NOT NULL AS existe', [$objet])->existe)->toBeTrue();
})->with([
    'ref.parametre', 'ref.seuil_interpretation', 'ref.besoin_animal', 'ref.reference_biblio',
    'prive.elevage_identite', 'prive.personne',
    'fourrage.elevage', 'fourrage.prelevement', 'fourrage.analyse', 'fourrage.resultat',
    'fourrage.interpretation', 'fourrage.alerte', 'fourrage.lot_animaux', 'fourrage.ration',
    'sig.commune', 'sig.geol_formation',
    'valo.v_resultat_large', 'valo.mv_prelevement_contexte',
]);

it('charge les nomenclatures du script 01 et la grille du script 02', function () {
    expect(DB::table('ref.espece_animale')->pluck('code')->sort()->values()->all())->toBe(['BOV', 'CAP', 'OVI'])
        ->and(DB::table('ref.parametre')->count())->toBeGreaterThan(0)
        ->and(DB::table('ref.reference_biblio')->count())->toBeGreaterThan(0)
        ->and(DB::table('ref.seuil_interpretation')->count())->toBeGreaterThan(0)
        ->and(DB::table('ref.besoin_animal')->count())->toBeGreaterThan(0);
});

it('est rejouable sans erreur ni doublon', function () {
    $avant = [
        DB::table('ref.espece_animale')->count(),
        DB::table('ref.seuil_interpretation')->count(),
        DB::table('ref.besoin_animal')->count(),
    ];

    migrationSocle()->up();
    migrationSocle()->up();

    expect([
        DB::table('ref.espece_animale')->count(),
        DB::table('ref.seuil_interpretation')->count(),
        DB::table('ref.besoin_animal')->count(),
    ])->toBe($avant);
});

it('ne supprime jamais le socle en production', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => migrationSocle()->down())->toThrow(RuntimeException::class);
    expect(DB::selectOne("SELECT to_regclass('fourrage.elevage') IS NOT NULL AS existe")->existe)->toBeTrue();
});

// CLAUDE.md : les scripts du document 02 sont exécutés tels quels et ne sont jamais modifiés.
// Une évolution du socle passe par une migration d'extension, pas par une retouche de ces fichiers.
it('exécute les scripts du document 02 sans modification', function (string $fichier, string $empreinte) {
    expect(hash_file('sha256', database_path('sql/'.$fichier)))->toBe($empreinte);
})->with([
    ['01_schema_fourrages_gds24.sql', '5b0238902a4f79c321794401ce4b76683064d519d348fecbb5100ec7685e2203'],
    ['02_referentiels_interpretation.sql', 'a15bd3d8ece429e220c1520c5bef035328f9e96f0fc0072d2a6a3517ced9d5fc'],
]);
