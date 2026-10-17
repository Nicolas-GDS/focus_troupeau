<?php

namespace App\Services\Troupeau;

use App\Domain\Dossier\ApportEtiquette;
use App\Domain\Dossier\Repere;
use App\Domain\Referentiels\Referentiels;
use App\Domain\Referentiels\StatutEpistemique;
use App\Domain\Troupeau\Composant;
use App\Domain\Troupeau\ControleRation;
use App\Domain\Troupeau\ControlesRation;
use App\Domain\Troupeau\Couverture;
use App\Domain\Troupeau\LigneBesoin;
use App\Domain\Troupeau\ResolutionBesoins;
use App\Domain\Troupeau\SeuilsControles;
use App\Domain\Troupeau\SeuilsCouverture;
use App\Models\Fourrage\Analyse;
use App\Models\Fourrage\Interpretation;
use App\Models\Plan\LotAnimaux;
use App\Models\Plan\Phase;
use App\Models\Plan\RationComposant;
use App\Models\Stock\Inventaire;
use App\Models\Stock\LotStock;
use App\Services\Dossier\Affichage;
use App\Services\Interpretation\InterpretationService;
use Illuminate\Support\Facades\DB;

/**
 * Apports, couverture et contrôles d'une ration (RC-9, EF-M5-03) et affectation fourrage → lot d'animaux (EF-M5-05).
 *
 * Teneurs des composants : lot de stock relié à une analyse (résultats du bulletin, origine « analyse ») ; famille de
 * stock dont tous les lots sont analysés (moyenne pondérée par le stock utile) ; étiquette (composition par kg brut
 * ramenée au kg MS, décision du 17/10/2026) ; sinon inconnues (lot non analysé, herbe pâturée) et la couverture n'est
 * pas calculée. Pour un lecteur externe, seule une analyse publiée fournit des teneurs (§ 6.3). Besoins :
 * ref.besoin_journalier pour la catégorie et le stade (et les lignes « tous »), production laitière le cas échéant.
 */
class AnalyseRationService
{
    public function __construct(
        private readonly Referentiels $referentiels,
        private readonly InterpretationService $interpretations,
        private readonly ResolutionBesoins $resolution = new ResolutionBesoins,
    ) {}

    /**
     * @return array{msi: float, complet: bool, inconnus: list<string>, elements: list<array<string, mixed>>, manquants: list<string>, controles: list<ControleRation>, sans_besoin: bool}
     */
    public function analyser(Phase $phase, LotAnimaux $lot, bool $publiesSeulement): array
    {
        $phase->loadMissing('ration.composants.lotStock', 'ration.composants.etiquette');
        $gds = (int) DB::table('fourrage.elevage')->where('id', $lot->elevage_id)->value('gds_id');
        $composants = [];
        $inconnus = [];

        foreach ($phase->ration->composants ?? [] as $c) {
            $teneurs = $this->teneurs($c, $publiesSeulement);
            $composants[] = new Composant($this->libelle($c), (float) $c->kg_ms, $teneurs);
            if ($teneurs === null && (float) $c->kg_ms > 0) {
                $inconnus[] = $this->libelle($c);
            }
        }

        $lignes = $this->lignesBesoin($lot->categorie_code, $phase->stade);
        $production = $lot->production_unite === 'kg_lait_j' && $lot->production_valeur !== null ? (float) $lot->production_valeur : null;
        ['besoins' => $besoins, 'manquants' => $manquants] = $this->resolution->resoudre($lignes, $production);
        $couverture = new Couverture(new SeuilsCouverture($this->parametre('couverture.deficit', $gds), $this->parametre('couverture.limite', $gds), $this->parametre('couverture.exces', $gds)));
        ['msi' => $msi, 'apports' => $apports, 'complet' => $complet] = $couverture->apports($composants);
        $parametres = DB::table('ref.parametre')->get()->keyBy('code');
        $sources = collect($lignes)->groupBy('parametre');

        $elements = [];
        foreach ($couverture->couverture($composants, $besoins) as $code => $resultat) {
            $repere = $sources[$code]->first()?->repere;
            $elements[] = [
                'code' => $code, 'libelle' => (string) ($parametres[$code]->libelle ?? $code), 'unite' => (string) ($parametres[$code]->unite ?? ''),
                'besoin' => $besoins[$code], 'resultat' => $resultat, 'source' => $repere === null ? null : Affichage::source($repere),
            ];
        }

        $controles = $complet ? (new ControlesRation($this->seuilsControles($gds)))->controler($apports, $msi, $phase->stade, $lot->espece_code, $lot->categorie_code === 'BOUC_MALE') : [];

        return [
            'msi' => $msi, 'complet' => $complet, 'inconnus' => $inconnus, 'elements' => $elements,
            'manquants' => array_map(fn (string $c) => (string) ($parametres[$c]->libelle ?? $c), $manquants),
            'controles' => $controles, 'sans_besoin' => $lignes === [],
        ];
    }

