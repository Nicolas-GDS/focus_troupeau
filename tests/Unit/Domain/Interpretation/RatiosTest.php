<?php

use App\Domain\Interpretation\Ratios;
use App\Domain\Interpretation\ValeurAnalysee;

// RC-1 — ratios dérivés calculés avant classement (à 1e-6 près, § 4.4).

/**
 * @param  array<string, float|array{0: float, 1: string}>  $valeurs
 * @return list<ValeurAnalysee>
 */
function valeurs(array $valeurs): array
{
    return array_map(
        fn (string $code, float|array $v) => is_array($v) ? new ValeurAnalysee($code, $v[0], $v[1]) : new ValeurAnalysee($code, $v),
        array_keys($valeurs),
        $valeurs,
    );
}

/**
 * @param  list<ValeurAnalysee>  $ratios
 * @return array<string, float>
 */
function parCode(array $ratios): array
{
    return array_combine(array_map(fn ($r) => $r->parametreCode, $ratios), array_map(fn ($r) => $r->valeur, $ratios));
}

it('calcule Ca/P, K/(Ca+Mg) en équivalents et PDI/UFL', function () {
    $ratios = parCode((new Ratios)->calculer(valeurs(['CA' => 6.0, 'P' => 2.4, 'K' => 25.0, 'MG' => 1.8, 'PDI' => 70.0, 'UFL_2018' => 0.70])));

    expect($ratios['CA_P'])->toEqualWithDelta(2.5, 1e-9)
        ->and($ratios['K_CAMG_EQ'])->toEqualWithDelta((25 / 39.1) / (6 / 20.04 + 1.8 / 12.15), 1e-9)
        ->and($ratios['PDI_UFL'])->toEqualWithDelta(100.0, 1e-9)
        ->and($ratios)->not->toHaveKey('BACA');
});

it('calcule la BACA en mEq/kg MS seulement si S et Cl sont dosés', function () {
    $ratios = parCode((new Ratios)->calculer(valeurs(['NA' => 1.0, 'K' => 25.0, 'CL' => 6.0, 'S' => 2.5])));
    $attendu = ((1 / 23 + 25 / 39.1) - (6 / 35.5 + 2.5 / 16)) * 1000;

    expect($ratios['BACA'])->toEqualWithDelta($attendu, 1e-6)
        ->and($attendu)->toEqualWithDelta(357.6, 0.01);
});

it('propose une BE partielle (Na + K − Cl) quand S n\'est pas dosé, sans l\'enregistrer comme BACA', function () {
    $ratios = new Ratios;
    $v = valeurs(['NA' => 1.0, 'K' => 25.0, 'CL' => 6.0]);

    expect(parCode($ratios->calculer($v)))->not->toHaveKey('BACA')
        ->and($ratios->bePartielle($v))->toEqualWithDelta((1 / 23 + 25 / 39.1 - 6 / 35.5) * 1000, 1e-6)
        ->and($ratios->bePartielle(valeurs(['NA' => 1.0, 'K' => 25.0])))->toBeNull();
});

it('ne calcule aucun ratio avec une valeur censurée ou un dénominateur nul', function () {
    $ratios = parCode((new Ratios)->calculer(valeurs(['CA' => 6.0, 'P' => [0.5, '<'], 'PDI' => 70.0, 'UFL_2018' => 0.0])));

    expect($ratios)->toBe([]);
});
