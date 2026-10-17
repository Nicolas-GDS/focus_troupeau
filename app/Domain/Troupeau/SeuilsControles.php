<?php

namespace App\Domain\Troupeau;

/**
 * Seuils des contrôles de la ration (RC-9), lus dans app.parametre_defaut (controle.*).
 */
final readonly class SeuilsControles
{
    public function __construct(
        public float $caPMin,
        public float $caPMax,
        public float $caPMaxPrepaVelage,
        public float $kMgMax,
        public float $bacaMaxFinGestation,
        public float $pdiUflMin,
        public float $pdiUflMax,
    ) {}
}
