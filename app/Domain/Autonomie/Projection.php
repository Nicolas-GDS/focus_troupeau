<?php

namespace App\Domain\Autonomie;

use DateInterval;
use DateTimeImmutable;

/**
 * Projection journalière des stocks et date de rupture (RC-11), stock de sécurité (RC-12). Implémentation de
 * référence : calculs/focus_troupeau_calculs.py, projeter() (T-14).
 *
 * Chaque jour : consommations des phases actives (effectif × kg MS / (1 − pertes à la distribution)), d'un lot précis
 * ou d'une famille selon l'ordre d'allocation ; mouvements du jour (achat +, vente −, ajustement ±, re-comptage :=
 * valeur, l'écart projeté − recompté étant conservé). Une famille épuisée impute le reste à son dernier lot (stock
 * négatif = rupture). Agrégation par mois civil et par famille.
 */
final class Projection
{
    private const EPSILON = 1e-9;

    /**
     * @param  list<LotProjection>  $lots
     * @param  list<MouvementProjection>  $mouvements
     * @param  list<ConsommationPhase>  $phases
     */
    public function projeter(array $lots, array $mouvements, array $phases, DateTimeImmutable $debut, DateTimeImmutable $fin, float $margeSecurite): ResultatProjection
    {
        $stock = [];
        $familleDe = [];
        foreach ($lots as $l) {
            $stock[$l->id] = $l->stockUtile;
            $familleDe[$l->id] = $l->famille;
        }
        $ordre = $this->ordreParFamille($lots);
        $ruptureLot = array_fill_keys(array_keys($stock), null);
        $epuiseLot = array_fill_keys(array_keys($stock), null);
        $ruptureFamille = array_fill_keys(array_keys($ordre), null);
        $mouvementsParJour = [];
        foreach ($mouvements as $m) {
            $mouvementsParJour[$m->date->format('Y-m-d')][] = $m;
        }
        $un = new DateInterval('P1D');

        // Premier passage : besoins totaux et par famille (sécurité).
        $besoinTotal = 0.0;
        $besoinFamille = [];
        for ($jour = $debut; $jour <= $fin; $jour = $jour->add($un)) {
            foreach ($this->prelevements($phases, $jour) as $cle => $q) {
                $famille = str_starts_with($cle, 'FAM:') ? substr($cle, 4) : ($familleDe[$cle] ?? $cle);
                $besoinTotal += $q;
                $besoinFamille[$famille] = ($besoinFamille[$famille] ?? 0.0) + $q;
            }
        }
        $securite = $margeSecurite * $besoinTotal;
        $securiteFamille = array_map(fn (float $b) => $margeSecurite * $b, $besoinFamille);
        $entame = null;
        $mensuel = [];
        $ecarts = [];

        for ($jour = $debut; $jour <= $fin; $jour = $jour->add($un)) {
            $mois = $jour->format('Y-m');
            if (! isset($mensuel[$mois])) {
                $mensuel[$mois] = ['mois' => $mois, 'stock_debut' => array_sum($stock), 'conso' => 0.0, 'mvts' => 0.0, 'stock_fin' => 0.0, 'familles' => []];
                foreach ($ordre as $famille => $ids) {
                    $mensuel[$mois]['familles'][$famille] = ['stock_debut' => $this->somme($stock, $ids), 'conso' => 0.0, 'mvts' => 0.0, 'stock_fin' => 0.0];
                }
            }

            foreach ($mouvementsParJour[$jour->format('Y-m-d')] ?? [] as $mv) {
                if (! isset($stock[$mv->lotId])) {
                    continue;
                }
                if ($mv->type === MouvementProjection::RECOMPTAGE) {
                    $ecarts[] = ['lot' => $mv->lotId, 'date' => $jour, 'projete' => $stock[$mv->lotId], 'recompte' => $mv->quantite, 'ecart' => $stock[$mv->lotId] - $mv->quantite];
                    $delta = $mv->quantite - $stock[$mv->lotId];
                } else {
                    $delta = $mv->quantite;
                }
                $stock[$mv->lotId] += $delta;
                $mensuel[$mois]['mvts'] += $delta;
                $mensuel[$mois]['familles'][$familleDe[$mv->lotId]]['mvts'] += $delta;
            }

            foreach ($this->prelevements($phases, $jour) as $cle => $q) {
                if (str_starts_with($cle, 'FAM:')) {
                    $famille = substr($cle, 4);
                    $reste = $q;
                    foreach ($ordre[$famille] ?? [] as $id) {
                        if ($reste <= 0) {
                            break;
                        }
                        $pris = min(max(0.0, $stock[$id]), $reste);
                        $stock[$id] -= $pris;
                        $reste -= $pris;
                    }
                    if ($reste > 0) {
                        $dernier = isset($ordre[$famille]) ? $ordre[$famille][array_key_last($ordre[$famille])] : null;
                        if ($dernier !== null) {
                            $stock[$dernier] -= $reste;
                        }
                        $ruptureFamille[$famille] ??= $jour;
                    }
                } else {
                    $famille = $familleDe[$cle] ?? null;
                    if ($famille === null) {
                        continue;
                    }
                    $stock[$cle] -= $q;
                }
                $mensuel[$mois]['conso'] += $q;
                if (isset($mensuel[$mois]['familles'][$famille])) {
                    $mensuel[$mois]['familles'][$famille]['conso'] += $q;
                }
            }

            foreach ($stock as $id => $s) {
                if ($s < -self::EPSILON) {
                    $ruptureLot[$id] ??= $jour;
                }
                if ($s <= self::EPSILON) {
                    $epuiseLot[$id] ??= $jour;
                }
            }
            foreach ($ordre as $famille => $ids) {
                $total = $this->somme($stock, $ids);
                if ($total < -self::EPSILON) {
                    $ruptureFamille[$famille] ??= $jour;
                }
                if ($entame === null && ($besoinFamille[$famille] ?? 0.0) > 0 && $total < ($securiteFamille[$famille] ?? 0.0) - self::EPSILON) {
                    $entame = $jour;
                }
            }

            $mensuel[$mois]['stock_fin'] = array_sum($stock);
            foreach ($ordre as $famille => $ids) {
                $mensuel[$mois]['familles'][$famille]['stock_fin'] = $this->somme($stock, $ids);
            }
        }

        $stockUtile = array_sum(array_map(fn (LotProjection $l) => $l->stockUtile, $lots));
        $jours = (int) $debut->diff($fin)->days + 1;
        $besoinJour = $jours > 0 ? $besoinTotal / $jours : 0.0;
        $ruptures = array_filter($ruptureFamille);

        return new ResultatProjection(
            $besoinTotal, $stockUtile, $securite, $stockUtile - $besoinTotal - $securite,
            $besoinTotal > 0 ? $stockUtile / $besoinTotal : 0.0,
            $besoinJour > 0 ? (int) floor($stockUtile / $besoinJour) : 0, $jours,
            $ruptures === [] ? null : min($ruptures), $entame, $besoinFamille,
            $ruptureLot, $epuiseLot, $ruptureFamille, array_values($mensuel), $ecarts,
        );
    }

