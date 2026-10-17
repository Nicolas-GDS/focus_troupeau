<?php

namespace App\Services\Troupeau;

use App\Domain\Referentiels\Referentiels;
use App\Domain\Troupeau\Besoins;
use App\Models\Dossier\EtiquetteAliment;
use App\Models\Fourrage\Elevage;
use App\Models\Plan\LotAnimaux;
use App\Models\Plan\Phase;
use App\Models\Plan\Ration;
use App\Models\Plan\RationComposant;
use App\Models\Stock\Inventaire;
use App\Models\Stock\LotStock;
use App\Services\Dossier\SaisieRefusee;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Troupeau : lots d'animaux, phases et rations (EF-M5-01 à EF-M5-06). Le coefficient UGB est proposé selon la
 * catégorie (ref.categorie_animale.coefficient_ugb_code) ; les pertes à la distribution d'une ration viennent de
 * app.parametre_defaut (RC-6) ; les kg MS d'un composant sont calculés depuis le brut et la MS de sa source. Besoins par
 * les deux voies (RC-8) et copie d'une période à l'autre.
 */
class TroupeauService
{
    public const PERTES_DISTRIBUTION = [
        'pertes.distribution.auge_cone', 'pertes.distribution.ratelier_anneau', 'pertes.distribution.remorque',
        'pertes.distribution.deroule_sol', 'pertes.distribution.anneau_sans_panneau',
    ];

    /** Sources de fourrage (voie ration RC-8.2) et sources prélevées sur un stock (pertes à la distribution, RC-8.3). */
    public const SOURCES_FOURRAGE = ['lot_stock', 'famille_stock', 'paturage'];

    public const SOURCES_STOCK = ['lot_stock', 'famille_stock'];

    public function __construct(
        private readonly Referentiels $referentiels,
        private readonly Besoins $besoins = new Besoins,
    ) {}

    /**
     * @param  array{libelle: string, categorie_code: string, coeff_ugb?: float|null, poids_vif_kg?: float|null, production_valeur?: float|null, production_unite?: string|null}  $donnees
     */
    public function creerLot(Elevage $elevage, Inventaire $inventaire, array $donnees): LotAnimaux
    {
        return LotAnimaux::query()->create([...$this->completerLot($donnees), 'elevage_id' => $elevage->id, 'inventaire_id' => $inventaire->id]);
    }

    /** @param  array<string, mixed>  $donnees */
    public function modifierLot(LotAnimaux $lot, array $donnees): LotAnimaux
    {
        $lot->update($this->completerLot($donnees));

        return $lot;
    }

    public function archiverLot(LotAnimaux $lot): void
    {
        $lot->update(['archive_le' => now()]);
    }

    /** Coefficient UGB par défaut de la catégorie (RC-8.1), null si RC-8.1 n'en donne pas. */
    public function coefficientDefaut(string $categorie): ?float
    {
        $coefficient = DB::table('ref.categorie_animale as c')->join('ref.coefficient_ugb as u', 'u.code', '=', 'c.coefficient_ugb_code')
            ->where('c.code', $categorie)->value('u.coefficient');

        return $coefficient === null ? null : (float) $coefficient;
    }

