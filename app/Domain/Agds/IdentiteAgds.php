<?php

namespace App\Domain\Agds;

use DateTimeImmutable;

/**
 * Identité de l'éleveur d'un cheptel, lue dans AGDS par SAGEMA (vue focus.agds_elevage) : lieu de l'exploitation
 * et adresse de correspondance, coordonnées. Lecture seule, jamais recopiée dans prive.elevage_identite.
 */
final readonly class IdentiteAgds
{
    /**
     * @param  list<string>  $adresse
     * @param  list<string>  $adresseCorrespondance
     */
    public function __construct(
        public string $numEde,
        public ?string $nom,
        public ?string $civilite,
        public array $adresse,
        public ?string $codePostal,
        public ?string $commune,
        public ?string $nomCorrespondance,
        public array $adresseCorrespondance,
        public ?string $codePostalCorrespondance,
        public ?string $communeCorrespondance,
        public ?string $telephone,
        public ?string $mobile,
        public ?string $courriel,
        public ?DateTimeImmutable $dateDebutActivite,
        public ?DateTimeImmutable $dateCessation,
    ) {}

    public function cesse(DateTimeImmutable $date): bool
    {
        return $this->dateCessation !== null && $this->dateCessation <= $date;
    }
}
