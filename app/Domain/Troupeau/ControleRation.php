<?php

namespace App\Domain\Troupeau;

/**
 * Résultat d'un contrôle de la ration (RC-9) : rapport ou concentration calculé, gravité (null : dans le repère ;
 * 0 information, 1 vigilance, 2 alerte) et constat en clair.
 */
final readonly class ControleRation
{
    public function __construct(
        public string $code,
        public string $libelle,
        public float $valeur,
        public ?int $gravite,
        public string $constat,
    ) {}
}
