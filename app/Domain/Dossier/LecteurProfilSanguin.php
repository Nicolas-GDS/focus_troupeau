<?php

namespace App\Domain\Dossier;

/**
 * RC-1.5 — Lecture d'un profil sanguin par comparaison au repère de l'élément : carence sévère à une fraction de la
 * borne basse ou en dessous (profil.part_carence_severe, 0,5 ; décision du 16/10/2026), carence sous la borne
 * basse, normal partie basse dans la fraction inférieure de l'intervalle (profil.part_normal_bas, 0,25), normal,
 * excès au-dessus de la borne haute. Les fractions viennent des référentiels.
 */
final class LecteurProfilSanguin
{
    public function __construct(
        private readonly float $partCarenceSevere,
        private readonly float $partNormalBas,
    ) {}

    /**
     * @param  list<RepereProfilSanguin>  $reperes
     */
    public function repere(string $element, string $matrice, ?string $especeCode, array $reperes): ?RepereProfilSanguin
    {
        $candidats = array_values(array_filter($reperes, fn (RepereProfilSanguin $r) => $r->sAppliqueA($element, $matrice, $especeCode)));
        usort($candidats, fn (RepereProfilSanguin $a, RepereProfilSanguin $b) => [$b->especeCode !== null, $a->id] <=> [$a->especeCode !== null, $b->id]);

        return $candidats[0] ?? null;
    }

    public function lire(float $valeur, RepereProfilSanguin $repere): LectureProfil
    {
        $basse = $repere->borneBasse;
        $haute = $repere->borneHaute;

        return match (true) {
            $basse !== null && $valeur <= $basse * $this->partCarenceSevere => LectureProfil::CarenceSevere,
            $basse !== null && $valeur < $basse => LectureProfil::Carence,
            $haute !== null && $valeur > $haute => LectureProfil::Exces,
            $basse !== null && $haute !== null && $valeur < $basse + ($haute - $basse) * $this->partNormalBas => LectureProfil::NormalBas,
            default => LectureProfil::Normal,
        };
    }
}
