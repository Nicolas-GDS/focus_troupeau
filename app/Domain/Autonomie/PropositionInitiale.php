<?php

namespace App\Domain\Autonomie;

/**
 * Diagnostic et proposition initiale (RC-13.1, RC-13.2 étapes 1 et 2) : déficit et excédent par famille ;
 * réaffectation d'une famille excédentaire vers une famille déficitaire substituable, puis achat du déficit restant.
 * Implémentation de référence : calculs/, proposition_initiale() (T-15). Les étapes 3 et 4 (correction
 * nutritionnelle, minéraux) expriment un besoin résiduel et relèvent du service (décision du 17/10/2026).
 */
final class PropositionInitiale
{
    private const EPSILON = 1e-9;

    /**
     * @param  array<string, float>  $besoins  B_f par famille
     * @param  array<string, float>  $securites  sécurité allouée par famille
     * @param  array<string, float>  $stocks  S_f par famille
     * @return array{deficits: array<string, float>, excedents: array<string, float>}
     */
    public function diagnostic(array $besoins, array $securites, array $stocks): array
    {
        $deficits = [];
        $excedents = [];
        foreach (array_unique([...array_keys($besoins), ...array_keys($stocks)]) as $f) {
            $solde = ($stocks[$f] ?? 0.0) - ($besoins[$f] ?? 0.0) - ($securites[$f] ?? 0.0);
            if ($solde < -self::EPSILON) {
                $deficits[$f] = -$solde;
            } elseif ($solde > self::EPSILON) {
                $excedents[$f] = $solde;
            }
        }

        return ['deficits' => $deficits, 'excedents' => $excedents];
    }

    /**
     * @param  array<string, float>  $deficits
     * @param  array<string, float>  $excedents
     * @param  list<array{source: string, cible: string, ratio: float}>  $substituabilites
     * @param  array<string, float>  $prix  €/t MS par famille
     * @return list<array{levier: string, source: string|null, famille: string, quantite: float, gain: float, cout: float}>
     */
    public function proposer(array $deficits, array $excedents, array $substituabilites, array $prix): array
    {
        $lignes = [];
        foreach ($substituabilites as $s) {
            if (($deficits[$s['cible']] ?? 0.0) > 0 && ($excedents[$s['source']] ?? 0.0) > 0) {
                $q = min($excedents[$s['source']], $deficits[$s['cible']] / $s['ratio']);
                $lignes[] = ['levier' => Levier::REAFFECTATION, 'source' => $s['source'], 'famille' => $s['cible'], 'quantite' => $q, 'gain' => $q * $s['ratio'], 'cout' => 0.0];
                $excedents[$s['source']] -= $q;
                $deficits[$s['cible']] -= $q * $s['ratio'];
            }
        }

        foreach ($deficits as $famille => $q) {
            if ($q > self::EPSILON) {
                $lignes[] = ['levier' => Levier::ACHAT, 'source' => null, 'famille' => $famille, 'quantite' => $q, 'gain' => $q, 'cout' => $q * ($prix[$famille] ?? 0.0)];
            }
        }

        return $lignes;
    }
}
