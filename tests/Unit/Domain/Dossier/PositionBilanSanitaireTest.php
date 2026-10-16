<?php

use App\Domain\Dossier\ClasseBilan;
use App\Domain\Dossier\PositionBilanSanitaire;
use App\Domain\Dossier\Repere;
use App\Domain\Dossier\SeuilBilanSanitaire;
use App\Domain\Referentiels\StatutEpistemique;

// EF-M1-07 — position des indicateurs du bilan sanitaire par rapport aux seuils S1 / S2 ; cas de la présentation
// Forgeneuf (diapositives 7 et 8).

function seuilBilan(string $indicateur, ?float $s1, ?float $s2, string $sens = 'haut', ?string $espece = 'BOV', ?string $systeme = 'viande', int $id = 1): SeuilBilanSanitaire
{
    return new SeuilBilanSanitaire($id, $indicateur, $s1, $s2, $sens,
        new Repere(null, StatutEpistemique::ConsensusProfessionnel, false), $espece, $systeme);
}

it('calcule le taux en pour cent des vêlages', function () {
    expect(PositionBilanSanitaire::taux(10, 81))->toEqualWithDelta(12.35, 0.01)
        ->and(PositionBilanSanitaire::taux(null, 81))->toBeNull()
        ->and(PositionBilanSanitaire::taux(3, 0))->toBeNull();
});

it('situe les taux de Forgeneuf comme la source', function (string $indicateur, ?float $s1, float $s2, float $taux, ClasseBilan $attendu) {
    expect((new PositionBilanSanitaire)->classer($taux, seuilBilan($indicateur, $s1, $s2)))->toBe($attendu);
})->with([
    'retournements 1,2 % (S1 0, S2 1) : très dégradée' => ['RETOURNEMENT', 0, 1, 1.2, ClasseBilan::TresDegradee],
    'retournements 0 % : favorable' => ['RETOURNEMENT', 0, 1, 0, ClasseBilan::Favorable],
    'métrites 6 % (5 / 10) : dégradée' => ['METRITE', 5, 10, 6, ClasseBilan::Degradee],
    'métrites 12 % : très dégradée' => ['METRITE', 5, 10, 12, ClasseBilan::TresDegradee],
    'non-délivrances 6 % (8 / 12) : favorable' => ['NON_DELIVRANCE', 8, 12, 6, ClasseBilan::Favorable],
    'non-délivrances 12 % (= S2) : dégradée' => ['NON_DELIVRANCE', 8, 12, 12, ClasseBilan::Degradee],
    'non-délivrances 8 % (= S1) : favorable' => ['NON_DELIVRANCE', 8, 12, 8, ClasseBilan::Favorable],
    'mortalité des veaux 6 % (S2 5, sans S1) : très dégradée' => ['MORTALITE_VEAUX_0_12M', null, 5, 6, ClasseBilan::TresDegradee],
    'mortalité des veaux 5 % : favorable' => ['MORTALITE_VEAUX_0_12M', null, 5, 5, ClasseBilan::Favorable],
]);

it('ne classe pas un indicateur sans seuil', function () {
    expect((new PositionBilanSanitaire)->classer(406, seuilBilan('IVV', null, null)))->toBeNull();
});

it('lit un seuil de sens « bas » de façon symétrique', function () {
    $seuil = seuilBilan('TAUX_FAVORABLE', 90, 80, 'bas');
    $position = new PositionBilanSanitaire;

    expect($position->classer(95, $seuil))->toBe(ClasseBilan::Favorable)
        ->and($position->classer(85, $seuil))->toBe(ClasseBilan::Degradee)
        ->and($position->classer(75, $seuil))->toBe(ClasseBilan::TresDegradee);
});

it('retient le seuil le plus précis : système, puis espèce', function () {
    $seuils = [
        seuilBilan('METRITE', 1, 2, espece: null, systeme: null, id: 1),
        seuilBilan('METRITE', 3, 4, espece: 'BOV', systeme: null, id: 2),
        seuilBilan('METRITE', 5, 10, espece: 'BOV', systeme: 'viande', id: 3),
    ];
    $position = new PositionBilanSanitaire;

    expect($position->seuil('METRITE', 'BOV', 'viande', $seuils)?->id)->toBe(3)
        ->and($position->seuil('METRITE', 'BOV', 'lait', $seuils)?->id)->toBe(2)
        ->and($position->seuil('METRITE', 'OVI', null, $seuils)?->id)->toBe(1)
        ->and($position->seuil('IVV', 'BOV', 'viande', $seuils))->toBeNull();
});
