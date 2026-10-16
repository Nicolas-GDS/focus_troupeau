<?php

namespace App\Domain\Dossier;

/**
 * Trouble de la grille de croisement (ref.trouble_croisement, grille NBVC) et ses correspondances : indicateurs
 * du bilan sanitaire et types d'événements sanitaires (codes de ref.trouble_sanitaire).
 */
final readonly class TroubleCroisement
{
    /**
     * @param  list<string>  $indicateursSanitaires
     * @param  list<string>  $troublesSanitaires
     */
    public function __construct(
        public string $code,
        public string $libelle,
        public int $ordre,
        public array $indicateursSanitaires = [],
        public array $troublesSanitaires = [],
    ) {}
}
