<?php

use App\Domain\Autonomie\ConsommationPhase;
use App\Domain\Autonomie\Incertitude;
use App\Domain\Autonomie\Levier;
use App\Domain\Autonomie\LotProjection;
use App\Domain\Autonomie\MouvementProjection;
use App\Domain\Autonomie\ParametresLeviers;
use App\Domain\Autonomie\Periode;
use App\Domain\Autonomie\Projection;
use App\Domain\Autonomie\PropositionInitiale;

// T-14 (RC-11, RC-12), T-15 (RC-13), T-16 (RC-14) : mêmes vérifications que calculs/test_calculs.py, à 1e-6 près
// (valeurs continues) ou exactement (dates, entiers).

function j(string $date): DateTimeImmutable
{
    return new DateTimeImmutable($date);
}

/** Stocks et phases de J1 (T-14). */
function donneesT14(): array
{
    $sFoin = 400 * 300 * 85 / 100 * 0.97 / 1000;
    $sEnrub = 150 * 650 * 45 / 100 * 0.97 / 1000;
    $sPaille = 100 * 280 * 90 / 100 * 0.97 / 1000;
    $lots = [
        new LotProjection('FOIN25', 'FOIN', 40.0, j('2025-06-10')),
        new LotProjection('FOIN26', 'FOIN', $sFoin, j('2026-06-15')),
        new LotProjection('ENRUB26', 'ENRUB', $sEnrub, j('2026-05-15')),
        new LotProjection('PAILLE26', 'PAILLE', $sPaille, j('2026-07-20')),
    ];
    $phases = [
        new ConsommationPhase(j('2026-11-01'), j('2027-03-31'), 100, 0.13, [['lot' => null, 'famille' => 'FOIN', 'kg_ms' => 4.0], ['lot' => null, 'famille' => 'ENRUB', 'kg_ms' => 7.0]]),
        new ConsommationPhase(j('2026-11-01'), j('2027-03-31'), 30, 0.13, [['lot' => null, 'famille' => 'FOIN', 'kg_ms' => 5.0], ['lot' => null, 'famille' => 'PAILLE', 'kg_ms' => 1.0]]),
    ];

    return [$lots, $phases, $sFoin, $sEnrub, $sPaille];
}

it('T-14 : projette J1 jour par jour, ruptures, épuisement, autonomie et mois (RC-11, RC-12)', function () {
    [$lots, $phases, $sFoin, $sEnrub, $sPaille] = donneesT14();
    $res = (new Projection)->projeter($lots, [], $phases, j('2026-11-01'), j('2027-03-31'), 0.15);
    $jours = 151;
    $bFoin = (100 * 4.0 + 30 * 5.0) / 0.87 / 1000 * $jours;
    $bEnrub = 100 * 7.0 / 0.87 / 1000 * $jours;
    $bPaille = 30 * 1.0 / 0.87 / 1000 * $jours;
    $jEnrub = (int) floor($sEnrub / (100 * 7.0 / 0.87 / 1000)) + 1;
    $jFoin25 = (int) ceil(40.0 / ((100 * 4.0 + 30 * 5.0) / 0.87 / 1000));

    expect($res->jours)->toBe($jours)                                                                      // T-14a
        ->and($res->besoinStock)->toEqualWithDelta($bFoin + $bEnrub + $bPaille, 1e-6)                     // T-14b
        ->and($res->stockUtile)->toEqualWithDelta(40 + $sFoin + $sEnrub + $sPaille, 1e-6)                 // T-14c
        ->and($res->securite)->toEqualWithDelta(0.15 * ($bFoin + $bEnrub + $bPaille), 1e-6)               // T-14d
        ->and($res->bilan)->toEqualWithDelta($res->stockUtile - $res->besoinStock - $res->securite, 1e-6) // T-14e
        ->and($res->ruptureParFamille['ENRUB']?->format('Y-m-d'))->toBe(j('2026-11-01')->modify('+'.($jEnrub - 1).' days')->format('Y-m-d'))   // T-14f
        ->and($res->ruptureParFamille['FOIN'])->toBeNull()                                                // T-14g
        ->and($res->epuisementParLot['FOIN25']?->format('Y-m-d'))->toBe(j('2026-11-01')->modify('+'.($jFoin25 - 1).' days')->format('Y-m-d'))  // T-14h
        ->and($res->ruptureParLot['FOIN25'])->toBeNull()                                                  // T-14h2
        ->and($res->epuisementParLot['FOIN26'])->toBeNull()                                               // T-14i
        ->and($res->dateRupture)->toEqual($res->ruptureParFamille['ENRUB'])                               // T-14j
        ->and($res->autonomieJours)->toBe((int) floor($res->stockUtile / ($res->besoinStock / $jours)))   // T-14k
        ->and($res->mensuel)->toHaveCount(5)                                                              // T-14l
        ->and($res->mensuel[0]['stock_fin'])->toEqualWithDelta($res->mensuel[0]['stock_debut'] - $res->mensuel[0]['conso'] + $res->mensuel[0]['mvts'], 1e-6);   // T-14m
    // valeurs du § 9.1 : rupture de l'enrubannage le 23 décembre, foin 2025 épuisé le 3 janvier, autonomie 139 jours, bilan − 49,54 t MS
    expect($res->ruptureParFamille['ENRUB']->format('Y-m-d'))->toBe('2026-12-23')
        ->and($res->epuisementParLot['FOIN25']->format('Y-m-d'))->toBe('2027-01-03')
        ->and($res->autonomieJours)->toBe(139)
        ->and(round($res->bilan, 2))->toBe(-49.54)
        ->and(round($res->besoinStock, 2))->toBe(222.16)
        ->and(round($res->securite, 2))->toBe(33.32);
});

