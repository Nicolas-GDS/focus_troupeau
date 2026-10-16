<?php

namespace App\Domain\Dossier;

/**
 * Case de la grille trouble × famille de cause (ref.grille_trouble_cause) : force 0 (aucun lien) à 3 (lien fort).
 */
final readonly class LienTroubleCause
{
    public const FAMILLES = ['immunite_oligo', 'metabolisme_mineral', 'metabolisme_energetique', 'confort', 'infectieux'];

    public function __construct(
        public string $troubleCode,
        public string $familleCause,
        public int $force,
        public Repere $source,
    ) {}
}
