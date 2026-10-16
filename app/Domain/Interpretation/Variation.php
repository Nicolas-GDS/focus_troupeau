<?php

namespace App\Domain\Interpretation;

/**
 * Variation entre la campagne courante et la précédente (RC-4) : en valeur et en pourcentage, sans classe (une hausse
 * n'est ni bonne ni mauvaise). Le pourcentage est absent quand la valeur précédente est nulle.
 */
final readonly class Variation
{
    public function __construct(
        public string $campagneCourante,
        public string $campagnePrecedente,
        public float $valeurCourante,
        public float $valeurPrecedente,
        public float $ecart,
        public ?float $pourcentage,
    ) {}
}
