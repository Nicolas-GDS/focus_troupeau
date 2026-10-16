<?php

namespace App\Domain\Dossier;

use App\Domain\Referentiels\StatutEpistemique;

/**
 * Source d'un repère affiché (EF-GEN-02) : référence bibliographique, statut épistémique, validation par le
 * vétérinaire conseil. Une valeur lue sur un repère non validé porte la mention « repère à valider » (§ 9.5,
 * décision du 16/10/2026).
 */
final readonly class Repere
{
    public function __construct(
        public ?int $referenceId,
        public StatutEpistemique $statutEpistemique,
        public bool $valide,
    ) {}

    public function aValider(): bool
    {
        return ! $this->valide;
    }
}
