<?php

namespace App\Domain\Dossier;

/**
 * Élément candidat à un message de la synthèse de visite (EF-M2-05) : une alerte retenue d'un lot ou un
 * classement de l'eau, avec sa gravité, son texte (message du seuil) et l'objet d'origine.
 */
final readonly class ElementSynthese
{
    public const ORIGINE_LOT = 'lot';

    public const ORIGINE_EAU = 'eau';

    public function __construct(
        public string $origine,
        public int $gravite,
        public string $texte,
        public string $referenceObjet,
    ) {}
}
