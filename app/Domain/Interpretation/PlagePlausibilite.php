<?php

namespace App\Domain\Interpretation;

/**
 * Plage de plausibilité acceptée [borneMin ; borneMax] (RC-2), pour un paramètre et, le cas échéant,
 * une famille de fourrage (MS). Bornes nulles : non bornée.
 */
final readonly class PlagePlausibilite
{
    public function __construct(
        public string $parametreCode,
        public ?float $borneMin = null,
        public ?float $borneMax = null,
        public ?string $familleFourrageCode = null,
    ) {}

    public function sAppliqueA(string $parametreCode, string $familleFourrageCode): bool
    {
        return $this->parametreCode === $parametreCode
            && ($this->familleFourrageCode === null || $this->familleFourrageCode === $familleFourrageCode);
    }

    /**
     * Une valeur censurée « < x » n'est hors plage que si x ≤ borne basse (la vraie valeur est alors sous la plage) ;
     * « > x » symétriquement. Une valeur exacte est hors plage si elle sort de [borneMin ; borneMax].
     */
    public function rejette(ValeurAnalysee $valeur): bool
    {
        return match ($valeur->censure) {
            '<' => $this->borneMin !== null && $valeur->valeur <= $this->borneMin,
            '>' => $this->borneMax !== null && $valeur->valeur >= $this->borneMax,
            default => ($this->borneMin !== null && $valeur->valeur < $this->borneMin)
                || ($this->borneMax !== null && $valeur->valeur > $this->borneMax),
        };
    }
}
