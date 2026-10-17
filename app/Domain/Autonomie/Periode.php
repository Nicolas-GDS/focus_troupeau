<?php

namespace App\Domain\Autonomie;

use DateTimeImmutable;

/**
 * Période de référence (RC-10) : jours équivalents de la voie UGB = Σ jours de la phase × coefficient de ration.
 */
final class Periode
{
    /**
     * @param  list<array{debut: DateTimeImmutable, fin: DateTimeImmutable, coefficient: float}>  $phases
     */
    public function joursEquivalents(array $phases): float
    {
        return array_sum(array_map(fn (array $p) => ((int) $p['debut']->diff($p['fin'])->days + 1) * $p['coefficient'], $phases));
    }
}
