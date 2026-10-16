<?php

namespace App\Domain\Interpretation;

/**
 * Valeur d'un paramètre pour une campagne de l'élevage (RC-4) : médiane des lots du même type, et leur nombre.
 */
final readonly class PointHistorique
{
    public function __construct(
        public string $campagne,
        public float $valeur,
        public int $n = 1,
    ) {}
}