    /**
     * Affectation (EF-M5-05) : pour chaque lot de stock, les lots d'animaux qui le consomment, au rythme de la phase en
     * cours ou à venir (t MS par jour), et les utilisations conseillée et déconseillée de son interprétation ; écart
     * signalé quand la catégorie d'un lot consommateur est déconseillée.
     *
     * @return list<array{lot: LotStock, consommateurs: list<array{lot: LotAnimaux, phase: Phase, t_ms_jour: float, deconseille: bool}>, conseillee: string|null, deconseillee: list<string>}>
     */
    public function affectation(Inventaire $inventaire, bool $publiesSeulement): array
    {
        $composants = RationComposant::query()->where('source', 'lot_stock')
            ->whereIn('lot_stock_id', LotStock::query()->where('inventaire_id', $inventaire->id)->select('id'))
            ->with('lotStock')->get();
        $phases = Phase::query()->whereIn('id', DB::table('plan.ration')->whereIn('id', $composants->pluck('ration_id'))->select('phase_id'))
            ->with('lot', 'ration')->get()->keyBy(fn (Phase $p) => $p->ration?->id);
        $affectation = [];

        foreach ($composants->groupBy('lot_stock_id') as $lotStockId => $parLot) {
            $lotStock = $parLot->first()->lotStock;
            $interpretation = $this->interpretationDuPrelevement($lotStock?->prelevement_id, $publiesSeulement);
            $deconseillee = $interpretation === null ? [] : $this->interpretations->utilisationDeconseillee($interpretation);
            $consommateurs = [];

            foreach ($parLot as $c) {
                $phase = $phases[$c->ration_id] ?? null;
                if ($phase === null || $phase->lot->archive_le !== null) {
                    continue;
                }
                $consommateurs[] = ['lot' => $phase->lot, 'phase' => $phase, 't_ms_jour' => $phase->effectif * (float) $c->kg_ms / 1000,
                    'deconseille' => in_array($phase->lot->categorie_code, $deconseillee, true)];
            }

            if ($lotStock !== null) {
                $affectation[] = ['lot' => $lotStock, 'consommateurs' => $consommateurs, 'conseillee' => $interpretation?->utilisation_conseillee, 'deconseillee' => $deconseillee];
            }
        }

        return $affectation;
    }

    /** @return array<string, float>|null teneurs par kg MS, null si inconnues */
    private function teneurs(RationComposant $c, bool $publiesSeulement): ?array
    {
        return match ($c->source) {
            'lot_stock' => $c->lotStock?->prelevement_id === null ? null : $this->teneursAnalyse($c->lotStock->prelevement_id, $publiesSeulement),
            'famille_stock' => $this->teneursFamille($c, $publiesSeulement),
            'etiquette' => $this->teneursEtiquette($c),
            default => null,
        };
    }

    /** @return array<string, float>|null */
    private function teneursAnalyse(int $prelevementId, bool $publiesSeulement): ?array
    {
        $analyse = Analyse::query()->withoutGlobalScopes()->where('prelevement_id', $prelevementId)->where('statut', '!=', Analyse::STATUT_ANNULE)
            ->when($publiesSeulement, fn ($q) => $q->whereExists(fn ($i) => $i->from('fourrage.interpretation')
                ->whereColumn('fourrage.interpretation.analyse_id', 'fourrage.analyse.id')->where('fourrage.interpretation.statut', 'publie')))
            ->orderByDesc('id')->first();

        if ($analyse === null) {
            return null;
        }

        return DB::table('fourrage.resultat')->where('analyse_id', $analyse->id)->whereNotNull('valeur')->where('parametre_code', '!=', 'MS')
            ->pluck('valeur', 'parametre_code')->map(fn ($v) => (float) $v)->all();
    }

