<?php

use App\Services\Import\CorrespondanceLano;

// Annexe 10.3 : clés du parseur LANO → codes de ref.parametre.

it('traduit les clés du parseur, la censure et l\'unité', function () {
    $traduit = (new CorrespondanceLano)->traduire([
        'Ca' => 6.5, 'Ca__unit' => 'g/kg MS',
        'Na' => '<0,1', 'Na__unit' => 'g/kg MS',
        'dMO' => 63.2, 'dMO__unit' => '%',
        'P_abs' => 1.6, 'P_abs__unit' => 'g/kg MS',
        'EB' => 4431.0, 'EB__unit' => 'Kcal/kg MS',
    ]);
    $r = collect($traduit['resultats'])->keyBy('parametreCode');

    expect($r->keys()->all())->toBe(['CA', 'NA', 'DMO', 'P_ABS', 'EB'])
        ->and($r['NA']->valeur)->toBe(0.1)
        ->and($r['NA']->censure)->toBe('<')
        ->and($r['EB']->unite)->toBe('kcal/kg MS')
        ->and($traduit['avertissements'])->toBe([]);
});

it('n\'importe pas une clé inconnue ou ambiguë et le signale', function () {
    $traduit = (new CorrespondanceLano)->traduire(['MG' => 12.0, 'MG__unit' => 'g/kg MS', 'XYZ' => 1.0, 'Mg' => 1.8, 'Mg__unit' => 'g/kg MS']);

    expect(array_map(fn ($r) => $r->parametreCode, $traduit['resultats']))->toBe(['MG'])
        ->and($traduit['resultats'][0]->valeur)->toBe(1.8)
        ->and($traduit['avertissements'])->toHaveCount(2);
});

it('conserve la première valeur d\'un paramètre présent deux fois (Lignine et ADL)', function () {
    $traduit = (new CorrespondanceLano)->traduire(['ADL' => 38.0, 'ADL__unit' => 'g/kg MS', 'LIG' => 40.0, 'LIG__unit' => 'g/kg MS']);

    expect($traduit['resultats'])->toHaveCount(1)
        ->and($traduit['resultats'][0]->valeur)->toBe(38.0)
        ->and($traduit['avertissements'])->toHaveCount(1);
});
