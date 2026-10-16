<?php

namespace App\Domain\Dossier;

use App\Domain\Interpretation\Classement;
use App\Domain\Interpretation\Classeur;
use App\Domain\Interpretation\ContexteLot;
use App\Domain\Interpretation\Seuil;
use App\Domain\Interpretation\ValeurAnalysee;

/**
 * Lecture d'une analyse d'eau (EF-M1-04) par la grille eau d'abreuvement : seuils de nature « eau » de
 * ref.seuil_interpretation, classés par RC-1 (Classeur). Les seuils d'eau ne visent aucun fourrage ni aucune
 * catégorie animale ; un paramètre sans seuil reste sans classe (« repère non disponible »).
 */
final class LecteurEau
{
    public const NATURE = 'eau';

    public function __construct(private readonly Classeur $classeur = new Classeur) {}

    /**
     * @param  list<ValeurAnalysee>  $valeurs
     * @param  list<Seuil>  $seuils  seuils actifs de la grille, toutes natures
     * @return list<Classement>
     */
    public function classer(array $valeurs, array $seuils): array
    {
        $seuilsEau = array_values(array_filter($seuils, fn (Seuil $s) => $s->nature === self::NATURE));

        return $this->classeur->classerTout($valeurs, ContexteLot::horsFourrage(), $seuilsEau);
    }
}
