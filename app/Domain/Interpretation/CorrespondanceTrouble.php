<?php

namespace App\Domain\Interpretation;

/**
 * Une alerte du bulletin reliée à un événement sanitaire de l'élevage par un lien de compatibilité.
 */
final readonly class CorrespondanceTrouble
{
    public function __construct(
        public Classement $alerte,
        public EvenementSanitaire $evenement,
        public LienCompatibilite $lien,
    ) {}
}
