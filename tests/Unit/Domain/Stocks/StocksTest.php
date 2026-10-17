<?php

use App\Domain\Stocks\Balles;
use App\Domain\Stocks\DensiteHorsDomaine;
use App\Domain\Stocks\EquationDensite;
use App\Domain\Stocks\Silo;

// T-10 (RC-5) et T-11 (RC-7) : mêmes vérifications que calculs/test_calculs.py, à 1e-6 près.

it('T-10 : volume, densités, amidon, tassement et stock utile des silos (RC-5)', function () {
    $silo = new Silo;
    $v = $silo->volume(30, 6, 8, 2.0);
    $d = $silo->densite(EquationDensite::MaisCouloir, 2.0, 33, 30);

    expect($v)->toEqualWithDelta(528.0, 1e-6)                                                      // T-10a
        ->and($d)->toEqualWithDelta(676.5 * 0.33, 1e-6)                                            // T-10b
        ->and($silo->densite(EquationDensite::SorghoTaupiniere, 2.0, 30, 28))
        ->toEqualWithDelta(1.2 * (874 - 14.5 * 30 + 4.2 * 28 + 48 * 2) * 0.30, 1e-6)               // T-10c
        ->and($silo->densite(EquationDensite::HerbeBrinsCourts, 2.5, 30))->toEqualWithDelta(195.0, 1e-6)   // T-10d
        ->and($silo->amidonDepuisGrain(null))->toEqualWithDelta(28.0, 1e-6)                        // T-10f
        ->and($silo->amidonDepuisGrain(45))->toEqualWithDelta(1.134 * 45 - 22.5, 1e-6)             // T-10g
        ->and($silo->correctionTassement(10, 33))->toEqualWithDelta(0.05, 1e-6)                    // T-10h
        ->and($silo->correctionTassement(20, 20))->toEqualWithDelta(0.06, 1e-6)                    // T-10i
        ->and($silo->correctionTassement(25, 30))->toEqualWithDelta(0.0, 1e-6)                     // T-10j
        ->and($silo->stockUtile($v, $d, 0.05))->toEqualWithDelta(528 * 676.5 * 0.33 / 1000 * 0.95, 1e-6);   // T-10k
});

it('T-10e : refuse l\'équation herbe au-delà de 36 % de MS', function () {
    (new Silo)->densite(EquationDensite::HerbeBrinsLongs, 2.0, 40);
})->throws(DensiteHorsDomaine::class);

it('applique les autres équations et le tassement sans fermeture connue', function () {
    $silo = new Silo;

    expect($silo->densite(EquationDensite::HerbeBrinsLongs, 2.0, 30))->toEqualWithDelta(27 * 2 + 1.8 * 30 + 54, 1e-6)
        ->and($silo->densite(EquationDensite::HerbeCouloir, 2.0, 30))->toEqualWithDelta(16 * 2 + 5 * 30 + 34, 1e-6)
        ->and($silo->densite(EquationDensite::HerbeTaupiniere, 2.0, 30))->toEqualWithDelta(7 * 2 + 3.7 * 30 + 74, 1e-6)
        ->and($silo->densite(EquationDensite::SorghoCouloir, 2.0, 33, 30))->toEqualWithDelta(1.2 * 676.5 * 0.33, 1e-6)
        ->and($silo->densite(EquationDensite::HerbeBrinsCourts, 2.0, 36))->toEqualWithDelta(30 * 2 + 2 * 36 + 60, 1e-6)
        ->and($silo->correctionTassement(null, 30))->toBe(0.0)
        ->and($silo->correctionTassement(0, 30))->toEqualWithDelta(0.06, 1e-6)
        ->and($silo->correctionTassement(15, 30))->toEqualWithDelta(0.04, 1e-6);
});

it('T-11 : stock utile des balles, bottes et vrac (RC-7)', function () {
    $balles = new Balles;

    expect($balles->stockUtile(400, 300, 85, 0.03))->toEqualWithDelta(98.94, 1e-6)        // T-11a
        ->and($balles->stockUtile(150, 650, 45, 0.03))->toEqualWithDelta(42.55875, 1e-6)  // T-11b
        ->and($balles->stockUtile(100, 280, 90, 0.03))->toEqualWithDelta(24.444, 1e-6)    // T-11c
        ->and($balles->stockUtile(50, 90, 85, 0.02))->toEqualWithDelta(3.7485, 1e-6)      // T-11d
        ->and($balles->stockBrut(400, 300, 85))->toEqualWithDelta(102.0, 1e-6);
});
