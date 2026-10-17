<?php

namespace App\Domain\Troupeau;

/**
 * Couverture d'un élément pour une ration (RC-9) : apport par animal et par jour, concentration par kg MS, ratio au
 * besoin, classe, gravité, besoin résiduel. Si un composant a des teneurs inconnues, la couverture n'est pas calculée
 * (complet = false ; ratio, classe et résiduel nuls).
 */
final readonly class ResultatCouverture
{
    public function __construct(
        public float $apport,
        public float $concentration,
        public float $msi,
        public bool $complet,
        public ?float $ratio = null,
        public ?ClasseCouverture $classe = null,
        public ?int $gravite = null,
        public ?float $residuel = null,
    ) {}
}
