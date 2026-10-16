<?php

namespace App\Domain\Dossier;

/**
 * Repère d'une mesure terrain (ref.repere_mesure_terrain, RC-1.5) : bornes incluses, nulles = non bornées ;
 * espèce et stade nuls = tous.
 */
final readonly class RepereMesureTerrain
{
    public function __construct(
        public int $id,
        public string $mesureCode,
        public ?float $borneBasse,
        public ?float $borneHaute,
        public Repere $source,
        public ?string $especeCode = null,
        public ?string $stade = null,
    ) {}

    public function sAppliqueA(string $mesureCode, ?string $especeCode, ?string $stade): bool
    {
        return $this->mesureCode === $mesureCode
            && ($this->especeCode === null || $this->especeCode === $especeCode)
            && ($this->stade === null || $this->stade === $stade);
    }

    /** Précision (RC-1.5, décision du 16/10/2026) : espèce × stade > stade > espèce > aucun. */
    public function precision(): int
    {
        return ($this->stade !== null ? 2 : 0) + ($this->especeCode !== null ? 1 : 0);
    }
}