    /** @return array<string, float>|null moyenne pondérée par le stock utile, si tous les lots de la famille sont analysés */
    private function teneursFamille(RationComposant $c, bool $publiesSeulement): ?array
    {
        $inventaireId = DB::table('plan.lot_animaux')->where('id', DB::table('plan.phase')->where('id', DB::table('plan.ration')->where('id', $c->ration_id)->value('phase_id'))->value('lot_animaux_id'))->value('inventaire_id');
        $lots = LotStock::query()->where('inventaire_id', $inventaireId)->where('famille_code', $c->famille_code)->get();
        $poids = (float) $lots->sum(fn (LotStock $l) => (float) $l->stock_utile_t_ms);

        if ($lots->isEmpty() || $poids <= 0) {
            return null;
        }

        $sommes = [];
        foreach ($lots as $l) {
            $teneurs = $l->prelevement_id === null ? null : $this->teneursAnalyse($l->prelevement_id, $publiesSeulement);
            if ($teneurs === null) {
                return null;
            }
            foreach ($teneurs as $code => $v) {
                $sommes[$code][] = [$v, (float) $l->stock_utile_t_ms];
            }
        }

        $communs = array_filter($sommes, fn (array $valeurs) => count($valeurs) === $lots->count());

        return array_map(fn (array $valeurs) => array_sum(array_map(fn ($x) => $x[0] * $x[1], $valeurs)) / $poids, $communs);
    }

    /** @return array<string, float>|null */
    private function teneursEtiquette(RationComposant $c): ?array
    {
        $etiquette = $c->etiquette;
        if ($etiquette === null || $etiquette->ms_pct === null) {
            return null;
        }

        $unites = DB::table('ref.parametre')->pluck('unite', 'code');
        $teneurs = [];
        foreach (DB::table('dossier.etiquette_composition')->where('etiquette_id', $etiquette->id)->get() as $l) {
            $v = ApportEtiquette::teneurParKgMs((float) $l->valeur, (string) $l->unite, (string) ($unites[$l->parametre_code] ?? ''), (float) $etiquette->ms_pct);
            if ($v !== null) {
                $teneurs[(string) $l->parametre_code] = $v;
            }
        }

        return $teneurs;
    }

    /** @return list<LigneBesoin> */
    private function lignesBesoin(string $categorie, string $stade): array
    {
        return DB::table('ref.besoin_journalier')->where('categorie_code', $categorie)->whereIn('stade', [$stade, 'tous'])->whereNull('version_fin')
            ->orderBy('id')->get()->map(fn ($l) => new LigneBesoin(
                (string) $l->parametre_code, (string) $l->mode, (string) $l->base,
                $l->valeur === null ? null : (float) $l->valeur, $l->valeur_min === null ? null : (float) $l->valeur_min, $l->valeur_max === null ? null : (float) $l->valeur_max,
                new Repere($l->reference_id === null ? null : (int) $l->reference_id, StatutEpistemique::from((string) $l->statut_epistemique), (bool) $l->valide),
                $l->maximum === null ? null : (float) $l->maximum,
            ))->values()->all();
    }

    private function interpretationDuPrelevement(?int $prelevementId, bool $publiesSeulement): ?Interpretation
    {
        if ($prelevementId === null) {
            return null;
        }

        $analyse = Analyse::query()->withoutGlobalScopes()->where('prelevement_id', $prelevementId)->where('statut', '!=', Analyse::STATUT_ANNULE)->orderByDesc('id')->first();

        return $analyse === null ? null : ($this->interpretations->publiee($analyse) ?? ($publiesSeulement ? null : $this->interpretations->courante($analyse)));
    }

    private function libelle(RationComposant $c): string
    {
        return match ($c->source) {
            'lot_stock' => (string) $c->lotStock?->libelle,
            'famille_stock' => (string) DB::table('ref.famille_fourrage')->where('code', $c->famille_code)->value('libelle'),
            'etiquette' => '« '.$c->etiquette?->libelle_eleveur.' »',
            default => 'Herbe pâturée',
        };
    }

    private function seuilsControles(int $gds): SeuilsControles
    {
        return new SeuilsControles(
            $this->parametre('controle.ca_p_min', $gds), $this->parametre('controle.ca_p_max', $gds), $this->parametre('controle.ca_p_max_prepa_velage', $gds),
            $this->parametre('controle.k_mg_max', $gds), $this->parametre('controle.baca_max_fin_gestation', $gds),
            $this->parametre('controle.pdi_ufl_min', $gds), $this->parametre('controle.pdi_ufl_max', $gds),
        );
    }

    private function parametre(string $cle, int $gds): float
    {
        return (float) $this->referentiels->parametre($cle, $gds);
    }
}
