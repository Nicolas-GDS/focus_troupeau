<?php

namespace App\Domain\Dossier;

use App\Domain\Interpretation\EvenementSanitaire;
use DateTimeImmutable;

/**
 * Étape « croiser » (EF-M2-05, précisions RC-1.5 du 16/10/2026) : un trouble de la grille est présent si l'un de
 * ses indicateurs du bilan sanitaire est dégradé ou très dégradé, ou si un événement sanitaire correspondant est
 * enregistré dans la fenêtre précédant la date de référence ; la force de chaque famille de cause est la somme
 * des forces sur les troubles présents. Les familles sont proposées par force décroissante (à force égale, dans
 * l'ordre de la grille) ; le technicien rédige les causes communes.
 */
final class Croisement
{
    /**
     * @param  array<string, ClasseBilan>  $classesIndicateurs  code d'indicateur => classe
     * @param  list<EvenementSanitaire>  $evenements
     * @param  list<TroubleCroisement>  $troubles
     * @return list<string> codes des troubles présents, dans l'ordre de la grille
     */
    public function troublesPresents(
        array $classesIndicateurs,
        array $evenements,
        array $troubles,
        DateTimeImmutable $dateReference,
        int $fenetreMois,
    ): array {
        $debut = $dateReference->modify("-{$fenetreMois} months");
        $types = [];

        foreach ($evenements as $evenement) {
            if ($evenement->date >= $debut && $evenement->date <= $dateReference) {
                $types[$evenement->typeEvenement] = true;
            }
        }

        usort($troubles, fn (TroubleCroisement $a, TroubleCroisement $b) => $a->ordre <=> $b->ordre);
        $presents = [];

        foreach ($troubles as $trouble) {
            $parIndicateur = array_filter(
                $trouble->indicateursSanitaires,
                fn (string $code) => ($classesIndicateurs[$code] ?? null)?->estDegradee() === true,
            ) !== [];
            $parEvenement = array_filter($trouble->troublesSanitaires, fn (string $code) => isset($types[$code])) !== [];

            if ($parIndicateur || $parEvenement) {
                $presents[] = $trouble->code;
            }
        }

        return $presents;
    }

    /**
     * @param  list<string>  $troublesPresents
     * @param  list<LienTroubleCause>  $liens
     * @return list<FamilleCauseProposee> familles de force non nulle
     */
    public function familles(array $troublesPresents, array $liens): array
    {
        $forces = array_fill_keys(LienTroubleCause::FAMILLES, 0);
        $detail = array_fill_keys(LienTroubleCause::FAMILLES, []);

        foreach ($liens as $lien) {
            if ($lien->force > 0 && in_array($lien->troubleCode, $troublesPresents, true) && isset($forces[$lien->familleCause])) {
                $forces[$lien->familleCause] += $lien->force;
                $detail[$lien->familleCause][$lien->troubleCode] = $lien->force;
            }
        }

        $rang = array_flip(LienTroubleCause::FAMILLES);
        $familles = array_keys(array_filter($forces, fn (int $f) => $f > 0));
        usort($familles, fn (string $a, string $b) => [$forces[$b], $rang[$a]] <=> [$forces[$a], $rang[$b]]);

        return array_map(fn (string $f) => new FamilleCauseProposee($f, $forces[$f], $detail[$f]), $familles);
    }
}
