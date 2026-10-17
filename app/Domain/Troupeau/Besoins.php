<?php

namespace App\Domain\Troupeau;

/**
 * Besoins en matière sèche (RC-8) : voie UGB (RC-8.1), besoin prélevé sur le stock avec les pertes à la distribution
 * (RC-8.3), cohérence des deux voies. Implémentation de référence : calculs/ (T-12).
 */
final class Besoins
{
    /**
     * Voie UGB : UGB = Σ effectif × coefficient ; B = UGB × kg MS/UGB/j × jours / 1000, en t MS.
     *
     * @param  list<array{effectif: float, coefficient: float}>  $lots
     * @return array{ugb: float, besoin_t_ms: float}
     */
    public function voieUgb(array $lots, float $jours, float $kgMsParUgbJour): array
    {
        $ugb = array_sum(array_map(fn (array $l) => $l['effectif'] * $l['coefficient'], $lots));

        return ['ugb' => $ugb, 'besoin_t_ms' => $ugb * $kgMsParUgbJour * $jours / 1000.0];
    }

    /** B_stock = B_ration / (1 − p_distribution) (RC-8.3). */
    public function prelevementStock(float $besoinRationTMs, float $pertesDistribution): float
    {
        return $besoinRationTMs / (1.0 - $pertesDistribution);
    }

    /** Voie ration d'une phase : effectif × kg MS de fourrage par animal et par jour × jours / 1000, en t MS (RC-8.2). */
    public function voieRation(int $effectif, float $kgMsFourrageParJour, int $jours): float
    {
        return $effectif * $kgMsFourrageParJour * $jours / 1000.0;
    }

    /** Écart |B_UGB − B_ration| / B_UGB, null si la voie UGB est nulle (cohérence, RC-8). */
    public function ecartVoies(float $besoinUgb, float $besoinRation): ?float
    {
        return $besoinUgb == 0.0 ? null : abs($besoinUgb - $besoinRation) / $besoinUgb;
    }
}
