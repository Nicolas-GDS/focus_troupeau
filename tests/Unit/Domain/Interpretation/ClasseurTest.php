<?php

use App\Domain\Interpretation\Classeur;
use App\Domain\Interpretation\ContexteLot;
use App\Domain\Interpretation\Seuil;
use App\Domain\Interpretation\ValeurAnalysee;

// RC-1 — classement d'une valeur par la grille : intervalles, ordre de priorité, départage, censure.

function seuil(int $id, string $classe, int $gravite, ?float $min, ?float $max, array $portee = []): Seuil
{
    return new Seuil(
        id: $id, parametreCode: $portee['parametre'] ?? 'CU', classe: $classe, gravite: $gravite, borneMin: $min, borneMax: $max,
        familleFourrageCode: $portee['famille'] ?? null, typeFourrageCode: $portee['type'] ?? null,
        especeCode: $portee['espece'] ?? null, categorieCode: $portee['categorie'] ?? null,
    );
}

function lotFoin(?string $espece = null, ?string $categorie = null): ContexteLot
{
    return new ContexteLot('FOIN_PP', 'FOIN', $espece, $categorie);
}

it('classe une valeur dans l\'intervalle [borne_min ; borne_max[', function (float $valeur, ?string $classe) {
    $seuils = [seuil(1, 'carence', 2, null, 7), seuil(2, 'insuffisant', 1, 7, 10)];

    expect((new Classeur)->classer(new ValeurAnalysee('CU', $valeur), lotFoin(), $seuils)?->seuil->classe)->toBe($classe);
})->with([
    'sous la borne haute ouverte' => [6.99, 'carence'],
    'borne basse incluse' => [7.0, 'insuffisant'],
    'borne haute exclue' => [10.0, null],
]);

it('applique l\'ordre de priorité de RC-1, du plus précis au plus général', function () {
    $seuils = [
        seuil(1, 'tous', 1, null, null),
        seuil(2, 'tous×catégorie', 1, null, null, ['espece' => 'BOV', 'categorie' => 'VL_TARIE']),
        seuil(3, 'famille', 1, null, null, ['famille' => 'FOIN']),
        seuil(4, 'famille×catégorie', 1, null, null, ['famille' => 'FOIN', 'espece' => 'BOV', 'categorie' => 'VL_TARIE']),
        seuil(5, 'type', 1, null, null, ['famille' => 'FOIN', 'type' => 'FOIN_PP']),
        seuil(6, 'type×espèce', 1, null, null, ['famille' => 'FOIN', 'type' => 'FOIN_PP', 'espece' => 'BOV']),
        seuil(7, 'type×catégorie', 1, null, null, ['famille' => 'FOIN', 'type' => 'FOIN_PP', 'espece' => 'BOV', 'categorie' => 'VL_TARIE']),
    ];
    $classeur = new Classeur;
    $valeur = new ValeurAnalysee('CU', 5);
    $lot = lotFoin('BOV', 'VL_TARIE');

    $ordre = [];
    while ($seuils !== []) {
        $retenu = $classeur->classer($valeur, $lot, $seuils)?->seuil;
        $ordre[] = $retenu?->classe;
        $seuils = array_values(array_filter($seuils, fn (Seuil $s) => $s->id !== $retenu?->id));
    }

    expect($ordre)->toBe(['type×catégorie', 'type×espèce', 'type', 'famille×catégorie', 'famille', 'tous×catégorie', 'tous']);
});

it('fait passer la famille avant « tous fourrages × catégorie », contrairement à ref.f_classer_valeur (décision du 15/10/2026)', function () {
    $seuils = [
        seuil(1, 'tous×catégorie', 2, null, null, ['espece' => 'BOV', 'categorie' => 'VL_TARIE']),
        seuil(2, 'famille', 1, null, null, ['famille' => 'FOIN']),
    ];

    expect((new Classeur)->classer(new ValeurAnalysee('CU', 5), lotFoin('BOV', 'VL_TARIE'), $seuils)?->seuil->classe)->toBe('famille');
});

it('classe « tous fourrages × espèce » entre « tous × catégorie » et « tous »', function () {
    $seuils = [
        seuil(1, 'tous', 3, 30, null),
        seuil(2, 'ovins', 3, 15, null, ['espece' => 'OVI']),
    ];

    expect((new Classeur)->classer(new ValeurAnalysee('CU', 35), lotFoin('OVI', 'BREBIS'), $seuils)?->seuil->classe)->toBe('ovins');
});

it('ignore un seuil propre à une autre espèce, catégorie, famille ou type', function () {
    $seuils = [
        seuil(1, 'ovins', 3, 15, null, ['espece' => 'OVI']),
        seuil(2, 'luzerne', 1, null, null, ['famille' => 'FOIN', 'type' => 'FOIN_LUZ']),
        seuil(3, 'ensilage', 1, null, null, ['famille' => 'ENSIL_MAIS']),
    ];

    expect((new Classeur)->classer(new ValeurAnalysee('CU', 35), lotFoin('BOV', 'VL_TARIE'), $seuils))->toBeNull();
});

it('départage deux seuils de même précision par la gravité la plus forte, puis par l\'identifiant', function () {
    $classeur = new Classeur;
    $valeur = new ValeurAnalysee('CU', 50);

    expect($classeur->classer($valeur, lotFoin(), [seuil(1, 'faible', 1, 20, null), seuil(2, 'fort', 3, 30, null)])?->seuil->classe)->toBe('fort')
        ->and($classeur->classer($valeur, lotFoin(), [seuil(9, 'b', 2, 20, null), seuil(4, 'a', 2, 30, null)])?->seuil->classe)->toBe('a');
});

it('classe une valeur censurée sur sa limite de quantification et conserve la censure', function () {
    $classement = (new Classeur)->classer(new ValeurAnalysee('CU', 2, '<'), lotFoin(), [seuil(1, 'carence', 2, null, 7)]);

    expect($classement?->seuil->classe)->toBe('carence')
        ->and($classement?->valeur->censure)->toBe('<');
});

it('ne produit une alerte qu\'à partir de la gravité 1', function () {
    $classeur = new Classeur;
    $seuils = [seuil(1, 'correct', 0, 10, 30), seuil(2, 'carence', 2, null, 7)];

    expect($classeur->classer(new ValeurAnalysee('CU', 12), lotFoin(), $seuils)?->produitUneAlerte())->toBeFalse()
        ->and($classeur->classer(new ValeurAnalysee('CU', 4), lotFoin(), $seuils)?->produitUneAlerte())->toBeTrue();
});

it('détecte deux seuils qui se chevauchent au même niveau de précision (contrôle de E24)', function () {
    expect(seuil(1, 'a', 1, null, 10)->chevauche(seuil(2, 'b', 1, 5, 20)))->toBeTrue()
        ->and(seuil(1, 'a', 1, null, 10)->chevauche(seuil(2, 'b', 1, 10, 20)))->toBeFalse()
        ->and(seuil(1, 'a', 1, null, 10)->chevauche(seuil(2, 'b', 1, 5, 20, ['espece' => 'OVI'])))->toBeFalse()
        ->and(seuil(1, 'a', 1, 30, null)->chevauche(seuil(2, 'b', 1, null, null)))->toBeTrue();
});
