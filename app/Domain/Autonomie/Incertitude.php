<?php

namespace App\Domain\Autonomie;

/**
 * Incertitude affichée (RC-14) : erreurs relatives d'un lot composées quadratiquement ; erreurs absolues des lots
 * composées quadratiquement pour le total. Implémentation de référence : calculs/ (T-16).
 */
final class Incertitude
{
    /** @param  list<float>  $erreursRelatives */
    public function lot(array $erreursRelatives): float
    {
        return sqrt(array_sum(array_map(fn (float $e) => $e * $e, $erreursRelatives)));
    }

    /** Erreur sur la MS exprimée en points, rapportée en relatif. */
    public function ms(float $points, float $msPct): float
    {
        return $msPct > 0 ? $points / $msPct : 0.0;
    }

    /** @param  list<LotProjection>  $lots */
    public function totale(array $lots): float
    {
        $total = array_sum(array_map(fn (LotProjection $l) => $l->stockUtile, $lots));

        return $total <= 0 ? 0.0 : sqrt(array_sum(array_map(fn (LotProjection $l) => ($l->stockUtile * $l->incertitude) ** 2, $lots))) / $total;
    }

    /**
     * Lots bornés bas (S × (1 − e)) ou haut (S × (1 + e)), pour la fourchette de la date de rupture.
     *
     * @param  list<LotProjection>  $lots
     * @return list<LotProjection>
     */
    public function bornes(array $lots, int $sens): array
    {
        return array_map(fn (LotProjection $l) => $l->avecStock($l->stockUtile * (1 + $sens * $l->incertitude)), $lots);
    }
}
