<?php

namespace App\Domain\Referentiels\Edition;

/**
 * Nomenclatures de référence (ref.*) nécessaires au contrôle d'une saisie de référentiel.
 */
final readonly class Nomenclatures
{
    /**
     * @param  list<string>  $parametres  codes de ref.parametre
     * @param  list<string>  $familles  codes de ref.famille_fourrage
     * @param  array<string, string>  $familleParType  type de fourrage → famille
     * @param  list<string>  $especes  codes de ref.espece_animale
     * @param  array<string, string>  $especeParCategorie  catégorie animale → espèce
     * @param  list<int>  $references  identifiants de ref.reference_biblio
     */
    public function __construct(
        public array $parametres,
        public array $familles,
        public array $familleParType,
        public array $especes,
        public array $especeParCategorie,
        public array $references = [],
    ) {}
}
