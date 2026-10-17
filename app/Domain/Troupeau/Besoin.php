<?php

namespace App\Domain\Troupeau;

/**
 * Besoin d'un élément pour une phase (RC-9) : par jour (valeur par animal) ou par kg MS de ration (intervalle
 * [min ; max], max facultatif), avec un maximum réglementaire éventuel sur la concentration.
 */
final readonly class Besoin
{
    public const PAR_JOUR = 'par_jour';

    public const PAR_KG_MS = 'par_kg_ms';

    private function __construct(
        public string $mode,
        public ?float $valeur,
        public ?float $min,
        public ?float $max,
        public ?float $maximum,
    ) {}

    public static function parJour(float $valeur, ?float $maximum = null): self
    {
        return new self(self::PAR_JOUR, $valeur, null, null, $maximum);
    }

    public static function parKgMs(float $min, ?float $max = null, ?float $maximum = null): self
    {
        return new self(self::PAR_KG_MS, null, $min, $max, $maximum);
    }
}
