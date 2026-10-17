<?php

namespace App\Domain\Stocks;

/**
 * Balles, bottes et vrac (RC-7) : S = N × P_brut × MS/100 × (1 − p_stockage) / 1000, en t MS. Pour le vrac, N est le
 * volume en m³ et P_brut la densité en kg brut/m³. Implémentation de référence : calculs/ (T-11).
 */
final class Balles
{
    public function stockUtile(float $nombre, float $poidsBrutKg, float $msPct, float $pertesStockage): float
    {
        return $nombre * $poidsBrutKg * $msPct / 100.0 * (1.0 - $pertesStockage) / 1000.0;
    }

    /** Stock brut avant pertes de stockage, en t MS. */
    public function stockBrut(float $nombre, float $poidsBrutKg, float $msPct): float
    {
        return $this->stockUtile($nombre, $poidsBrutKg, $msPct, 0.0);
    }
}
