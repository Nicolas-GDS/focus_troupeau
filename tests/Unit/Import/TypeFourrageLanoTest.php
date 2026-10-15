<?php

use App\Services\Import\TypeFourrageLano;

// Type de fourrage du prélèvement créé à l'import (proposition du lot L2, à confirmer par le technicien).

it('déduit le type de fourrage des quatre bulletins d\'exemple', function (string $famille, array $entete, string $type, bool $certain) {
    expect((new TypeFourrageLano)->deduire($famille, $entete))->toBe(['type' => $type, 'certain' => $certain]);
})->with([
    'HA25-41524' => ['FOURRAGE_DIVERS', ['nom_echantillon' => 'ENSILAGE SORGHO BMR FERMENTE'], 'ENSIL_SORGHO', true],
    'HA25-41525' => ['FOIN', ['espece_fourrage' => 'Dactyle (50 %) / Luzerne (50 %)', 'nom_echantillon' => 'FOIN DACTYLE / LUZERNE'], 'FOIN_ASSOC', true],
    'HA25-41526' => ['FOIN', ['espece_fourrage' => 'Prairie Naturelle (100 %)'], 'FOIN_PP', true],
    'HA25-41527' => ['ENSILAGE_ENRUBANNAGE_HERBE', ['espece_fourrage' => 'Dactyle (50 %) / Luzerne (50 %)', 'nom_echantillon' => 'ENRUBANNAGE DACTYLE / LUZERNE'], 'ENRUB_HERBE', true],
]);

it('reconnaît la luzerne seule et les cas incertains', function () {
    $types = new TypeFourrageLano;

    expect($types->deduire('FOIN', ['espece_fourrage' => 'Luzerne (100 %)']))->toBe(['type' => 'FOIN_LUZ', 'certain' => true])
        ->and($types->deduire('ENSILAGE_ENRUBANNAGE_HERBE', ['nom_echantillon' => 'ENRUBANNAGE LUZERNE']))->toBe(['type' => 'ENRUB_LUZ', 'certain' => true])
        ->and($types->deduire('FOIN', ['nom_echantillon' => 'FOIN GENISSES'])['certain'])->toBeFalse()
        ->and($types->deduire('AUTRE', [])['certain'])->toBeFalse();
});
