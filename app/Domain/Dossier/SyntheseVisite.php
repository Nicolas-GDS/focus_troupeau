<?php

namespace App\Domain\Dossier;

/**
 * Synthèse de visite (EF-M2-05, précisions RC-1.5 du 16/10/2026) : messages proposés à partir des alertes retenues
 * des lots et des classements de l'eau de gravité au moins 1, regroupés par texte, triés par gravité décroissante
 * (à gravité égale : lots, puis eau, puis ordre d'arrivée), au plus messagesMax ; pilier « nutrition » pour les
 * lots, « hydratation » pour l'eau (cinq piliers de la démarche). Niveau proposé de la visite : maximum des niveaux
 * retenus des lots et des gravités de l'eau. Le technicien retient, modifie ou complète.
 */
final class SyntheseVisite
{
    private const PILIERS = [ElementSynthese::ORIGINE_LOT => 'nutrition', ElementSynthese::ORIGINE_EAU => 'hydratation'];

    private const RANG_ORIGINE = [ElementSynthese::ORIGINE_LOT => 0, ElementSynthese::ORIGINE_EAU => 1];

    /**
     * @param  list<ElementSynthese>  $elements
     * @return list<MessagePropose>
     */
    public function messages(array $elements, int $messagesMax): array
    {
        /** @var array<string, array{gravite: int, origine: string, rang: int, references: list<string>}> $groupes */
        $groupes = [];

        foreach ($elements as $rang => $element) {
            if ($element->gravite < 1) {
                continue;
            }

            $cle = $element->origine."\0".$element->texte;
            $groupe = $groupes[$cle] ?? ['gravite' => 0, 'origine' => $element->origine, 'rang' => $rang, 'references' => []];
            $groupe['gravite'] = max($groupe['gravite'], $element->gravite);
            $groupe['references'][] = $element->referenceObjet;
            $groupes[$cle] = $groupe;
        }

        uasort($groupes, fn (array $a, array $b) => [$b['gravite'], self::RANG_ORIGINE[$a['origine']] ?? 9, $a['rang']]
            <=> [$a['gravite'], self::RANG_ORIGINE[$b['origine']] ?? 9, $b['rang']]);

        $messages = [];

        foreach (array_slice($groupes, 0, max(0, $messagesMax), true) as $cle => $groupe) {
            $messages[] = new MessagePropose(
                ordre: count($messages) + 1,
                gravite: $groupe['gravite'],
                texte: substr($cle, strpos($cle, "\0") + 1),
                origine: $groupe['origine'],
                pilier: self::PILIERS[$groupe['origine']] ?? null,
                referencesObjet: $groupe['references'],
            );
        }

        return $messages;
    }

    /**
     * @param  list<int>  $niveauxRetenusLots
     * @param  list<ElementSynthese>  $elementsEau
     */
    public function niveauPropose(array $niveauxRetenusLots, array $elementsEau): int
    {
        $gravitesEau = array_map(fn (ElementSynthese $e) => $e->gravite, $elementsEau);

        return max([0, ...$niveauxRetenusLots, ...$gravitesEau]);
    }
}