it('T-14n-p : un achat repousse la rupture, un re-comptage produit un écart de recalage', function () {
    [$lots, $phases, , $sEnrub] = donneesT14();
    $mouvements = [new MouvementProjection('ENRUB26', j('2026-12-15'), 'achat', 30.0), new MouvementProjection('FOIN26', j('2027-01-15'), 'recomptage', 50.0)];
    $res = (new Projection)->projeter($lots, $mouvements, $phases, j('2026-11-01'), j('2027-03-31'), 0.15);
    $jEnrub2 = (int) floor(($sEnrub + 30.0) / (100 * 7.0 / 0.87 / 1000)) + 1;

    expect($res->ruptureParFamille['ENRUB']->format('Y-m-d'))->toBe(j('2026-11-01')->modify('+'.($jEnrub2 - 1).' days')->format('Y-m-d'))
        ->and($res->ruptureParFamille['ENRUB']->format('Y-m-d'))->toBe('2027-01-30')
        ->and($res->ecartsRecalage)->toHaveCount(1)
        ->and($res->ecartsRecalage[0]['ecart'])->toEqualWithDelta($res->ecartsRecalage[0]['projete'] - 50.0, 1e-9)
        ->and(round($res->ecartsRecalage[0]['ecart'], 2))->toBe(41.53);
});

it('consomme un lot précis directement et ordonne les lots d\'une famille (priorité, récolte, non analysés d\'abord)', function () {
    $p = new Projection;
    $ordre = $p->ordreParFamille([
        new LotProjection('B', 'FOIN', 10, j('2026-06-01'), 0, true),
        new LotProjection('A', 'FOIN', 10, j('2026-06-01'), 0, false),
        new LotProjection('C', 'FOIN', 10, j('2025-06-01'), 1),
        new LotProjection('D', 'FOIN', 10, null),
    ]);
    $res = $p->projeter([new LotProjection('X', 'ENRUB', 1.0)], [],
        [new ConsommationPhase(j('2026-11-01'), j('2026-11-10'), 10, 0.0, [['lot' => 'X', 'famille' => null, 'kg_ms' => 20.0]])], j('2026-11-01'), j('2026-11-10'), 0.15);

    expect($ordre['FOIN'])->toBe(['A', 'B', 'D', 'C'])
        ->and($res->ruptureParLot['X']->format('Y-m-d'))->toBe('2026-11-06')
        ->and($res->epuisementParLot['X']->format('Y-m-d'))->toBe('2026-11-05');
});

it('compte les jours équivalents de la voie UGB (RC-10)', function () {
    expect((new Periode)->joursEquivalents([
        ['debut' => j('2026-11-01'), 'fin' => j('2027-03-31'), 'coefficient' => 1.0],
        ['debut' => j('2027-04-01'), 'fin' => j('2027-04-30'), 'coefficient' => 0.5],
    ]))->toEqualWithDelta(151 + 15, 1e-9);
});

