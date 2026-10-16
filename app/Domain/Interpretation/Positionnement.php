<?php

namespace App\Domain\Interpretation;

/**
 * Positionnement par quartiles (RC-3, EF-M3-01, EF-M3-02).
 *
 * Classement : faible si v < Q1, normal si Q1 ≤ v ≤ Q3, élevé si v > Q3, pour une population d'au moins n_min lots ;
 * sinon « référence insuffisante (n = …) ». Ordre d'affichage : population départementale (toujours, même
 * insuffisante) ; lithologique si elle est suffisante ; bi-GDS seulement si la départementale est insuffisante ou
 * absente. Le seuil n_min vient de app.parametre_defaut (references.n_min).
 */
final class Positionnement
{
    public function classer(float $valeur, ReferenceQuartiles $reference, int $nMin): Position
    {
        return match (true) {
            ! $reference->suffisante($nMin) => Position::Insuffisante,
            $valeur < $reference->q1 => Position::Faible,
            $valeur > $reference->q3 => Position::Eleve,
            default => Position::Normal,
        };
    }

    /**
     * Populations à afficher, dans l'ordre de RC-3.
     *
     * @return list<ReferenceQuartiles>
     */
    public function populationsAffichees(?ReferenceQuartiles $departementale, ?ReferenceQuartiles $lithologique, ?ReferenceQuartiles $bigds, int $nMin): array
    {
        $populations = [];

        if ($departementale !== null) {
            $populations[] = $departementale;
        }

        if ($lithologique !== null && $lithologique->suffisante($nMin)) {
            $populations[] = $lithologique;
        }

        if ($bigds !== null && ($departementale === null || ! $departementale->suffisante($nMin))) {
            $populations[] = $bigds;
        }

        return $populations;
    }
}
