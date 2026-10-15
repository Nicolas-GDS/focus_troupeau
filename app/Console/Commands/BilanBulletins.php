<?php

namespace App\Console\Commands;

use App\Domain\Interpretation\Classeur;
use App\Domain\Interpretation\ContexteLot;
use App\Domain\Interpretation\ValeurAnalysee;
use App\Domain\Referentiels\Referentiels;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Bilan d'une campagne pour la recette T-01 (§ 9.2) : analyses par statut, ratios calculés, bulletins en anomalie,
 * alertes automatiques de la grille, part des lots sous le seuil de carence en cuivre.
 */
class BilanBulletins extends Command
{
    protected $signature = 'bulletins:bilan {--campagne=2025-2026 : Code de la campagne}';

    protected $description = 'Bilan de recette des bulletins d\'une campagne (T-01)';

    public function handle(Referentiels $referentiels): int
    {
        $campagne = (string) $this->option('campagne');
        $analyses = DB::table('fourrage.analyse as a')
            ->join('fourrage.prelevement as p', 'p.id', '=', 'a.prelevement_id')
            ->where('p.campagne_code', $campagne)
            ->get(['a.id', 'a.num_echantillon_labo', 'a.statut', 'a.anomalie_detail', 'p.type_fourrage_code', 'p.destination_categorie_code']);

        $this->info("Campagne {$campagne} : {$analyses->count()} analyse(s).");
        $this->table(['Statut', 'Analyses'], $analyses->countBy('statut')->map(fn ($n, $s) => [$s, $n])->values()->all());

        $ids = $analyses->pluck('id');
        $this->line('Ratios calculés par le GDS : '.DB::table('fourrage.resultat')->whereIn('analyse_id', $ids)->where('origine', 'calcul_gds')->count());

        $anomalies = $analyses->where('statut', 'anomalie');
        if ($anomalies->isNotEmpty()) {
            $this->warn('Bulletins en anomalie :');
            $this->table(['Bulletin', 'Détail'], $anomalies->map(fn ($a) => [$a->num_echantillon_labo, $a->anomalie_detail])->values()->all());
        }

        // Alertes automatiques que produirait la grille courante (RC-1), lots contrôlés ou validés
        $classeur = new Classeur;
        $seuils = $referentiels->seuilsActifs();
        $alertes = [];
        $cuivre = ['mesures' => 0, 'sous_seuil' => 0];
        $seuilCarenceCu = collect($seuils)->first(fn ($s) => $s->parametreCode === 'CU' && $s->classe === 'carence_probable' && $s->especeCode === null);

        foreach ($analyses->whereIn('statut', ['controle', 'valide']) as $a) {
            $lot = new ContexteLot($a->type_fourrage_code, $referentiels->familleDuType($a->type_fourrage_code), null, null);
            $resultats = DB::table('fourrage.resultat')->where('analyse_id', $a->id)->whereNotNull('valeur')->get();

            foreach ($resultats as $r) {
                $classement = $classeur->classer(new ValeurAnalysee($r->parametre_code, (float) $r->valeur, $r->censure), $lot, $seuils);
                if ($classement !== null && $classement->produitUneAlerte()) {
                    $cle = $r->parametre_code.' — '.$classement->seuil->classe.' (gravité '.$classement->seuil->gravite.')';
                    $alertes[$cle] = ($alertes[$cle] ?? 0) + 1;
                }
                if ($r->parametre_code === 'CU' && $seuilCarenceCu?->borneMax !== null) {
                    $cuivre['mesures']++;
                    $cuivre['sous_seuil'] += (float) $r->valeur < $seuilCarenceCu->borneMax ? 1 : 0;
                }
            }
        }

        ksort($alertes);
        $this->table(['Alerte automatique (grille '.$referentiels->versionGrilleCourante().')', 'Lots'], collect($alertes)->map(fn ($n, $c) => [$c, $n])->values()->all());

        if ($cuivre['mesures'] > 0 && $seuilCarenceCu !== null) {
            $part = round(100 * $cuivre['sous_seuil'] / $cuivre['mesures']);
            $this->info("Cuivre : {$cuivre['sous_seuil']} lot(s) sur {$cuivre['mesures']} sous {$seuilCarenceCu->borneMax} mg/kg MS ({$part} %).");
        }

        return self::SUCCESS;
    }
}
