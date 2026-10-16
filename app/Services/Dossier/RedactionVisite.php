<?php

namespace App\Services\Dossier;

/**
 * Rédaction d'une interprétation de visite (E21, EF-M2-05) : titre orienté décision, synthèse, limites, niveau retenu.
 */
final readonly class RedactionVisite
{
    public function __construct(
        public ?string $titre = null,
        public ?string $synthese = null,
        public ?string $limites = null,
        public ?int $niveauRetenu = null,
    ) {}
}
