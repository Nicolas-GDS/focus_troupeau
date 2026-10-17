<?php

namespace App\Services\Autonomie;

use App\Domain\Autonomie\ConsommationPhase;
use App\Domain\Autonomie\Incertitude;
use App\Domain\Autonomie\LotProjection;
use App\Domain\Autonomie\MouvementProjection;
use App\Domain\Autonomie\Periode;
use App\Domain\Autonomie\Projection;
use App\Domain\Autonomie\ResultatProjection;
use App\Domain\Referentiels\Referentiels;
use App\Models\Plan\Bilan;
use App\Models\Plan\LevierLigne;
use App\Models\Plan\LotAnimaux;
use App\Models\Plan\Scenario;
use App\Models\Stock\Inventaire;
use App\Models\Stock\LotStock;
use App\Models\Stock\Mouvement;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Bilan d'autonomie d'un inventaire (EF-M6-01 à EF-M6-05, RC-10 à RC-14) : traduction des stocks, des mouvements et
 * des rations en données du domaine, projection centrale et bornes d'incertitude (S × (1 ∓ e)), sécurité, besoins et
 * stocks par famille ; un scénario s'applique virtuellement (RC-13.4) : ses lignes d'achat deviennent des lots à stock
 * nul alimentés à la date de livraison, ses autres gains s'ajoutent à la famille de la ligne à sa date d'effet, une
 * réaffectation retire la quantité de la famille cédante ; les lots et mouvements déjà créés par un scénario retenu sont
 * alors écartés. Le dernier bilan de l'inventaire (et de chaque scénario) est mémorisé dans plan.bilan.
 */
class BilanService
{
    public function __construct(
        private readonly Referentiels $referentiels,
        private readonly Projection $projection = new Projection,
        private readonly Incertitude $incertitude = new Incertitude,
        private readonly Periode $periode = new Periode,
    ) {}

    /**
     * @return array{centrale: ResultatProjection, basse: ResultatProjection, haute: ResultatProjection, incertitude: float, lots: list<LotProjection>, libelles: array<string, string>, stocks_famille: array<string, float>, securites_famille: array<string, float>, besoin_ration_t_ms: float, pertes_moyennes: float, voie_ugb: array{t_ms: float|null, jours_equivalents: float|null}, phases: list<ConsommationPhase>, debut: DateTimeImmutable, fin: DateTimeImmutable}
     */
    public function calculer(Inventaire $inventaire, ?Scenario $scenario = null): array
    {
        $debut = DateTimeImmutable::createFromInterface($inventaire->date_debut->copy()->startOfDay());
        $fin = DateTimeImmutable::createFromInterface($inventaire->date_fin->copy()->startOfDay());
        $lotsStock = LotStock::query()->withoutGlobalScopes()->where('inventaire_id', $inventaire->id)
            ->when($scenario !== null, fn ($q) => $q->whereNull('scenario_ligne_id'))->orderBy('id')->get();
        $lots = [];
        $libelles = [];
        foreach ($lotsStock as $l) {
            $lots[] = new LotProjection('L'.$l->id, $l->famille_code, (float) $l->stock_utile_t_ms,
                $l->date_recolte === null ? null : DateTimeImmutable::createFromInterface($l->date_recolte), (int) ($l->priorite_consommation ?? 0),
                $l->prelevement_id !== null, (float) ($l->incertitude_rel ?? 0));
            $libelles['L'.$l->id] = $l->libelle;
        }

        $mouvements = Mouvement::query()->withoutGlobalScopes()->whereIn('lot_stock_id', $lotsStock->pluck('id'))
            ->when($scenario !== null, fn ($q) => $q->whereNull('scenario_ligne_id'))->get()
            ->map(fn (Mouvement $m) => new MouvementProjection('L'.$m->lot_stock_id, DateTimeImmutable::createFromInterface($m->date_mouvement->copy()->startOfDay()), $m->type, (float) $m->quantite_t_ms))
            ->values()->all();

        [$phases, $besoinRation, $pertesMoyennes, $ugb] = $this->phases($inventaire);

        if ($scenario !== null) {
            foreach (LevierLigne::query()->withoutGlobalScopes()->where('scenario_id', $scenario->id)->orderBy('ordre')->get() as $ligne) {
                [$lotsVirtuels, $mvts] = $this->ligneVirtuelle($ligne, $debut);
                array_push($lots, ...$lotsVirtuels);
                array_push($mouvements, ...$mvts);
            }
        }

        $marge = (float) $inventaire->marge_securite;
        $centrale = $this->projection->projeter($lots, $mouvements, $phases, $debut, $fin, $marge);
        $stocksFamille = [];
        foreach ($lots as $l) {
            $stocksFamille[$l->famille] = ($stocksFamille[$l->famille] ?? 0.0) + $l->stockUtile;
        }
        foreach ($mouvements as $m) {
            $famille = collect($lots)->firstWhere('id', $m->lotId)?->famille;
            if ($famille !== null && $m->type !== MouvementProjection::RECOMPTAGE) {
                $stocksFamille[$famille] = ($stocksFamille[$famille] ?? 0.0) + $m->quantite;
            }
        }

        return [
            'centrale' => $centrale,
            'basse' => $this->projection->projeter($this->incertitude->bornes($lots, -1), $mouvements, $phases, $debut, $fin, $marge),
            'haute' => $this->projection->projeter($this->incertitude->bornes($lots, 1), $mouvements, $phases, $debut, $fin, $marge),
            'incertitude' => $this->incertitude->totale(array_values(array_filter($lots, fn (LotProjection $l) => $l->stockUtile > 0))),
            'lots' => $lots, 'libelles' => $libelles, 'stocks_famille' => $stocksFamille,
            'securites_famille' => array_map(fn (float $b) => $marge * $b, $centrale->besoinParFamille),
            'besoin_ration_t_ms' => $besoinRation, 'pertes_moyennes' => $pertesMoyennes, 'voie_ugb' => $ugb, 'phases' => $phases,
            'debut' => $debut, 'fin' => $fin,
        ];
    }

