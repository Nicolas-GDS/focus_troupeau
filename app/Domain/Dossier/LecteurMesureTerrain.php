<?php

namespace App\Domain\Dossier;

/**
 * RC-1.5 — Lecture d'une mesure terrain : « bas » sous la borne basse, « haut » au-dessus de la borne haute,
 * « normal » entre les deux, bornes incluses. Le repère retenu est le plus précis qui s'applique à la mesure, à
 * l'espèce et au stade (espèce × stade > stade > espèce > aucun) ; sans repère, pas de lecture.
 */
final class LecteurMesureTerrain
{
    /**
     * @param  list<RepereMesureTerrain>  $reperes
     */
    public function repere(string $mesureCode, ?string $especeCode, ?string $stade, array $reperes): ?RepereMesureTerrain
    {
        $candidats = array_values(array_filter($reperes, fn (RepereMesureTerrain $r) => $r->sAppliqueA($mesureCode, $especeCode, $stade)));

        if ($candidats === []) {
            return null;
        }

        usort($candidats, fn (RepereMesureTerrain $a, RepereMesureTerrain $b) => [$b->precision(), $a->id] <=> [$a->precision(), $b->id]);

        return $candidats[0];
    }

    public function lire(float $valeur, RepereMesureTerrain $repere): LectureMesure
    {
        return match (true) {
            $repere->borneBasse !== null && $valeur < $repere->borneBasse => LectureMesure::Bas,
            $repere->borneHaute !== null && $valeur > $repere->borneHaute => LectureMesure::Haut,
            default => LectureMesure::Normal,
        };
    }
}
