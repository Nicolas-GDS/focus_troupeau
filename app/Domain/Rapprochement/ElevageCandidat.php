<?php

namespace App\Domain\Rapprochement;

/**
 * Élevage candidat au pré-rapprochement d'un bulletin : identité (prive.elevage_identite) et commune du siège.
 */
final readonly class ElevageCandidat
{
    public function __construct(
        public string $elevageId,
        public string $codePseudo,
        public ?string $raisonSociale = null,
        public ?string $nomContact = null,
        public ?string $adresse = null,
        public ?string $codePostal = null,
        public ?string $commune = null,
    ) {}

    public function libelle(): string
    {
        return $this->raisonSociale !== null && $this->raisonSociale !== '' ? $this->raisonSociale : $this->codePseudo;
    }
}
