<?php

namespace App\Domain\Interpretation;

use DateTimeImmutable;

/**
 * Trouble observé dans le troupeau (fourrage.evenement_sanitaire) : son type est un code de ref.trouble_sanitaire.
 */
final readonly class EvenementSanitaire
{
    public function __construct(
        public string $typeEvenement,
        public string $especeCode,
        public DateTimeImmutable $date,
        public ?string $categorieCode = null,
    ) {}
}
