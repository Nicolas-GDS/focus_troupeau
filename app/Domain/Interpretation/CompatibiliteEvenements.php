<?php

namespace App\Domain\Interpretation;

use DateTimeImmutable;

/**
 * RC-1.4 — Correspondance entre les alertes d'un bulletin et les événements sanitaires de l'élevage (chapitre 11).
 *
 * Un lien de compatibilité relie une alerte à un événement si : le paramètre est celui de l'alerte ; le sens du lien
 * correspond au sens du seuil classant ; le trouble est le type de l'événement ; l'espèce (et, si connue, la catégorie)
 * de l'événement est concernée ; l'événement est survenu dans la fenêtre de référence précédant la date de référence.
 *
 * Toutes les forces sont rendues (la lecture « causes communes » de l'interprétation de visite les utilise) ;
 * le rehaussement du niveau ne retient que les liens dont la force atteint le minimum paramétré.
 */
final class CompatibiliteEvenements
{
    /**
     * @param  list<Classement>  $alertes
     * @param  list<EvenementSanitaire>  $evenements
     * @param  list<LienCompatibilite>  $liens
     * @return list<CorrespondanceTrouble>
     */
    public function correspondances(
        array $alertes,
        array $evenements,
        array $liens,
        DateTimeImmutable $dateReference,
        int $fenetreMois,
    ): array {
        $debut = $dateReference->modify("-{$fenetreMois} months");
        $recents = array_values(array_filter(
            $evenements,
            fn (EvenementSanitaire $e) => $e->date >= $debut && $e->date <= $dateReference,
        ));

        $correspondances = [];

        foreach ($alertes as $alerte) {
            foreach ($liens as $lien) {
                if ($lien->parametreCode !== $alerte->valeur->parametreCode || ! $lien->correspondAuSens($alerte->seuil->sens)) {
                    continue;
                }

                foreach ($recents as $evenement) {
                    if ($lien->troubleCode === $evenement->typeEvenement && $lien->concerne($evenement)) {
                        $correspondances[] = new CorrespondanceTrouble($alerte, $evenement, $lien);
                    }
                }
            }
        }

        return $correspondances;
    }

    /**
     * Paramètres en alerte reliés à un événement par un lien d'au moins la force minimale : entrée du rehaussement.
     *
     * @param  list<CorrespondanceTrouble>  $correspondances
     * @return list<string>
     */
    public function parametresPourRehaussement(array $correspondances, int $forceMin): array
    {
        $parametres = [];

        foreach ($correspondances as $c) {
            if ($c->lien->force >= $forceMin) {
                $parametres[$c->alerte->valeur->parametreCode] = true;
            }
        }

        return array_map('strval', array_keys($parametres));
    }
}