it('T-15 : leviers, gain et coût (RC-13.3)', function () {
    $levier = new Levier(new ParametresLeviers(['FOIN' => 0.44, 'ENSIL_HERBE' => 0.53, 'ENSIL_MAIS' => 0.70], 13.0));
    $ctx = ['jours' => 160, 'besoin_jour_t_ms' => 1.4, 'besoin_ration_t_ms' => 200.0, 'pertes_distribution' => 0.13];

    expect($levier->evaluer(Levier::ACHAT, 20, 170, 'FOIN', $ctx))->toBe(['gain' => 20.0, 'cout' => 3400.0])                  // T-15a
        ->and($levier->evaluer(Levier::CONCENTRE, 10, 290, 'FOIN', $ctx)['gain'])->toEqualWithDelta(4.4, 1e-9)                 // T-15b
        ->and($levier->evaluer(Levier::CONCENTRE, 10, 290, 'FOIN', $ctx)['cout'])->toBe(2900.0)
        ->and($levier->evaluer(Levier::DECAPITALISATION, 5, 900, null, $ctx))->toBe(['gain' => 5 * 13 * 160 / 1000, 'cout' => 4500.0]) // T-15c
        ->and($levier->evaluer(Levier::PATURAGE, 10, 0, null, $ctx)['gain'])->toEqualWithDelta(14.0, 1e-9)                     // T-15d
        ->and($levier->evaluer(Levier::RATELIER, 0, 0.04, null, $ctx)['gain'])->toEqualWithDelta(200 * (1 / 0.87 - 1 / 0.96), 1e-9)   // T-15e
        ->and($levier->evaluer(Levier::GENISSES, 3, 6, null, $ctx))->toBe(['gain' => 18.0, 'cout' => 0.0])
        ->and($levier->evaluer(Levier::CONCENTRE, 10, 290, 'ENSIL_MAIS', $ctx)['gain'])->toEqualWithDelta(7.0, 1e-9);
});

it('T-15f-h : réaffectation puis achat du déficit restant (RC-13.2)', function () {
    $p = new PropositionInitiale;
    $lignes = $p->proposer(['ENRUB' => 25.0, 'PAILLE' => 0.0], ['FOIN' => 40.0], [['source' => 'FOIN', 'cible' => 'ENRUB', 'ratio' => 1.0]], ['ENRUB' => 190.0]);
    $lignes2 = $p->proposer(['ENRUB' => 25.0], ['FOIN' => 10.0], [['source' => 'FOIN', 'cible' => 'ENRUB', 'ratio' => 1.0]], ['ENRUB' => 190.0]);

    expect([$lignes[0]['levier'], $lignes[0]['quantite']])->toBe(['reaffectation', 25.0])
        ->and($lignes)->toHaveCount(1)
        ->and([$lignes2[1]['quantite'], $lignes2[1]['cout']])->toBe([15.0, 2850.0])
        ->and($d = $p->diagnostic(['FOIN' => 95.5, 'ENRUB' => 121.5], ['FOIN' => 14.3, 'ENRUB' => 18.2], ['FOIN' => 138.9, 'ENRUB' => 42.6]))
        ->and(array_keys($d['deficits']))->toBe(['ENRUB'])
        ->and($d['deficits']['ENRUB'])->toEqualWithDelta(121.5 + 18.2 - 42.6, 1e-9)
        ->and($d['excedents']['FOIN'])->toEqualWithDelta(138.9 - 95.5 - 14.3, 1e-9);
});

it('T-16 : incertitude d\'un silo, de balles estimées et du total (RC-14)', function () {
    $i = new Incertitude;

    expect($i->lot([0.05, 0.07, $i->ms(2.0, 33), 0.5 * 0.05]))->toEqualWithDelta(sqrt(0.05 ** 2 + 0.07 ** 2 + (2 / 33) ** 2 + 0.025 ** 2), 1e-12)
        ->and($i->lot([0.15, $i->ms(5.0, 85), 0.5 * 0.03]))->toEqualWithDelta(sqrt(0.15 ** 2 + (5 / 85) ** 2 + 0.015 ** 2), 1e-12)
        ->and($i->totale([new LotProjection('A', 'FOIN', 100.0, incertitude: 0.10), new LotProjection('B', 'FOIN', 50.0, incertitude: 0.20)]))
        ->toEqualWithDelta(sqrt(10 ** 2 + 10 ** 2) / 150, 1e-12)
        ->and(array_map(fn ($l) => $l->stockUtile, $i->bornes([new LotProjection('A', 'FOIN', 100.0, incertitude: 0.10)], -1)))->toBe([90.0]);
});
