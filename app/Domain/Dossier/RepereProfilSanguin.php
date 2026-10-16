<?php

namespace App\Domain\Dossier;

/**
 * Repère d'un élément d'un profil sanguin (ref.repere_analyse_animale, RC-1.5), dans l'unité indiquée.
 */
final readonly class RepereProfilSanguin
{
    public function __construct(
        public int $id,
        public string $element,
        public string $matrice,
        public ?float $borneBasse,
        public ?float $borneHaute,
        public string $unite,
        public Repere $source,
        public ?string $especeCode = null,
    ) {}

    public function sAppliqueA(string $element, string $matrice, ?string $especeCode): bool
    {
        return $this->element === $element && $this->matrice === $matrice
            && ($this->especeCode === null || $this->especeCode === $especeCode);
    }
}
