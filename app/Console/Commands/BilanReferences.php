<?php

namespace App\Console\Commands;

use App\Services\Interpretation\PositionnementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Bilan des références d'une campagne pour la recette du lot L4 (RC-3, T-07, T-08 ; livrable « graphiques de
 * positionnement sur J0 ») : élevages consentants, bulletins par statut, puis par GDS et type de fourrage l'effectif
 * des populations et les quartiles de MAT, UFL 2018 et Cu, avec l'indication « suffisante » (references.n_min).
 * Signale les lots qui n'entrent pas dans les références faute de consentement (point ouvert 14).
 */
class BilanReferences extends Command
{
    protected $signature = 'references:bilan {--campagne=2025-2026 : Code de la campagne}';

    protected $description = 'Bilan de recette des populations de référence d\'une campagne (RC-3)';

    public function handle(PositionnementService $positionnement): int
    {
        $campagne = (string) $this->option('campagne');
        $nMin = $positionnement->nMin();
        $lots = DB::table('fourrage.analyse as a')
            ->join('fourrage.prelevement as p', 'p.id', '=', 'a.prelevement_id')
            ->join('fourrage.elevage as e', 'e.id', '=', 'p.elevage_id')
            ->where('p.campagne_code', $campagne)
            ->selectRaw("e.gds_id, a.statut, a.exclu_references, EXISTS (SELECT 1 FROM app.consentement c WHERE c.elevage_id = e.id AND c.type = 'references' AND c.revoque_le IS NULL) AS consent")
            ->get();

        $this->info("Campagne {$campagne} : {$lots->count()} bulletin(s) ; seuil d'affichage n ≥ {$nMin}.");
        $this->table(['GDS', 'Statut', 'Consentement « références »', 'Exclu', 'Bulletins'],
            $lots->groupBy(fn ($l) => $l->gds_id.'|'.$l->statut.'|'.($l->consent ? 'oui' : 'non').'|'.($l->exclu_references ? 'oui' : 'non'))
                ->map(fn ($g, $cle) => [...explode('|', (string) $cle), $g->count()])->sortKeys()->values()->all());

        $sansConsentement = $lots->where('statut', 'valide')->where('consent', false)->count();
        if ($sansConsentement > 0) {
            $this->warn("{$sansConsentement} bulletin(s) valide(s) n'entrent pas dans les références faute de consentement « références » (point ouvert 14).");
        }

        $references = DB::table('valo.v_reference_gds')->where('campagne_code', $campagne)->whereIn('parametre_code', PositionnementService::PARAMETRES_FICHE)
            ->orderBy('gds_id')->orderBy('type_fourrage_code')->orderBy('parametre_code')->get();
        $this->table(['GDS', 'Type', 'Paramètre', 'n', 'Q1', 'Médiane', 'Q3', 'Positionnement'], $references->map(fn ($r) => [
            $r->gds_id, $r->type_fourrage_code, $r->parametre_code, $r->n, round((float) $r->q1, 2), round((float) $r->mediane, 2), round((float) $r->q3, 2),
            $r->n >= $nMin ? 'quartiles affichés' : "référence insuffisante (n = {$r->n})",
        ])->all());

        $litho = DB::table('valo.v_reference_litho')->where('campagne_code', $campagne)->count();
        $this->line($litho === 0
            ? 'Population lithologique : aucune (couches SIG ou localisation absentes, point ouvert 15 ; contexte rafraîchi chaque nuit).'
            : "Population lithologique : {$litho} groupe(s) classe × type × paramètre.");

        return self::SUCCESS;
    }
}
