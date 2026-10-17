<?php

use App\Domain\Troupeau\Besoin;
use App\Domain\Troupeau\Besoins;
use App\Domain\Troupeau\ClasseCouverture;
use App\Domain\Troupeau\Composant;
use App\Domain\Troupeau\Couverture;
use App\Domain\Troupeau\LigneBesoin;
use App\Domain\Troupeau\ResolutionBesoins;
use App\Domain\Troupeau\SeuilsCouverture;

// T-12 (RC-8) et T-13 (RC-9) : mêmes vérifications que calculs/test_calculs.py, à 1e-6 près ; codes de paramètres de
// l'application (UFL_2018 pour « UFL »).

function couvertureEssai(): Couverture
{
    return new Couverture(new SeuilsCouverture(0.80, 1.00, 1.50));
}

/** @return list<Composant> ration des vaches de J1 (T-13) */
function rationJ1(): array
{
    return [
        new Composant('Foin 2025', 4.0, ['UFL_2018' => 0.67, 'PDI' => 60, 'MAT' => 78, 'CA' => 7.3, 'P' => 2.2, 'CU' => 5.1, 'K' => 19.7], Composant::ORIGINE_ANALYSE),
        new Composant('Enrubannage 2026', 7.0, ['UFL_2018' => 0.80, 'PDI' => 70, 'MAT' => 84, 'CA' => 9.6, 'P' => 2.5, 'CU' => 5.4, 'K' => 13.4], Composant::ORIGINE_ANALYSE),
        new Composant('Correcteur azoté', 0.88, ['UFL_2018' => 1.0, 'PDI' => 250, 'MAT' => 280, 'CA' => 10, 'P' => 8, 'CU' => 20, 'K' => 15]),
        new Composant('Minéral (100 g/j)', 0.1, ['CA' => 80, 'P' => 120, 'CU' => 2000], Composant::ORIGINE_ETIQUETTE),
    ];
}

it('T-12 : voie UGB et besoin prélevé sur le stock (RC-8)', function () {
    $besoins = new Besoins;
    $ugb = $besoins->voieUgb([['effectif' => 100, 'coefficient' => 0.90], ['effectif' => 30, 'coefficient' => 0.60], ['effectif' => 2, 'coefficient' => 1.00]], 160, 13.0);

    expect($ugb['ugb'])->toEqualWithDelta(110.0, 1e-6)                                   // T-12a
        ->and($ugb['besoin_t_ms'])->toEqualWithDelta(228.8, 1e-6)                        // T-12b
        ->and($besoins->prelevementStock(200.0, 0.13))->toEqualWithDelta(200 / 0.87, 1e-6)   // T-12c
        ->and($besoins->voieRation(100, 11.0, 151))->toEqualWithDelta(166.1, 1e-6)
        ->and($besoins->ecartVoies(228.8, 200.0))->toEqualWithDelta(28.8 / 228.8, 1e-6)
        ->and($besoins->ecartVoies(0.0, 10.0))->toBeNull();
});

it('T-13 : apports, couverture et classes de la ration des vaches de J1 (RC-9)', function () {
    $c = couvertureEssai();
    $ration = rationJ1();
    ['msi' => $msi, 'apports' => $apports] = $c->apports($ration);
    $ufl = 4 * 0.67 + 7 * 0.80 + 0.88;
    $cv = $c->couverture($ration, [
        'UFL_2018' => Besoin::parJour(9.5),
        'PDI' => Besoin::parJour(800),
        'CU' => Besoin::parKgMs(10),
        'CA' => Besoin::parKgMs(6, 6.5),
    ]);

    expect($msi)->toEqualWithDelta(11.98, 1e-6)                                                     // T-13a
        ->and($apports['UFL_2018'])->toEqualWithDelta($ufl, 1e-6)                                   // T-13b
        ->and($apports['CU'])->toEqualWithDelta(4 * 5.1 + 7 * 5.4 + 0.88 * 20 + 200, 1e-6)           // T-13c
        ->and($cv['UFL_2018']->ratio)->toEqualWithDelta($ufl / 9.5, 1e-6)                           // T-13d
        ->and($cv['UFL_2018']->classe)->toBe(ClasseCouverture::Limite)                              // T-13e
        ->and($cv['UFL_2018']->residuel)->toEqualWithDelta(9.5 - $ufl, 1e-6)                        // T-13f
        ->and($cv['PDI']->classe)->toBe(ClasseCouverture::Couvert)                                  // T-13g
        ->and($cv['CU']->concentration)->toEqualWithDelta($apports['CU'] / $msi, 1e-6)              // T-13h
        ->and($cv['CU']->classe)->toBe(ClasseCouverture::Exces)                                     // T-13i
        ->and($cv['CU']->gravite)->toBe(0)                                                          // T-13i2
        ->and($cv['CA']->ratio)->toEqualWithDelta($apports['CA'] / $msi / 6.5, 1e-6)                // T-13j
        ->and($c->ratioCaP($apports))->toEqualWithDelta($apports['CA'] / $apports['P'], 1e-6);      // T-13m
});

