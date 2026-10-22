<?php

use App\Domain\Agds\BovinAgds;
use App\Domain\Agds\CompositionTroupeau;

// Composition du troupeau bovin lu dans AGDS : effectifs par sexe et tranche d'âge, races, bovins sans date de naissance.

function bovin(?string $sexe, ?string $naissance, ?string $race = '34'): BovinAgds
{
    return new BovinAgds(null, 250, $sexe, $race, $naissance === null ? null : new DateTimeImmutable($naissance), null);
}

it('répartit les bovins par sexe et par tranche d\'âge en mois révolus', function () {
    $date = new DateTimeImmutable('2026-10-22');
    $composition = (new CompositionTroupeau([12, 24]))->calculer([
        bovin('F', '2026-01-01'),            // 9 mois
        bovin('F', '2025-10-23'),            // 11 mois (veille de l'anniversaire)
        bovin('F', '2025-10-22'),            // 12 mois pile
        bovin('F', '2018-12-18', '46'),
        bovin('M', '2024-10-22'),            // 24 mois pile
        bovin('X', '2020-01-01'),
        bovin('F', null, null),
    ], $date);

    expect($composition['total'])->toBe(7)
        ->and($composition['tranches'])->toBe(['moins de 12 mois', '12 à 24 mois', '24 mois et plus'])
        ->and($composition['effectifs'])->toBe([
            'F' => ['moins de 12 mois' => 2, '12 à 24 mois' => 1, '24 mois et plus' => 1],
            'M' => ['24 mois et plus' => 1],
            '?' => ['24 mois et plus' => 1],
        ])
        ->and($composition['races'])->toBe(['34' => 5, '46' => 1, '?' => 1])
        ->and($composition['sans_date_naissance'])->toBe(1);
});

it('compte un troupeau vide', function () {
    expect((new CompositionTroupeau([12]))->calculer([], new DateTimeImmutable('2026-10-22')))
        ->toMatchArray(['total' => 0, 'effectifs' => [], 'races' => [], 'sans_date_naissance' => 0]);
});

it('refuse des bornes vides, négatives ou non croissantes', function (array $bornes) {
    new CompositionTroupeau($bornes);
})->throws(InvalidArgumentException::class)->with([[[]], [[0, 12]], [[24, 12]]]);
