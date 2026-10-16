<?php

namespace App\Domain\Dossier;

/**
 * Seuils S1 / S2 d'un indicateur du bilan sanitaire (ref.seuil_bilan_sanitaire, EF-M1-07). Sens « haut » : la
 * situation se dégrade au-delà des seuils ; sens « bas » : en deçà.
 */
final readonly class SeuilBilanSanitaire
{
    public const SENS_HAUT = 'haut';

    public const SENS_BAS = 'bas';

    public function __construct(
        public int $id,
        public string $indicateurCode,
        public ?float $s1,
        public ?float $s2,
        public string $sens,
        public Repere $source,
        public ?string $especeCode = null,
        public ?string $systeme = null,
    ) {}

    public function sAppliqueA(string $indicateurCode, ?string $especeCode, ?string $systeme): bool
    {
        return $this->indicateurCode === $indicateurCode
            && ($this->especeCode === null || $this->especeCode === $especeCode)
            && ($this->systeme === null || $this->systeme === $systeme);
    }

    public function precision(): int
    {
        return ($this->systeme !== null ? 2 : 0) + ($this->especeCode !== null ? 1 : 0);
    }
}
