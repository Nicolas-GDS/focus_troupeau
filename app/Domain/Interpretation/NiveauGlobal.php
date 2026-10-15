<?php

namespace App\Domain\Interpretation;

/**
 * RC-1.4 — Niveau global proposé : la gravité maximale des alertes retenues, avec deux rehaussements proposés
 * (jamais imposés : le technicien confirme ou modifie, les deux niveaux sont conservés) :
 *
 * 1. un événement sanitaire compatible avec un paramètre en alerte, enregistré pour l'élevage dans la fenêtre
 *    de référence (paramètre interpretation.fenetre_evenements_mois, 12 mois selon RC-1.4 ; table
 *    ref.compatibilite_parametre_trouble), fait passer un niveau 1 à 2 ;
 * 2. un lot destiné à des ovins dont le cuivre atteint le seuil réglementaire ovin de la grille passe au niveau 3
 *    (EFSA 2016 [V]) ; le seuil est lu dans la grille, jamais codé ici.
 */
final class NiveauGlobal
{
    public const ESPECE_OVINE = 'OVI';

    /**
     * @param  list<int>  $gravitesRetenues  gravités des alertes retenues
     * @param  list<string>  $parametresEnAlerte  paramètres des alertes retenues de gravité ≥ 1
     * @param  list<string>  $parametresCompatiblesEvenements  paramètres compatibles avec un événement sanitaire récent
     * @param  float|null  $seuilCuivreOvins  borne basse du seuil cuivre propre aux ovins dans la grille
     */
    public function proposer(
        array $gravitesRetenues,
        array $parametresEnAlerte,
        array $parametresCompatiblesEvenements,
        ContexteLot $lot,
        ?float $cuivre,
        ?float $seuilCuivreOvins,
    ): NiveauPropose {
        $initial = $gravitesRetenues === [] ? 0 : max($gravitesRetenues);
        $niveau = $initial;
        $motifs = [];

        $evenements = array_values(array_intersect($parametresEnAlerte, $parametresCompatiblesEvenements));
        if ($niveau === 1 && $evenements !== []) {
            $niveau = 2;
            $motifs[] = 'Événement sanitaire compatible enregistré dans la fenêtre de référence (paramètres : '.implode(', ', $evenements).')';
        }

        if ($lot->especeCode === self::ESPECE_OVINE && $cuivre !== null && $seuilCuivreOvins !== null
            && $cuivre >= $seuilCuivreOvins && $niveau < 3) {
            $niveau = 3;
            $motifs[] = 'Lot destiné à des ovins : cuivre au-delà du maximum réglementaire ovin';
        }

        return new NiveauPropose($niveau, $initial, $motifs);
    }
}
