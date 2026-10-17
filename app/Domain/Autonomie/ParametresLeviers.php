<?php

namespace App\Domain\Autonomie;

/**
 * Paramètres des leviers (RC-13.3) lus dans app.parametre_defaut : taux de substitution des concentrés par famille de
 * base, ingestion de référence par UGB.
 */
final readonly class ParametresLeviers
{
    /**
     * @param  array<string, float>  $substitution  famille de base (FOIN, ENSIL_HERBE, ENSIL_MAIS) → taux
     */
    public function __construct(
        public array $substitution,
        public float $kgMsParUgbJour,
    ) {}
}