    /**
     * Ordre de consommation des lots de chaque famille : priorité, date de récolte croissante (sans date : en dernier),
     * lots non analysés avant analysés, identifiant.
     *
     * @param  list<LotProjection>  $lots
     * @return array<string, list<string>>
     */
    public function ordreParFamille(array $lots): array
    {
        usort($lots, fn (LotProjection $a, LotProjection $b) => [$a->priorite, $a->dateRecolte?->format('Y-m-d') ?? '9999-12-31', $a->analyse, $a->id]
            <=> [$b->priorite, $b->dateRecolte?->format('Y-m-d') ?? '9999-12-31', $b->analyse, $b->id]);
        $ordre = [];
        foreach ($lots as $l) {
            $ordre[$l->famille][] = $l->id;
        }

        return $ordre;
    }

    /**
     * Prélèvements du jour en t MS, par lot précis (clé : identifiant du lot) ou par famille (clé « FAM:code »).
     *
     * @param  list<ConsommationPhase>  $phases
     * @return array<string, float>
     */
    private function prelevements(array $phases, DateTimeImmutable $jour): array
    {
        $prelevements = [];
        foreach ($phases as $phase) {
            if (! $phase->active($jour)) {
                continue;
            }
            foreach ($phase->composants as $c) {
                $q = $phase->effectif * $c['kg_ms'] / (1.0 - $phase->pertesDistribution) / 1000.0;
                $cle = $c['lot'] !== null ? $c['lot'] : 'FAM:'.$c['famille'];
                $prelevements[$cle] = ($prelevements[$cle] ?? 0.0) + $q;
            }
        }

        return $prelevements;
    }

    /**
     * @param  array<string, float>  $stock
     * @param  list<string>  $ids
     */
    private function somme(array $stock, array $ids): float
    {
        return array_sum(array_map(fn (string $id) => $stock[$id], $ids));
    }
}
