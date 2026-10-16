<?php

use App\Domain\Interpretation\Position;
use App\Domain\Interpretation\Positionnement;
use App\Domain\Interpretation\ReferenceQuartiles;

// RC-3 — positionnement par quartiles, seuil n_min, ordre d'affichage des populations.

function quartilesEssai(string $population, int $n, float $q1 = 60.0, float $q3 = 70.0): ReferenceQuartiles
{
    return new ReferenceQuartiles($population, $n, $q1, ($q1 + $q3) / 2, $q3, $q1 - 5, $q3 + 5);
}

it('classe faible sous Q1, normal de Q1 à Q3 inclus, élevé au-dessus de Q3', function () {
    $p = new Positionnement;
    $ref = quartilesEssai(ReferenceQuartiles::DEPARTEMENTALE, 17, 62.0, 70.0);

    expect($p->classer(60.0, $ref, 5))->toBe(Position::Faible)
        ->and($p->classer(62.0, $ref, 5))->toBe(Position::Normal)
        ->and($p->classer(70.0, $ref, 5))->toBe(Position::Normal)
        ->and($p->classer(70.1, $ref, 5))->toBe(Position::Eleve);
});

it('déclare la référence insuffisante sous n_min (T-07 : n = 4) et classe à partir de n_min', function () {
    $p = new Positionnement;

    expect($p->classer(50.0, quartilesEssai(ReferenceQuartiles::DEPARTEMENTALE, 4), 5))->toBe(Position::Insuffisante)
        ->and($p->classer(50.0, quartilesEssai(ReferenceQuartiles::DEPARTEMENTALE, 5), 5))->toBe(Position::Faible)
        ->and($p->classer(50.0, quartilesEssai(ReferenceQuartiles::DEPARTEMENTALE, 5), 6))->toBe(Position::Insuffisante);
});

it('affiche la départementale, puis la lithologique suffisante, puis le bi-GDS seulement en repli', function () {
    $p = new Positionnement;
    $dep = quartilesEssai(ReferenceQuartiles::DEPARTEMENTALE, 17);
    $depFaible = quartilesEssai(ReferenceQuartiles::DEPARTEMENTALE, 3);
    $litho = quartilesEssai(ReferenceQuartiles::LITHOLOGIQUE, 6);
    $lithoFaible = quartilesEssai(ReferenceQuartiles::LITHOLOGIQUE, 4);
    $bigds = quartilesEssai(ReferenceQuartiles::BIGDS, 30);
    $populations = fn (array $refs) => array_map(fn (ReferenceQuartiles $r) => $r->population, $refs);

    expect($populations($p->populationsAffichees($dep, $litho, $bigds, 5)))->toBe(['departementale', 'lithologique'])
        ->and($populations($p->populationsAffichees($dep, $lithoFaible, $bigds, 5)))->toBe(['departementale'])
        ->and($populations($p->populationsAffichees($depFaible, null, $bigds, 5)))->toBe(['departementale', 'bigds'])
        ->and($populations($p->populationsAffichees(null, null, $bigds, 5)))->toBe(['bigds'])
        ->and($p->populationsAffichees(null, null, null, 5))->toBe([]);
});
