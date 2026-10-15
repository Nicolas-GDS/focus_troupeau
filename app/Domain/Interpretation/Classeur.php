<?php

namespace App\Domain\Interpretation;

/**
 * RC-1 — Classement d'une valeur par la grille d'interprétation.
 *
 * La valeur est classée par le seuil actif qui la contient et s'applique au lot, du plus précis au plus général :
 * précision côté fourrage d'abord (type > famille > tous fourrages), puis côté animal (catégorie > espèce > aucune).
 * Cet ordre reproduit la liste de RC-1 — (type × espèce × catégorie) > (type × espèce) > (type) >
 * (famille × espèce × catégorie) > (famille) > (tous × espèce × catégorie) > (tous) — et classe les combinaisons
 * qu'elle ne cite pas (ex. tous fourrages × espèce). À précision égale, la gravité la plus forte l'emporte
 * (décision du 15/10/2026), puis l'identifiant le plus petit, pour un résultat toujours déterministe.
 *
 * Un résultat censuré « < » est classé sur sa limite de quantification ; la censure est reportée dans l'alerte.
 */
final class Classeur
{
    /**
     * @param  list<Seuil>  $seuils  seuils actifs de la grille (tous paramètres confondus)
     */
    public function classer(ValeurAnalysee $valeur, ContexteLot $lot, array $seuils): ?Classement
    {
        $candidats = array_values(array_filter(
            $seuils,
            fn (Seuil $seuil) => $seuil->parametreCode === $valeur->parametreCode
                && $seuil->sAppliqueA($lot)
                && $seuil->contient($valeur->valeur),
        ));

        if ($candidats === []) {
            return null;
        }

        usort($candidats, fn (Seuil $a, Seuil $b) => [$b->precisionFourrage(), $b->precisionAnimal(), $b->gravite, $a->id]
            <=> [$a->precisionFourrage(), $a->precisionAnimal(), $a->gravite, $b->id]);

        return new Classement($valeur, $candidats[0]);
    }

    /**
     * Classe toutes les valeurs du bulletin ; ne renvoie que les valeurs classées.
     *
     * @param  list<ValeurAnalysee>  $valeurs
     * @param  list<Seuil>  $seuils
     * @return list<Classement>
     */
    public function classerTout(array $valeurs, ContexteLot $lot, array $seuils): array
    {
        $classements = [];

        foreach ($valeurs as $valeur) {
            $classement = $this->classer($valeur, $lot, $seuils);

            if ($classement !== null) {
                $classements[] = $classement;
            }
        }

        return $classements;
    }
}
