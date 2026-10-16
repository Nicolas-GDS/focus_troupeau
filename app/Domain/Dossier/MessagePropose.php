<?php

namespace App\Domain\Dossier;

/**
 * Message hiérarchisé proposé pour l'interprétation de visite (dossier.message) ; un même texte venu de plusieurs
 * lots n'est proposé qu'une fois, avec tous ses objets d'origine.
 */
final readonly class MessagePropose
{
    /**
     * @param  list<string>  $referencesObjet
     */
    public function __construct(
        public int $ordre,
        public int $gravite,
        public string $texte,
        public string $origine,
        public ?string $pilier,
        public array $referencesObjet,
    ) {}
}
