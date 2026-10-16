<?php

use App\Domain\Interpretation\Historique;
use App\Domain\Interpretation\PointHistorique;

// RC-4 — série pluriannuelle et variation entre les deux dernières campagnes, sans classe.

it('trie la série par campagne et calcule la variation en valeur et en pourcentage (T-09)', function () {
    $h = new Historique;
    $points = [new PointHistorique('2025-2026', 67.0), new PointHistorique('2023-2024', 70.0), new PointHistorique('2024-2025', 55.0)];

    expect(array_map(fn ($p) => $p->campagne, $h->serie($points)))->toBe(['2023-2024', '2024-2025', '2025-2026']);

    $v = $h->variation($points);
    expect($v->campagneCourante)->toBe('2025-2026')
        ->and($v->campagnePrecedente)->toBe('2024-2025')
        ->and($v->ecart)->toBe(12.0)
        ->and(round((float) $v->pourcentage, 6))->toBe(round(1200 / 55, 6));
});

it('ne donne pas de variation avec une seule campagne, ni de pourcentage sur une valeur précédente nulle', function () {
    $h = new Historique;

    expect($h->variation([new PointHistorique('2025-2026', 4.0)]))->toBeNull()
        ->and($h->variation([new PointHistorique('2024-2025', 0.0), new PointHistorique('2025-2026', 4.0)])->pourcentage)->toBeNull();
});

it('rapporte une baisse d\'une valeur négative à sa valeur absolue (BPR)', function () {
    $v = (new Historique)->variation([new PointHistorique('2024-2025', -40.0), new PointHistorique('2025-2026', -50.0)]);

    expect($v->ecart)->toBe(-10.0)->and($v->pourcentage)->toBe(-25.0);
});
