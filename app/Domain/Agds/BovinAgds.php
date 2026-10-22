<?php

namespace App\Domain\Agds;

use DateTimeImmutable;

/**
 * Bovin présent d'un cheptel, lu dans AGDS par SAGEMA (vue focus.agds_bovin_present).
 * Le numéro est le numéro national (bip.nuidbo, nullable), jamais la clé technique AGDS.
 */
final readonly class BovinAgds
{
    public const FEMELLE = 'F';

    public const MALE = 'M';

    public function __construct(
        public ?string $numeroNational,
        public ?int $paysIso,
        public ?string $sexe,
        public ?string $raceCode,
        public ?DateTimeImmutable $dateNaissance,
        public ?DateTimeImmutable $dateEntree,
    ) {}

    /** Âge en mois révolus à la date donnée (null sans date de naissance). */
    public function ageMois(DateTimeImmutable $date): ?int
    {
        if ($this->dateNaissance === null || $this->dateNaissance > $date) {
            return null;
        }
        $ecart = $this->dateNaissance->diff($date);

        return $ecart->y * 12 + $ecart->m;
    }
}
