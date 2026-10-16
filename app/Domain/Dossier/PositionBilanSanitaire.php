<?php

namespace App\Domain\Dossier;

/**
 * Bilan sanitaire (EF-M1-07, précisions RC-1.5 du 16/10/2026) : taux = 100 × nombre de cas ÷ dénominateur
 * (vêlages) ; sens « haut » : favorable jusqu'à S1 inclus, dégradée jusqu'à S2 inclus, très dégradée au-delà ;
 * sans S1, favorable jusqu'à S2 inclus. Sens « bas » : symétrique. Sans seuil : pas de classe.
 */
final class PositionBilanSanitaire
{
    public static function taux(?int $nbCas, ?int $denominateur): ?float
    {
        return $nbCas === null || $denominateur === null || $denominateur <= 0 ? null : 100 * $nbCas / $denominateur;
    }

    /**
     * @param  list<SeuilBilanSanitaire>  $seuils
     */
    public function seuil(string $indicateurCode, ?string $especeCode, ?string $systeme, array $seuils): ?SeuilBilanSanitaire
    {
        $candidats = array_values(array_filter($seuils, fn (SeuilBilanSanitaire $s) => $s->sAppliqueA($indicateurCode, $especeCode, $systeme)));
        usort($candidats, fn (SeuilBilanSanitaire $a, SeuilBilanSanitaire $b) => [$b->precision(), $a->id] <=> [$a->precision(), $b->id]);

        return $candidats[0] ?? null;
    }

    public function classer(float $valeur, SeuilBilanSanitaire $seuil): ?ClasseBilan
    {
        if ($seuil->s1 === null && $seuil->s2 === null) {
            return null;
        }

        $haut = $seuil->sens === SeuilBilanSanitaire::SENS_HAUT;
        $auDela = fn (float $limite): bool => $haut ? $valeur > $limite : $valeur < $limite;

        return match (true) {
            $seuil->s2 !== null && $auDela($seuil->s2) => ClasseBilan::TresDegradee,
            $seuil->s1 !== null && $auDela($seuil->s1) => ClasseBilan::Degradee,
            default => ClasseBilan::Favorable,
        };
    }
}
