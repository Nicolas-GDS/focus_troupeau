<?php

namespace App\Domain\Interpretation;

/**
 * Quartiles d'une population de référence (RC-3), tels que calculés par les vues valo (percentile_cont, interpolation
 * linéaire) : effectif, Q1, médiane, Q3, minimum et maximum.
 */
final readonly class ReferenceQuartiles
{
    public const DEPARTEMENTALE = 'departementale';

    public const LITHOLOGIQUE = 'lithologique';

    public const BIGDS = 'bigds';

    public function __construct(
        public string $population,
        public int $n,
        public float $q1,
        public float $mediane,
        public float $q3,
        public float $minimum,
        public float $maximum,
    ) {}

    public function suffisante(int $nMin): bool
    {
        return $this->n >= $nMin;
    }
}
