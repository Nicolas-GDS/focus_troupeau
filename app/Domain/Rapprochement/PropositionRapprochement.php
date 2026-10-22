<?php

namespace App\Domain\Rapprochement;

/**
 * Élevage proposé au technicien pour un bulletin à rapprocher : score sur 100, niveau de confiance et motifs
 * lisibles. Une proposition n'est jamais appliquée seule : le technicien la retient ou choisit un autre élevage.
 */
final readonly class PropositionRapprochement
{
    public const FORTE = 'forte';

    public const MOYENNE = 'moyenne';

    public const FAIBLE = 'faible';

    /**
     * @param  list<string>  $motifs
     */
    public function __construct(
        public ElevageCandidat $elevage,
        public int $score,
        public string $niveau,
        public array $motifs,
    ) {}
}
