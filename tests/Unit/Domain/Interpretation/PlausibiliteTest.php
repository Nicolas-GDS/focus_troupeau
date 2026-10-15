<?php

use App\Domain\Interpretation\PlagePlausibilite;
use App\Domain\Interpretation\Plausibilite;
use App\Domain\Interpretation\ValeurAnalysee;

// RC-2 — contrôle de plausibilité à l'import.

/**
 * @return list<PlagePlausibilite>
 */
function plagesRc2(): array
{
    return [
        new PlagePlausibilite('MS', 75, 95, 'FOIN'),
        new PlagePlausibilite('MS', 25, 80, 'ENRUB'),
        new PlagePlausibilite('NA', 0, 6),
        new PlagePlausibilite('CU', 0, 30),
    ];
}

it('accepte les bornes de la plage et rejette ce qui en sort', function (float $na, bool $rejete) {
    $anomalies = (new Plausibilite)->controler([new ValeurAnalysee('NA', $na)], 'FOIN', plagesRc2());

    expect($anomalies !== [])->toBe($rejete);
})->with([[0.0, false], [6.0, false], [6.01, true], [13.6, true]]);

it('contrôle la MS selon la famille du fourrage', function () {
    $plausibilite = new Plausibilite;
    $ms = [new ValeurAnalysee('MS', 63.9)];

    expect($plausibilite->controler($ms, 'ENRUB', plagesRc2()))->toBe([])
        ->and($plausibilite->controler($ms, 'FOIN', plagesRc2()))->toHaveCount(1)
        ->and($plausibilite->controler($ms, 'CONCENTRE', plagesRc2()))->toBe([]);
});

it('ne rejette une valeur censurée que si elle est certainement hors plage', function () {
    $plausibilite = new Plausibilite;
    $plages = [new PlagePlausibilite('CU', 2, 30)];

    expect($plausibilite->controler([new ValeurAnalysee('CU', 0.5, '<')], 'FOIN', $plages))->toHaveCount(1)
        ->and($plausibilite->controler([new ValeurAnalysee('CU', 5, '<')], 'FOIN', $plages))->toBe([])
        ->and($plausibilite->controler([new ValeurAnalysee('CU', 40, '>')], 'FOIN', $plages))->toHaveCount(1)
        ->and($plausibilite->controler([new ValeurAnalysee('CU', 20, '>')], 'FOIN', $plages))->toBe([]);
});

it('ignore un paramètre sans plage', function () {
    expect((new Plausibilite)->controler([new ValeurAnalysee('DON', 99999)], 'FOIN', plagesRc2()))->toBe([]);
});
