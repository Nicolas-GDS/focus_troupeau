<?php

namespace App\Domain\Interpretation;

/**
 * Niveau global proposé au technicien (RC-1.4) et motifs des rehaussements, affichés sans être imposés.
 */
final readonly class NiveauPropose
{
    /**
     * @param  list<string>  $rehaussements  motifs lisibles des rehaussements appliqués
     */
    public function __construct(
        public int $niveau,
        public int $niveauAvantRehaussement,
        public array $rehaussements = [],
    ) {}
}
