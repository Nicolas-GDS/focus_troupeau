<?php

use App\Domain\Interpretation\ContexteLot;
use App\Domain\Interpretation\NiveauGlobal;

// RC-1.4 — niveau global proposé et rehaussements.

it('propose la gravité maximale des alertes retenues, 0 sans alerte', function () {
    $niveau = new NiveauGlobal;
    $lot = new ContexteLot('FOIN_PP', 'FOIN');

    expect($niveau->proposer([1, 2, 1], ['MAT', 'CU'], [], $lot, 4.2, null)->niveau)->toBe(2)
        ->and($niveau->proposer([], [], [], $lot, null, null)->niveau)->toBe(0);
});

it('rehausse un niveau 1 en 2 si un événement sanitaire compatible est enregistré, avec le motif', function () {
    $propose = (new NiveauGlobal)->proposer([1], ['CU'], ['CU', 'SE'], new ContexteLot('FOIN_PP', 'FOIN'), 8.0, null);

    expect($propose->niveau)->toBe(2)
        ->and($propose->niveauAvantRehaussement)->toBe(1)
        ->and($propose->rehaussements)->toHaveCount(1)
        ->and($propose->rehaussements[0])->toContain('CU');
});

it('ne rehausse pas sans événement compatible avec un paramètre en alerte, ni au-delà d\'un niveau 1', function () {
    $niveau = new NiveauGlobal;
    $lot = new ContexteLot('FOIN_PP', 'FOIN');

    expect($niveau->proposer([1], ['MAT'], ['CU'], $lot, null, null)->niveau)->toBe(1)
        ->and($niveau->proposer([2], ['CU'], ['CU'], $lot, null, null)->niveau)->toBe(2);
});

it('passe au niveau 3 un lot destiné à des ovins dont le cuivre atteint le seuil ovin de la grille', function () {
    $niveau = new NiveauGlobal;
    $ovins = new ContexteLot('FOIN_PP', 'FOIN', 'OVI', 'BREBIS');
    $bovins = new ContexteLot('FOIN_PP', 'FOIN', 'BOV', 'VL_TARIE');

    expect($niveau->proposer([], [], [], $ovins, 15.0, 15.0)->niveau)->toBe(3)
        ->and($niveau->proposer([], [], [], $ovins, 14.9, 15.0)->niveau)->toBe(0)
        ->and($niveau->proposer([], [], [], $bovins, 23.0, 15.0)->niveau)->toBe(0)
        ->and($niveau->proposer([], [], [], $ovins, 23.0, null)->niveau)->toBe(0);
});
