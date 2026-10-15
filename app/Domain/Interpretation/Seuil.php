<?php

namespace App\Domain\Interpretation;

use App\Domain\Referentiels\StatutEpistemique;

/**
 * Un seuil actif de la grille d'interprétation (ref.seuil_interpretation) : intervalle [borneMin ; borneMax[,
 * bornes nulles non bornées.
 */
final readonly class Seuil
{
    public function __construct(
        public int $id,
        public string $parametreCode,
        public string $classe,
        public int $gravite,
        public ?float $borneMin = null,
        public ?float $borneMax = null,
        public ?string $familleFourrageCode = null,
        public ?string $typeFourrageCode = null,
        public ?string $especeCode = null,
        public ?string $categorieCode = null,
        public string $nature = 'nutritionnel',
        public ?string $message = null,
        public ?int $referenceId = null,
        public StatutEpistemique $statutEpistemique = StatutEpistemique::Publie,
    ) {}

    public function contient(float $valeur): bool
    {
        return ($this->borneMin === null || $valeur >= $this->borneMin)
            && ($this->borneMax === null || $valeur < $this->borneMax);
    }

    public function sAppliqueA(ContexteLot $lot): bool
    {
        return ($this->typeFourrageCode === null || $this->typeFourrageCode === $lot->typeFourrageCode)
            && ($this->familleFourrageCode === null || $this->familleFourrageCode === $lot->familleFourrageCode)
            && ($this->categorieCode === null || $this->categorieCode === $lot->categorieCode)
            && ($this->especeCode === null || $this->especeCode === $lot->especeCode);
    }

    /**
     * Précision côté fourrage (RC-1) : type 3, famille 2, tous fourrages 1.
     */
    public function precisionFourrage(): int
    {
        return match (true) {
            $this->typeFourrageCode !== null => 3,
            $this->familleFourrageCode !== null => 2,
            default => 1,
        };
    }

    /**
     * Précision côté animal (RC-1) : catégorie 2, espèce 1, aucune 0.
     */
    public function precisionAnimal(): int
    {
        return match (true) {
            $this->categorieCode !== null => 2,
            $this->especeCode !== null => 1,
            default => 0,
        };
    }

    /**
     * Même niveau de précision qu'un autre seuil du même paramètre (même famille, type, espèce, catégorie) :
     * base du contrôle des chevauchements dans E24 (décision du 15/10/2026).
     */
    public function memeNiveauQue(self $autre): bool
    {
        return $this->parametreCode === $autre->parametreCode
            && $this->familleFourrageCode === $autre->familleFourrageCode
            && $this->typeFourrageCode === $autre->typeFourrageCode
            && $this->especeCode === $autre->especeCode
            && $this->categorieCode === $autre->categorieCode;
    }

    public function chevauche(self $autre): bool
    {
        $debut = max($this->borneMin ?? -INF, $autre->borneMin ?? -INF);
        $fin = min($this->borneMax ?? INF, $autre->borneMax ?? INF);

        return $this->memeNiveauQue($autre) && $debut < $fin;
    }
}