    public function ajouterPhase(LotAnimaux $lot, string $stade, string $debut, string $fin, int $effectif): Phase
    {
        if (! in_array($stade, Phase::STADES, true)) {
            throw new SaisieRefusee("Stade inconnu : {$stade}.");
        }

        try {
            return DB::transaction(function () use ($lot, $stade, $debut, $fin, $effectif) {
                $phase = Phase::query()->create(['lot_animaux_id' => $lot->id, 'stade' => $stade, 'date_debut' => $debut, 'date_fin' => $fin, 'effectif' => $effectif]);
                Ration::query()->create(['phase_id' => $phase->id, 'mode_distribution' => 'pertes.distribution.deroule_sol',
                    'pertes_distribution_pct' => $this->parametre('pertes.distribution.deroule_sol', $lot->elevage_id)]);

                return $phase;
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23P01') {
                throw new SaisieRefusee('Cette phase chevauche une autre phase du lot : les phases se suivent sans se recouvrir.');
            }
            throw $e;
        }
    }

    public function supprimerPhase(Phase $phase): void
    {
        $phase->delete();
    }

    /** Mode de distribution de la ration et pertes retenues (défaut : valeur de référence du mode, RC-6). */
    public function enregistrerDistribution(Ration $ration, string $mode, ?float $pertes = null): Ration
    {
        if (! in_array($mode, self::PERTES_DISTRIBUTION, true)) {
            throw new SaisieRefusee('Mode de distribution inconnu.');
        }

        $elevage = (string) DB::table('plan.lot_animaux')->where('id', DB::table('plan.phase')->where('id', $ration->phase_id)->value('lot_animaux_id'))->value('elevage_id');
        $ration->update(['mode_distribution' => $mode, 'pertes_distribution_pct' => $pertes ?? $this->parametre($mode, $elevage)]);

        return $ration;
    }

    /**
     * Ajoute un composant ; kg MS = brut × MS de la source quand le brut est saisi (lot de stock, famille de stock,
     * étiquette) ; l'herbe pâturée se saisit en kg MS.
     *
     * @param  array{source: string, lot_stock_id?: int|null, famille_code?: string|null, etiquette_id?: int|null, quantite_brute_kg?: float|null, kg_ms?: float|null}  $donnees
     */
    public function ajouterComposant(Ration $ration, array $donnees): RationComposant
    {
        $inventaireId = (int) DB::table('plan.lot_animaux')->where('id', DB::table('plan.phase')->where('id', $ration->phase_id)->value('lot_animaux_id'))->value('inventaire_id');
        $elevageId = (string) DB::table('stock.inventaire')->where('id', $inventaireId)->value('elevage_id');
        $brut = $donnees['quantite_brute_kg'] ?? null;
        $kgMs = $donnees['kg_ms'] ?? null;

        $ms = match ($donnees['source']) {
            'lot_stock' => $this->msLotStock((int) ($donnees['lot_stock_id'] ?? 0), $inventaireId),
            'famille_stock' => $this->msFamille((string) ($donnees['famille_code'] ?? ''), $inventaireId),
            'etiquette' => $this->msEtiquette((int) ($donnees['etiquette_id'] ?? 0), $elevageId),
            'paturage' => null,
            default => throw new SaisieRefusee('Source de composant non prise en charge.'),
        };

        if ($brut !== null) {
            $kgMs = $ms === null ? throw new SaisieRefusee('MS inconnue pour cette source : saisir la quantité en kg MS.') : $brut * $ms / 100;
        }
        if ($kgMs === null || $kgMs < 0) {
            throw new SaisieRefusee('Saisir une quantité par animal et par jour.');
        }

        return RationComposant::query()->create([
            'ration_id' => $ration->id, 'ordre' => (int) RationComposant::query()->where('ration_id', $ration->id)->max('ordre') + 1,
            'source' => $donnees['source'], 'lot_stock_id' => $donnees['lot_stock_id'] ?? null, 'famille_code' => $donnees['famille_code'] ?? null,
            'etiquette_id' => $donnees['etiquette_id'] ?? null, 'quantite_brute_kg' => $brut, 'kg_ms' => $kgMs,
        ]);
    }

    public function supprimerComposant(RationComposant $composant): void
    {
        $composant->delete();
    }

    /**
     * Besoins de l'inventaire par les deux voies (RC-8) : par lot et par phase, jours de la phase dans la période de
     * référence, voie ration (kg MS de fourrage), besoin prélevé sur le stock (pertes à la distribution), voie UGB ;
     * écart des deux voies et seuil de cohérence.
     *
     * @return array{lots: list<array{lot: LotAnimaux, phases: list<array{phase: Phase, jours: int, ration_t_ms: float, stock_t_ms: float, ugb_t_ms: float, kg_ms_fourrage: float}>, ration_t_ms: float, stock_t_ms: float, ugb_t_ms: float}>, ration_t_ms: float, stock_t_ms: float, ugb_t_ms: float, ecart: float|null, ecart_max: float}
     */
    public function besoins(Inventaire $inventaire): array
    {
        $kgUgb = $this->parametre('ugb.kg_ms_par_ugb_jour', $inventaire->elevage_id);
        $lots = [];

        foreach ($this->lots($inventaire) as $lot) {
            $phases = [];
            foreach ($lot->phases as $phase) {
                $jours = $this->joursDansPeriode($phase, $inventaire);
                $composants = $phase->ration->composants ?? collect();
                $fourrage = (float) $composants->whereIn('source', self::SOURCES_FOURRAGE)->sum(fn (RationComposant $c) => (float) $c->kg_ms);
                $stock = (float) $composants->whereIn('source', self::SOURCES_STOCK)->sum(fn (RationComposant $c) => (float) $c->kg_ms);
                $pertes = (float) ($phase->ration->pertes_distribution_pct ?? 0);

                $phases[] = [
                    'phase' => $phase, 'jours' => $jours, 'kg_ms_fourrage' => $fourrage,
                    'ration_t_ms' => $this->besoins->voieRation($phase->effectif, $fourrage, $jours),
                    'stock_t_ms' => $this->besoins->prelevementStock($this->besoins->voieRation($phase->effectif, $stock, $jours), $pertes),
                    'ugb_t_ms' => $lot->coeff_ugb === null ? 0.0
                        : $this->besoins->voieUgb([['effectif' => (float) $phase->effectif, 'coefficient' => (float) $lot->coeff_ugb]], $jours, $kgUgb)['besoin_t_ms'],
                ];
            }

            $lots[] = ['lot' => $lot, 'phases' => $phases, ...$this->sommes($phases)];
        }

        $totaux = $this->sommes($lots);

        return [
            'lots' => $lots, ...$totaux,
            'ecart' => $this->besoins->ecartVoies($totaux['ugb_t_ms'], $totaux['ration_t_ms']),
            'ecart_max' => $this->parametre('coherence.ecart_voies_max', $inventaire->elevage_id),
        ];
    }

    /**
     * « Repartir de l'hiver dernier » (EF-M5-06) : copie les lots, phases et rations d'un inventaire précédent dans
     * l'inventaire courant ; les dates des phases sont décalées de l'écart calendaire entre les débuts de période ; un composant
     * tiré d'un lot de stock de l'ancien inventaire devient un composant « famille de stock » (les lots de stock ne se
     * transportent pas d'un inventaire à l'autre).
     */
    public function copierDepuis(Inventaire $source, Inventaire $cible): int
    {
        if ($source->elevage_id !== $cible->elevage_id || $source->id === $cible->id) {
            throw new SaisieRefusee('Choisir un autre inventaire du même élevage.');
        }

        $decalage = $source->date_debut->diff($cible->date_debut);

        return DB::transaction(function () use ($source, $cible, $decalage) {
            $copies = 0;
            foreach ($this->lots($source) as $lot) {
                $nouveau = $lot->replicate(['cree_le', 'maj_le', 'cree_par', 'maj_par']);
                $nouveau->inventaire_id = $cible->id;
                $nouveau->save();
                $copies++;

                foreach ($lot->phases as $phase) {
                    $copiePhase = Phase::query()->create([
                        'lot_animaux_id' => $nouveau->id, 'stade' => $phase->stade, 'effectif' => $phase->effectif,
                        'date_debut' => $phase->date_debut->copy()->add($decalage), 'date_fin' => $phase->date_fin->copy()->add($decalage),
                    ]);
                    $ration = Ration::query()->create(['phase_id' => $copiePhase->id, 'mode_distribution' => $phase->ration?->mode_distribution,
                        'pertes_distribution_pct' => $phase->ration->pertes_distribution_pct ?? $this->parametre('pertes.distribution.deroule_sol', $cible->elevage_id)]);

                    foreach ($phase->ration->composants ?? [] as $c) {
                        $versFamille = $c->source === 'lot_stock';
                        RationComposant::query()->create([
                            'ration_id' => $ration->id, 'ordre' => $c->ordre,
                            'source' => $versFamille ? 'famille_stock' : $c->source,
                            'famille_code' => $versFamille ? DB::table('stock.lot_stock')->where('id', $c->lot_stock_id)->value('famille_code') : $c->famille_code,
                            'lot_stock_id' => null, 'aliment_reference_id' => $c->aliment_reference_id, 'etiquette_id' => $c->etiquette_id,
                            'quantite_brute_kg' => $versFamille ? null : $c->quantite_brute_kg, 'kg_ms' => $c->kg_ms,
                        ]);
                    }
                }
            }

            return $copies;
        });
    }

    /** @return Collection<int, LotAnimaux> lots non archivés de l'inventaire, avec phases, rations, composants */
    public function lots(Inventaire $inventaire): Collection
    {
        return LotAnimaux::query()->where('inventaire_id', $inventaire->id)->whereNull('archive_le')
            ->with(['phases.ration.composants.lotStock', 'phases.ration.composants.etiquette'])->orderBy('id')->get();
    }

    public function joursDansPeriode(Phase $phase, Inventaire $inventaire): int
    {
        $debut = $phase->date_debut->max($inventaire->date_debut);
        $fin = $phase->date_fin->min($inventaire->date_fin);

        return $fin->lt($debut) ? 0 : (int) $debut->diffInDays($fin) + 1;
    }

    /**
     * @param  array<string, mixed>  $donnees
     * @return array<string, mixed>
     */
    private function completerLot(array $donnees): array
    {
        $donnees['espece_code'] = (string) DB::table('ref.categorie_animale')->where('code', $donnees['categorie_code'])->value('espece_code')
            ?: throw new SaisieRefusee('Catégorie animale inconnue.');
        $donnees['coeff_ugb'] ??= $this->coefficientDefaut($donnees['categorie_code']);

        return $donnees;
    }

    private function msLotStock(int $id, int $inventaireId): ?float
    {
        $lot = LotStock::query()->where('inventaire_id', $inventaireId)->find($id) ?? throw new SaisieRefusee('Lot de stock introuvable dans cet inventaire.');

        return $lot->ms_pct === null ? null : (float) $lot->ms_pct;
    }

    /** MS moyenne des lots de la famille dans l'inventaire, pondérée par le stock brut. */
    private function msFamille(string $famille, int $inventaireId): ?float
    {
        $lots = LotStock::query()->where('inventaire_id', $inventaireId)->where('famille_code', $famille)->whereNotNull('ms_pct')->get();
        $poids = (float) $lots->sum(fn (LotStock $l) => (float) $l->stock_brut_t_ms);

        return $poids > 0 ? (float) $lots->sum(fn (LotStock $l) => (float) $l->ms_pct * (float) $l->stock_brut_t_ms) / $poids : null;
    }

    private function msEtiquette(int $id, string $elevageId): ?float
    {
        $etiquette = EtiquetteAliment::query()->where('elevage_id', $elevageId)->find($id) ?? throw new SaisieRefusee('Étiquette introuvable pour cet élevage.');

        return $etiquette->ms_pct === null ? null : (float) $etiquette->ms_pct;
    }

    /**
     * @param  list<array{ration_t_ms: float, stock_t_ms: float, ugb_t_ms: float}>  $lignes
     * @return array{ration_t_ms: float, stock_t_ms: float, ugb_t_ms: float}
     */
    private function sommes(array $lignes): array
    {
        return [
            'ration_t_ms' => array_sum(array_column($lignes, 'ration_t_ms')),
            'stock_t_ms' => array_sum(array_column($lignes, 'stock_t_ms')),
            'ugb_t_ms' => array_sum(array_column($lignes, 'ugb_t_ms')),
        ];
    }

    private function parametre(string $cle, string $elevageId): float
    {
        $gds = (int) DB::table('fourrage.elevage')->where('id', $elevageId)->value('gds_id');

        return $this->referentiels->parametre($cle, $gds) ?? throw new SaisieRefusee("Valeur par défaut absente : {$cle}.");
    }
}
