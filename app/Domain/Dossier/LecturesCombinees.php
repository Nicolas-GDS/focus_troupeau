<?php

namespace App\Domain\Dossier;

/**
 * RC-1.5 — Lectures combinées des mesures terrain d'un groupe (visite × stade), évaluées sur la médiane de chaque
 * mesure (décision du 16/10/2026). Quand deux règles s'appliquent et que les conditions de l'une contiennent
 * celles de l'autre, seule la plus précise est proposée. L'application ne pose aucun diagnostic : elle propose
 * des libellés que le technicien retient ou écarte.
 */
final class LecturesCombinees
{
    /**
     * Médiane d'une série (interpolation linéaire entre les deux valeurs centrales).
     *
     * @param  list<float>  $valeurs
     */
    public static function mediane(array $valeurs): ?float
    {
        if ($valeurs === []) {
            return null;
        }

        sort($valeurs);
        $n = count($valeurs);
        $milieu = intdiv($n, 2);

        return $n % 2 === 1 ? $valeurs[$milieu] : ($valeurs[$milieu - 1] + $valeurs[$milieu]) / 2;
    }

    /**
     * @param  array<string, float>  $medianes  médiane par code de mesure
     * @param  array<string, LectureMesure>  $lectures  lecture de chaque médiane par rapport à son repère
     * @param  list<RegleLecture>  $regles
     * @return list<RegleLecture> règles retenues, dans l'ordre de la grille
     */
    public function proposer(array $medianes, array $lectures, array $regles, ?string $especeCode = null, ?string $stade = null): array
    {
        $retenues = array_values(array_filter($regles, fn (RegleLecture $r) => $r->sApplique($medianes, $lectures, $especeCode, $stade)));

        $plusPrecises = array_values(array_filter($retenues, function (RegleLecture $regle) use ($retenues): bool {
            foreach ($retenues as $autre) {
                if ($autre !== $regle && count($autre->cles()) > count($regle->cles()) && array_diff($regle->cles(), $autre->cles()) === []) {
                    return false;
                }
            }

            return true;
        }));

        usort($plusPrecises, fn (RegleLecture $a, RegleLecture $b) => $a->ordre <=> $b->ordre);

        return $plusPrecises;
    }
}