it('T-13k-l : maximum réglementaire du cuivre chez les ovins dépassé (gravité 3)', function () {
    $cv = couvertureEssai()->couverture(
        [new Composant('Foin', 1.5, ['CU' => 5]), new Composant('Seau bovin', 0.1, ['CU' => 300])],
        ['CU' => Besoin::parKgMs(8, null, 15)],
    );

    expect($cv['CU']->classe)->toBe(ClasseCouverture::MaximumDepasse)
        ->and($cv['CU']->gravite)->toBe(3);
});

it('classe déficit, limite, couvert et excès aux bornes paramétrées (RC-9 [CP])', function () {
    $c = couvertureEssai();

    expect($c->classer(0.79, false, false))->toBe(['classe' => ClasseCouverture::Deficit, 'gravite' => 2])
        ->and($c->classer(0.80, false, false))->toBe(['classe' => ClasseCouverture::Limite, 'gravite' => 1])
        ->and($c->classer(1.00, false, false))->toBe(['classe' => ClasseCouverture::Couvert, 'gravite' => 0])
        ->and($c->classer(1.50, false, false))->toBe(['classe' => ClasseCouverture::Couvert, 'gravite' => 0])
        ->and($c->classer(1.51, false, false))->toBe(['classe' => ClasseCouverture::Exces, 'gravite' => 1])
        ->and($c->classer(1.51, false, true))->toBe(['classe' => ClasseCouverture::Exces, 'gravite' => 0])
        ->and((new Couverture(new SeuilsCouverture(0.9, 1.0, 1.2)))->classer(0.85, false, false)['classe'])->toBe(ClasseCouverture::Deficit);
});

it('ne calcule pas la couverture quand un composant a des teneurs inconnues (lot non analysé, décision du 17/10/2026)', function () {
    $ration = [...rationJ1(), new Composant('Foin non analysé', 2.0, null, Composant::ORIGINE_INCONNUE)];
    $c = couvertureEssai();
    $cv = $c->couverture($ration, ['UFL_2018' => Besoin::parJour(9.5)]);

    expect($c->apports($ration)['complet'])->toBeFalse()
        ->and($cv['UFL_2018']->msi)->toEqualWithDelta(13.98, 1e-6)
        ->and($cv['UFL_2018']->complet)->toBeFalse()
        ->and($cv['UFL_2018']->classe)->toBeNull()
        ->and($cv['UFL_2018']->ratio)->toBeNull()
        ->and($c->apports([...rationJ1(), new Composant('Foin non distribué', 0.0, null)])['complet'])->toBeTrue();
});

it('résout les besoins d\'une phase : entretien plus production pour la vache laitière, intervalle par kg MS (RC-9)', function () {
    $r = new ResolutionBesoins;
    $vl = [
        new LigneBesoin('UFL_2018', Besoin::PAR_JOUR, LigneBesoin::BASE_ANIMAL, 5.3, null, null),
        new LigneBesoin('UFL_2018', Besoin::PAR_JOUR, LigneBesoin::BASE_PAR_KG_LAIT, 0.42, null, null),
        new LigneBesoin('PDI', Besoin::PAR_JOUR, LigneBesoin::BASE_PAR_KG_LAIT, 48, null, null),
    ];
    $avecLait = $r->resoudre($vl, 25.0);
    $va = $r->resoudre([new LigneBesoin('MAT', Besoin::PAR_KG_MS, LigneBesoin::BASE_ANIMAL, null, 120, 140)], null);

    expect($avecLait['besoins']['UFL_2018']->valeur)->toEqualWithDelta(5.3 + 0.42 * 25, 1e-6)
        ->and($avecLait['besoins']['PDI']->valeur)->toEqualWithDelta(48 * 25, 1e-6)
        ->and($avecLait['manquants'])->toBe([])
        ->and($r->resoudre($vl, null))->toMatchArray(['besoins' => [], 'manquants' => ['UFL_2018', 'PDI']])
        ->and([$va['besoins']['MAT']->mode, $va['besoins']['MAT']->min, $va['besoins']['MAT']->max])->toBe([Besoin::PAR_KG_MS, 120.0, 140.0]);
});
