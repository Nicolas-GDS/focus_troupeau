<?php

namespace App\Domain\Interpretation;

/**
 * Résultat du classement d'une valeur (RC-1) : le seuil retenu et la valeur classée.
 * Une gravité ≥ 1 produit une alerte automatique ; une gravité 0 ne sert qu'à l'affichage de la classe.
 */
final readonly class Classement
{
    public function __construct(
        public ValeurAnalysee $valeur,
        public Seuil $seuil,
    ) {}

    public function produitUneAlerte(): bool
    {
        return $this->seuil->gravite >= 1;
    }
}