    /** Calcule et mémorise le dernier bilan de l'inventaire (ou d'un scénario) ; l'indicateur d'alerte est conservé. */
    public function enregistrer(Inventaire $inventaire, ?Scenario $scenario = null): Bilan
    {
        $calcul = $this->calculer($inventaire, $scenario);
        $c = $calcul['centrale'];
        $date = fn (?DateTimeImmutable $d) => $d?->format('Y-m-d');
        $reels = array_keys($calcul['libelles']);

        $bilan = Bilan::query()->withoutGlobalScopes()->firstOrNew(['inventaire_id' => $inventaire->id, 'scenario_id' => $scenario?->id]);
        $bilan->fill([
            'calcule_le' => now(), 'stock_utile_t_ms' => $c->stockUtile, 'besoin_t_ms' => $c->besoinStock, 'securite_t_ms' => $c->securite,
            'bilan_t_ms' => $c->bilan, 'couverture' => $c->couverture, 'autonomie_jours' => $c->autonomieJours,
            'date_rupture' => $date($c->dateRupture), 'date_rupture_min' => $date($calcul['basse']->dateRupture),
            'date_rupture_max' => $date($calcul['haute']->dateRupture), 'date_entame_securite' => $date($c->dateEntameSecurite),
            'incertitude_rel' => $calcul['incertitude'],
            'detail' => [
                'mensuel' => $c->mensuel,
                'rupture_famille' => array_map($date, $c->ruptureParFamille),
                'rupture_lot' => array_map($date, array_intersect_key($c->ruptureParLot, array_flip($reels))),
                'epuisement_lot' => array_map($date, array_intersect_key($c->epuisementParLot, array_flip($reels))),
                'ecarts' => array_map(fn (array $e) => [...$e, 'date' => $e['date']->format('Y-m-d')], $c->ecartsRecalage),
                'besoin_famille' => $c->besoinParFamille, 'stock_famille' => $calcul['stocks_famille'], 'securite_famille' => $calcul['securites_famille'],
                'voie_ugb' => $calcul['voie_ugb'], 'jours' => $c->jours,
            ],
        ]);
        $bilan->alerte_envoyee ??= false;
        $bilan->save();

        return $bilan;
    }

    /** Dernier bilan mémorisé de l'inventaire courant d'un élevage. */
    public function dernier(string $elevageId): ?Bilan
    {
        $inventaire = Inventaire::query()->withoutGlobalScopes()->where('elevage_id', $elevageId)->where('statut', Inventaire::STATUT_COURANT)->first();

        return $inventaire === null ? null : Bilan::query()->withoutGlobalScopes()->where('inventaire_id', $inventaire->id)->whereNull('scenario_id')->first();
    }

