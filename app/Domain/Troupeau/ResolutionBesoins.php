<?php

namespace App\Domain\Troupeau;

/**
 * Besoins d'une phase à partir des lignes de ref.besoin_journalier (RC-9) : par jour, somme des lignes « animal » et
 * des lignes « par kg de lait » multipliées par la production ; par kg MS, intervalle [min ; max]. Une ligne par kg
 * de lait sans production saisie rend le besoin du paramètre incalculable (il n'est pas réduit à l'entretien).
 */
final class ResolutionBesoins
{
    /**
     * @param  list<LigneBesoin>  $lignes
     * @return array{besoins: array<string, Besoin>, manquants: list<string>}
     */
    public function resoudre(array $lignes, ?float $productionLaitKgJ): array
    {
        $besoins = [];
        $manquants = [];

        foreach (collect($lignes)->groupBy('parametre') as $parametre => $parParametre) {
            $parJour = $parParametre->where('mode', Besoin::PAR_JOUR);
            $parKgMs = $parParametre->firstWhere('mode', Besoin::PAR_KG_MS);

            if ($parJour->isNotEmpty()) {
                $laitier = $parJour->where('base', LigneBesoin::BASE_PAR_KG_LAIT);
                if ($laitier->isNotEmpty() && $productionLaitKgJ === null) {
                    $manquants[] = (string) $parametre;

                    continue;
                }

                $besoins[(string) $parametre] = Besoin::parJour(
                    (float) $parJour->where('base', LigneBesoin::BASE_ANIMAL)->sum('valeur') + (float) $laitier->sum('valeur') * (float) $productionLaitKgJ,
                );
            } elseif ($parKgMs !== null && $parKgMs->min !== null) {
                $besoins[(string) $parametre] = Besoin::parKgMs($parKgMs->min, $parKgMs->max, $parKgMs->maximum);
            }
        }

        return ['besoins' => $besoins, 'manquants' => $manquants];
    }
}
