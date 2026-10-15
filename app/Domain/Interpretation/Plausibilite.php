<?php

namespace App\Domain\Interpretation;

/**
 * RC-2 — Contrôle de plausibilité à l'import : un bulletin dont une valeur sort de sa plage passe en « anomalie »
 * (EF-M2-01). Les plages sont larges : elles détectent les erreurs d'extraction et les contaminations, pas les
 * fourrages médiocres. Pour la MS, la plage dépend de la famille du fourrage ; une famille sans plage de MS
 * (ex. concentré) n'est pas contrôlée sur ce point.
 */
final class Plausibilite
{
    /**
     * @param  list<ValeurAnalysee>  $valeurs
     * @param  list<PlagePlausibilite>  $plages  plages actives
     * @return list<array{valeur: ValeurAnalysee, plage: PlagePlausibilite}> valeurs hors plage (vide : bulletin plausible)
     */
    public function controler(array $valeurs, string $familleFourrageCode, array $plages): array
    {
        $anomalies = [];

        foreach ($valeurs as $valeur) {
            $plage = $this->plagePour($valeur->parametreCode, $familleFourrageCode, $plages);

            if ($plage !== null && $plage->rejette($valeur)) {
                $anomalies[] = ['valeur' => $valeur, 'plage' => $plage];
            }
        }

        return $anomalies;
    }

    /**
     * La plage propre à la famille l'emporte sur une plage « tout fourrage ».
     *
     * @param  list<PlagePlausibilite>  $plages
     */
    private function plagePour(string $parametreCode, string $familleFourrageCode, array $plages): ?PlagePlausibilite
    {
        $retenue = null;

        foreach ($plages as $plage) {
            if (! $plage->sAppliqueA($parametreCode, $familleFourrageCode)) {
                continue;
            }

            if ($plage->familleFourrageCode !== null) {
                return $plage;
            }

            $retenue ??= $plage;
        }

        return $retenue;
    }
}