    /**
     * Prélèvements des phases sur les stocks, besoin de la voie ration avant pertes, pertes moyennes pondérées et voie
     * UGB de cadrage (RC-10 : jours équivalents si la période est décrite en phases).
     *
     * @return array{0: list<ConsommationPhase>, 1: float, 2: float, 3: array{t_ms: float|null, jours_equivalents: float|null}}
     */
    private function phases(Inventaire $inventaire): array
    {
        $lots = LotAnimaux::query()->withoutGlobalScopes()->where('inventaire_id', $inventaire->id)->whereNull('archive_le')
            ->with(['phases' => fn ($q) => $q->withoutGlobalScopes(), 'phases.ration' => fn ($q) => $q->withoutGlobalScopes(), 'phases.ration.composants' => fn ($q) => $q->withoutGlobalScopes()])->get();
        $phases = [];
        $besoinRation = 0.0;
        $prelevement = 0.0;
        $ugbJours = 0.0;
        $joursPeriode = (int) $inventaire->date_debut->diffInDays($inventaire->date_fin) + 1;

        foreach ($lots as $lot) {
            foreach ($lot->phases as $phase) {
                $composants = [];
                foreach ($phase->ration->composants ?? [] as $c) {
                    if (in_array($c->source, ['lot_stock', 'famille_stock'], true)) {
                        $composants[] = ['lot' => $c->source === 'lot_stock' ? 'L'.$c->lot_stock_id : null, 'famille' => $c->famille_code, 'kg_ms' => (float) $c->kg_ms];
                    }
                }
                $pertes = (float) ($phase->ration->pertes_distribution_pct ?? 0);
                $debut = max($phase->date_debut, $inventaire->date_debut);
                $fin = min($phase->date_fin, $inventaire->date_fin);
                $jours = $fin->lt($debut) ? 0 : (int) $debut->diffInDays($fin) + 1;
                $kg = array_sum(array_column($composants, 'kg_ms'));
                $besoinRation += $phase->effectif * $kg * $jours / 1000;
                $prelevement += $phase->effectif * $kg * $jours / 1000 / (1 - $pertes);
                $ugbJours += $phase->effectif * (float) ($lot->coeff_ugb ?? 0) * $jours;
                $phases[] = new ConsommationPhase(DateTimeImmutable::createFromInterface($phase->date_debut->copy()->startOfDay()),
                    DateTimeImmutable::createFromInterface($phase->date_fin->copy()->startOfDay()), $phase->effectif, $pertes, $composants);
            }
        }

        $periodes = DB::table('stock.periode_phase')->where('inventaire_id', $inventaire->id)->orderBy('date_debut')->get();
        $joursEquivalents = $periodes->isEmpty() ? null : $this->periode->joursEquivalents($periodes->map(fn ($p) => [
            'debut' => new DateTimeImmutable($p->date_debut), 'fin' => new DateTimeImmutable($p->date_fin), 'coefficient' => (float) $p->coefficient,
        ])->values()->all());
        $gds = (int) DB::table('fourrage.elevage')->where('id', $inventaire->elevage_id)->value('gds_id');
        $ugbMoyen = $joursPeriode > 0 ? $ugbJours / $joursPeriode : 0.0;

        return [
            $phases, $besoinRation, $besoinRation > 0 ? 1 - $besoinRation / $prelevement : 0.0,
            ['t_ms' => $ugbMoyen > 0 ? $ugbMoyen * (float) $this->referentiels->parametre('ugb.kg_ms_par_ugb_jour', $gds) * ($joursEquivalents ?? $joursPeriode) / 1000 : null,
                'jours_equivalents' => $joursEquivalents],
        ];
    }

    /**
     * Lots et mouvements virtuels d'une ligne de scénario (RC-13.4).
     *
     * @return array{0: list<LotProjection>, 1: list<MouvementProjection>}
     */
    private function ligneVirtuelle(LevierLigne $ligne, DateTimeImmutable $debut): array
    {
        $date = $ligne->date_effet === null ? $debut : DateTimeImmutable::createFromInterface($ligne->date_effet->copy()->startOfDay());
        $gain = (float) $ligne->gain_t_ms;
        $id = 'S'.$ligne->id;
        $lots = [];
        $mouvements = [];

        if ($ligne->famille_code !== null && $gain != 0.0) {
            $lots[] = new LotProjection($id, $ligne->famille_code, 0.0, null, PHP_INT_MAX);
            $mouvements[] = new MouvementProjection($id, $date, 'achat', $gain);
        }
        if ($ligne->levier_code === 'reaffectation' && $ligne->famille_source_code !== null) {
            $lots[] = new LotProjection($id.'-cede', $ligne->famille_source_code, 0.0, null, PHP_INT_MAX);
            $mouvements[] = new MouvementProjection($id.'-cede', $date, 'ajustement', -(float) $ligne->quantite);
        }

        return [$lots, $mouvements];
    }
}
