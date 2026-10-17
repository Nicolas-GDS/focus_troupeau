<?php

namespace App\Domain\Autonomie;

use DateTimeImmutable;

/**
 * Prélèvements d'une phase d'un lot d'animaux sur les stocks (RC-11) : effectif, dates, pertes à la distribution de la
 * ration, composants tirés d'un lot de stock précis ou d'une famille (kg MS par animal et par jour).
 */
final readonly class ConsommationPhase
{
    /**
     * @param  list<array{lot: string|null, famille: string|null, kg_ms: float}>  $composants  lot précis ou famille
     */
    public function __construct(
        public DateTimeImmutable $debut,
        public DateTimeImmutable $fin,
        public int $effectif,
        public float $pertesDistribution,
        public array $composants,
    ) {}

    public function active(DateTimeImmutable $jour): bool
    {
        return $jour >= $this->debut && $jour <= $this->fin;
    }
}
