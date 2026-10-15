<?php

namespace App\Domain\Interpretation;

/**
 * RC-1 — Ratios dérivés, calculés avant classement si leurs composants sont dosés.
 *
 *   CA_P      = CA / P
 *   K_CAMG_EQ = (K / 39,1) / (CA / 20,04 + MG / 12,15)            en équivalents
 *   PDI_UFL   = PDI / UFL_2018
 *   BACA      = [(NA / 23 + K / 39,1) − (CL / 35,5 + S / 16)] × 1000   en mEq/kg MS, seulement si S et Cl sont dosés
 *
 * Sinon, une « BE partielle (Na + K − Cl) » peut être affichée (non enregistrée comme BACA) si Cl est dosé.
 * Les diviseurs sont des masses équivalentes (masse atomique / valence), constantes physiques citées par RC-1 ;
 * formule BACA : Meschy 2007 [V]. Le facteur 1000 porte sur toute la différence (g/kg → mEq/kg), lecture retenue
 * de la formule de RC-1 dont la mise entre parenthèses est ambiguë.
 * Une valeur censurée n'entre dans aucun ratio : sa valeur exacte est inconnue.
 */
final class Ratios
{
    private const EQ_NA = 23.0;

    private const EQ_K = 39.1;

    private const EQ_CA = 20.04;

    private const EQ_MG = 12.15;

    private const EQ_CL = 35.5;

    private const EQ_S = 16.0;

    /**
     * @param  list<ValeurAnalysee>  $valeurs
     * @return list<ValeurAnalysee> ratios calculés (non censurés), à ajouter aux résultats du bulletin
     */
    public function calculer(array $valeurs): array
    {
        $v = [];
        foreach ($valeurs as $valeur) {
            if ($valeur->censure === null) {
                $v[$valeur->parametreCode] = $valeur->valeur;
            }
        }

        $ratios = [];

        if (isset($v['CA'], $v['P']) && $v['P'] > 0) {
            $ratios[] = new ValeurAnalysee('CA_P', $v['CA'] / $v['P']);
        }

        if (isset($v['K'], $v['CA'], $v['MG'])) {
            $cations = $v['CA'] / self::EQ_CA + $v['MG'] / self::EQ_MG;
            if ($cations > 0) {
                $ratios[] = new ValeurAnalysee('K_CAMG_EQ', ($v['K'] / self::EQ_K) / $cations);
            }
        }

        if (isset($v['PDI'], $v['UFL_2018']) && $v['UFL_2018'] > 0) {
            $ratios[] = new ValeurAnalysee('PDI_UFL', $v['PDI'] / $v['UFL_2018']);
        }

        if (isset($v['NA'], $v['K'], $v['CL'], $v['S'])) {
            $ratios[] = new ValeurAnalysee('BACA', $this->baca($v['NA'], $v['K'], $v['CL'], $v['S']));
        }

        return $ratios;
    }

    /**
     * BE partielle (Na + K − Cl), en mEq/kg MS, quand S n'est pas dosé : affichage seulement.
     *
     * @param  list<ValeurAnalysee>  $valeurs
     */
    public function bePartielle(array $valeurs): ?float
    {
        $v = [];
        foreach ($valeurs as $valeur) {
            if ($valeur->censure === null) {
                $v[$valeur->parametreCode] = $valeur->valeur;
            }
        }

        if (! isset($v['NA'], $v['K'], $v['CL']) || isset($v['S'])) {
            return null;
        }

        return ($v['NA'] / self::EQ_NA + $v['K'] / self::EQ_K - $v['CL'] / self::EQ_CL) * 1000;
    }

    private function baca(float $na, float $k, float $cl, float $s): float
    {
        return (($na / self::EQ_NA + $k / self::EQ_K) - ($cl / self::EQ_CL + $s / self::EQ_S)) * 1000;
    }
}
