<?php

namespace App\Domain\Interpretation;

/**
 * Historique pluriannuel (RC-4, EF-M3-05) : série d'un paramètre par campagne pour un élevage et un type de fourrage,
 * variation entre la campagne la plus récente et celle qui la précède dans la série.
 */
final class Historique
{
    /**
     * Série triée par campagne croissante (les codes « 2025-2026 » se trient comme des chaînes).
     *
     * @param  list<PointHistorique>  $points
     * @return list<PointHistorique>
     */
    public function serie(array $points): array
    {
        usort($points, fn (PointHistorique $a, PointHistorique $b) => strcmp($a->campagne, $b->campagne));

        return $points;
    }

    /**
     * @param  list<PointHistorique>  $points
     */
    public function variation(array $points): ?Variation
    {
        $serie = $this->serie($points);

        if (count($serie) < 2) {
            return null;
        }

        [$precedente, $courante] = array_slice($serie, -2);
        $ecart = $courante->valeur - $precedente->valeur;

        return new Variation(
            $courante->campagne,
            $precedente->campagne,
            $courante->valeur,
            $precedente->valeur,
            $ecart,
            $precedente->valeur == 0.0 ? null : 100 * $ecart / abs($precedente->valeur),
        );
    }
}
