<?php

use App\Services\Import\CorrespondanceLano;
use App\Services\Import\ExtracteurLano;
use App\Services\Import\ExtractionImpossible;

// EF-M9-01, risque § 9.5 (« extraction PDF LANO fragile ») : non-régression de scripts/parse_lano.py sur les quatre
// bulletins anonymisés de tests/Fixtures/lano (pdftotext réel, Python réel).

function bulletinExemple(string $numero): string
{
    return base_path("tests/Fixtures/lano/{$numero}_ELV-0001_anonymise.pdf");
}

it('extrait l\'en-tête, la famille de rapport et les résultats de chaque bulletin', function (string $numero, string $famille, int $nombre, array $valeurs) {
    $donnees = (new ExtracteurLano)->extraire(bulletinExemple($numero));
    $resultats = collect((new CorrespondanceLano)->traduire($donnees['resultats'])['resultats'])->keyBy('parametreCode');

    expect($donnees['entete']['num_echantillon'])->toBe($numero)
        ->and($donnees['entete']['date_prelevement'])->toBe('05/12/2025')
        ->and($donnees['famille_rapport'])->toBe($famille)
        ->and($resultats)->toHaveCount($nombre);

    foreach ($valeurs as $code => $attendu) {
        expect($resultats[$code]->valeur)->toEqualWithDelta($attendu, 1e-9);
    }
})->with([
    'ensilage de sorgho' => ['HA25-41524', 'FOURRAGE_DIVERS', 22, ['MS' => 20.8, 'MAT' => 79, 'CA' => 4.4, 'CU' => 6.6, 'FE' => 113.6, 'UFL' => 0.83]],
    'foin dactyle / luzerne' => ['HA25-41525', 'FOIN', 41, ['MS' => 88.0, 'MAT' => 154, 'MG' => 1.8, 'CU' => 6.3, 'UFL_2018' => 0.77, 'PDI' => 84, 'BACA' => 311, 'BPR' => 17]],
    'foin de prairie naturelle' => ['HA25-41526', 'FOIN', 41, ['MS' => 89.0, 'MAT' => 67, 'CU' => 4.0, 'MN' => 131.5, 'FE' => 280, 'BPR' => -47]],
    'enrubannage dactyle / luzerne' => ['HA25-41527', 'ENSILAGE_ENRUBANNAGE_HERBE', 41, ['MS' => 63.9, 'K' => 26.2, 'CU' => 5.3, 'UFL_2018' => 0.83, 'EB' => 4484]],
]);

it('lit les unités du bulletin, normalisées sur le dictionnaire', function () {
    $resultats = collect((new CorrespondanceLano)->traduire((new ExtracteurLano)->extraire(bulletinExemple('HA25-41525'))['resultats'])['resultats'])
        ->keyBy('parametreCode');

    expect($resultats['MS']->unite)->toBe('% brut')
        ->and($resultats['CU']->unite)->toBe('mg/kg MS')
        ->and($resultats['EB']->unite)->toBe('kcal/kg MS');
});

it('signale un fichier qui n\'est pas un PDF lisible', function () {
    $faux = tempnam(sys_get_temp_dir(), 'lano');
    file_put_contents($faux, 'pas un pdf');

    expect(fn () => (new ExtracteurLano)->extraire($faux))->toThrow(ExtractionImpossible::class);
});
