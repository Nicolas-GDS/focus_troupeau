<?php

namespace App\Domain\Stocks;

/**
 * Silos (RC-5, document 06 § 2.1, Idele [T]) : volume, correction de tassement, densité par équation, stock utile.
 * Implémentation de référence : calculs/focus_troupeau_calculs.py (T-10).
 */
final class Silo
{
    /** Au-delà de cette MS (%), les équations herbe donnent des densités invraisemblables (RC-5). */
    public const MS_MAX_HERBE = 36.0;

    /** Amidon par défaut (% MS) quand ni l'amidon ni le pourcentage de grain ne sont connus (RC-5 [T]). */
    public const AMIDON_DEFAUT = 28.0;

    /** V = (L1 + 0,5 × L2) × l × h, en m³. */
    public function volume(float $l1, float $l2, float $largeur, float $hauteur): float
    {
        return ($l1 + 0.5 * $l2) * $largeur * $hauteur;
    }

    /**
     * Coefficient de réduction du volume d'un silo fermé depuis moins de trois semaines (RC-5.3, Idele [T],
     * interpolation [C]) : 0,05 à 10 jours, 0,03 à 20 jours, 0 à partir de 21 jours ; doublé si MS < 22 %.
     */
    public function correctionTassement(?int $joursDepuisFermeture, float $msPct): float
    {
        if ($joursDepuisFermeture === null || $joursDepuisFermeture >= 21) {
            return 0.0;
        }

        $j = max(0, $joursDepuisFermeture);
        // 0 j → 0,06 (extrapolation), 10 j → 0,05, 20 j → 0,03 ; le cas 20 < j < 21 n'existe pas pour des jours entiers
        $c = $j <= 10 ? 0.06 - 0.001 * $j : 0.05 - 0.002 * ($j - 10);

        return $msPct < 22 ? 2.0 * $c : $c;
    }

    /** Amidon (% MS) : 28 par défaut ; si le pourcentage de grain G est connu et > 20 : 1,134 × G − 22,5 [T]. */
    public function amidonDepuisGrain(?float $grainPct): float
    {
        return $grainPct === null || $grainPct <= 20 ? self::AMIDON_DEFAUT : 1.134 * $grainPct - 22.5;
    }

    /**
     * Densité en kg MS/m³ (Idele 2020 [T]) ; sorgho = maïs × 1,20.
     *
     * @throws DensiteHorsDomaine équations herbe au-delà de 36 % de MS
     */
    public function densite(EquationDensite $equation, float $hauteur, float $msPct, float $amidonPct = self::AMIDON_DEFAUT): float
    {
        if ($equation->herbe() && $msPct > self::MS_MAX_HERBE) {
            throw new DensiteHorsDomaine('Les équations herbe ne s\'appliquent pas au-delà de 36 % de MS : saisir une densité mesurée.');
        }

        [$h, $ms, $a] = [$hauteur, $msPct, $amidonPct];
        $densite = match ($equation) {
            EquationDensite::MaisCouloir, EquationDensite::SorghoCouloir => (929 - 17.5 * $ms + 7.9 * $a + 44 * $h) * $ms / 100,
            EquationDensite::MaisTaupiniere, EquationDensite::SorghoTaupiniere => (874 - 14.5 * $ms + 4.2 * $a + 48 * $h) * $ms / 100,
            EquationDensite::HerbeBrinsCourts => 30 * $h + 2 * $ms + 60,
            EquationDensite::HerbeBrinsLongs => 27 * $h + 1.8 * $ms + 54,
            EquationDensite::HerbeCouloir => 16 * $h + 5 * $ms + 34,
            EquationDensite::HerbeTaupiniere => 7 * $h + 3.7 * $ms + 74,
        };

        return $equation->sorgho() ? $densite * 1.20 : $densite;
    }

    /** S = V × D / 1000 × (1 − p_conservation), en t MS. */
    public function stockUtile(float $volumeM3, float $densiteKgMsM3, float $pertesConservation): float
    {
        return $volumeM3 * $densiteKgMsM3 / 1000.0 * (1.0 - $pertesConservation);
    }
}
