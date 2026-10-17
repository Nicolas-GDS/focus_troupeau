<?php

namespace App\Services\Stocks;

use App\Domain\Autonomie\Incertitude;
use App\Domain\Referentiels\Referentiels;
use App\Domain\Stocks\Balles;
use App\Domain\Stocks\DensiteHorsDomaine;
use App\Domain\Stocks\EquationDensite;
use App\Domain\Stocks\Silo;
use App\Models\Fourrage\Analyse;
use App\Models\Fourrage\Elevage;
use App\Models\Stock\Inventaire;
use App\Models\Stock\LotStock;
use App\Models\Stock\Mouvement;
use App\Services\Dossier\SaisieRefusee;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Inventaires de stocks (EF-M4-01 à EF-M4-06) : création de l'inventaire (période de référence, marge de sécurité
 * copiée de la valeur en vigueur, RC-12), lots de stock calculés à chaque enregistrement (silos RC-5, balles et vrac
 * RC-7), lien avec un prélèvement analysé (la MS du bulletin remplace la valeur par défaut, EF-M4-04), mouvements
 * (EF-M4-05). Les valeurs par défaut (MS, pertes, densité du vrac) viennent de app.parametre_defaut ; la valeur retenue
 * et la valeur par défaut sont toutes deux conservées (EF-M4-07).
 */
class StocksService
{
    /** Clés de pertes admises selon la forme (RC-6). */
    public const PERTES_SILO = ['pertes.conservation.silo_bien_conduit', 'pertes.conservation.silo_chauffe'];

    public const PERTES_BALLES = [
        'pertes.stockage.batiment_ferme', 'pertes.stockage.sous_toit', 'pertes.stockage.bache_sureleve',
        'pertes.stockage.bache_sol', 'pertes.stockage.expose_sureleve', 'pertes.stockage.expose_sol',
    ];

    /** MS par défaut par famille de fourrage (RC-7) ; les autres familles exigent une analyse ou une saisie. */
    private const MS_DEFAUT = ['FOIN' => 'ms.defaut.foin', 'PAILLE' => 'ms.defaut.paille'];

    public function __construct(
        private readonly Referentiels $referentiels,
        private readonly Silo $silo = new Silo,
        private readonly Balles $balles = new Balles,
        private readonly Incertitude $incertitudeDomaine = new Incertitude,
    ) {}

    /**
     * @param  array{date_inventaire: string, type: string, date_debut: string, date_fin: string, annee_seche: bool, commentaire?: string|null}  $donnees
     */
    public function creerInventaire(Elevage $elevage, array $donnees): Inventaire
    {
        if (Inventaire::query()->where('elevage_id', $elevage->id)->where('statut', Inventaire::STATUT_COURANT)->exists()) {
            throw new SaisieRefusee('Un inventaire est déjà en cours pour cet élevage : clore l\'inventaire courant avant d\'en créer un nouveau.');
        }

        $marge = $this->parametre($donnees['annee_seche'] ? 'securite.marge_annee_seche' : 'securite.marge', $elevage->gds_id);

        return Inventaire::query()->create([...$donnees, 'elevage_id' => $elevage->id, 'marge_securite' => $marge]);
    }

    public function clore(Inventaire $inventaire): Inventaire
    {
        $inventaire->update(['statut' => Inventaire::STATUT_CLOS]);

        return $inventaire;
    }

    /**
     * Enregistre un lot (création ou mise à jour) et recalcule ses résultats.
     *
     * @param  array<string, mixed>  $donnees  champs de stock.lot_stock saisis
     * @param  bool  $publiesSeulement  lecteur externe : seule une analyse publiée fournit la MS (§ 6.3)
     */
    public function enregistrerLot(Inventaire $inventaire, array $donnees, ?LotStock $lot = null, bool $publiesSeulement = false): LotStock
    {
        $donnees = $this->completer($inventaire, $donnees, $publiesSeulement);
        $resultats = [...$this->calculer($inventaire, $donnees), 'incertitude_rel' => $this->incertitude($inventaire, $donnees)];

        return DB::transaction(function () use ($inventaire, $donnees, $resultats, $lot) {
            $attributs = [...$donnees, ...$resultats, 'inventaire_id' => $inventaire->id, 'elevage_id' => $inventaire->elevage_id];

            if ($lot === null) {
                return LotStock::query()->create($attributs);
            }

            $lot->update($attributs);

            return $lot;
        });
    }

    /**
     * Calcul d'un lot sans enregistrement (aperçu pendant la saisie).
     *
     * @param  array<string, mixed>  $donnees
     * @return array{stock_utile_t_ms: float, stock_brut_t_ms: float, volume_calc_m3: float|null, densite_calc: float|null, ms_pct: float, ms_origine: string, pertes_pct: float}
     */
    public function apercu(Inventaire $inventaire, array $donnees, bool $publiesSeulement = false): array
    {
        $donnees = $this->completer($inventaire, $donnees, $publiesSeulement);

        return [...$this->calculer($inventaire, $donnees), 'ms_pct' => (float) $donnees['ms_pct'], 'ms_origine' => (string) $donnees['ms_origine'],
            'pertes_pct' => (float) $donnees['pertes_pct']];
    }

    public function supprimerLot(LotStock $lot): void
    {
        $lot->delete();
    }

    /**
     * Mouvement d'un lot (EF-M4-05) : achat (positif), vente (négative), re-comptage (valeur absolue du stock),
     * ajustement (signé, motif obligatoire).
     *
     * @param  array<string, mixed>|null  $detail
     */
    public function ajouterMouvement(LotStock $lot, string $type, string $date, float $quantiteTMs, ?float $prix = null, ?string $motif = null, ?array $detail = null): Mouvement
    {
        if (! in_array($type, Mouvement::TYPES, true)) {
            throw new SaisieRefusee("Type de mouvement inconnu : {$type}.");
        }
        if ($type === 'ajustement' && trim((string) $motif) === '') {
            throw new SaisieRefusee('Un ajustement demande un motif.');
        }
        if ($type !== 'ajustement' && $quantiteTMs < 0) {
            throw new SaisieRefusee('Saisir une quantité positive : le sens du mouvement vient de son type.');
        }

        return Mouvement::query()->create([
            'lot_stock_id' => $lot->id, 'date_mouvement' => $date, 'type' => $type,
            'quantite_t_ms' => $type === 'vente' ? -$quantiteTMs : $quantiteTMs,
            'prix_eur' => $prix, 'motif' => trim((string) $motif) === '' ? null : trim((string) $motif), 'detail' => $detail,
        ]);
    }

    public function supprimerMouvement(Mouvement $mouvement): void
    {
        $mouvement->delete();
    }

    /**
     * Tableau de bord des stocks par famille (EF-M4-06) : stock utile à l'inventaire, mouvements nets (achats, ventes,
     * ajustements), part analysée, dernière mise à jour. La consommation projetée et le recalage sur re-comptage
     * (RC-11) relèvent du bilan d'autonomie (lot L6).
     *
     * @return list<array{famille: string, libelle: string, stock_utile_t_ms: float, mouvements_t_ms: float, part_analysee: float, recomptages: int, maj_le: string|null}>
     */
    public function tableauDeBord(Inventaire $inventaire): array
    {
        $libelles = DB::table('ref.famille_fourrage')->pluck('libelle', 'code');
        $lots = LotStock::query()->where('inventaire_id', $inventaire->id)->with('mouvements')->get();

        return $lots->groupBy('famille_code')->map(function ($lotsFamille, $famille) use ($libelles) {
            $utile = (float) $lotsFamille->sum(fn (LotStock $l) => (float) $l->stock_utile_t_ms);
            $analyse = (float) $lotsFamille->where('ms_origine', 'analyse')->sum(fn (LotStock $l) => (float) $l->stock_utile_t_ms);
            $mouvements = $lotsFamille->flatMap(fn (LotStock $l) => $l->mouvements);

            return [
                'famille' => (string) $famille,
                'libelle' => (string) ($libelles[$famille] ?? $famille),
                'stock_utile_t_ms' => $utile,
                'mouvements_t_ms' => (float) $mouvements->where('type', '!=', 'recomptage')->sum(fn (Mouvement $m) => (float) $m->quantite_t_ms),
                'part_analysee' => $utile > 0 ? $analyse / $utile : 0.0,
                'recomptages' => $mouvements->where('type', 'recomptage')->count(),
                'maj_le' => $lotsFamille->max(fn (LotStock $l) => $l->getAttribute('maj_le'))?->toDateTimeString(),
            ];
        })->sortBy('famille')->values()->all();
    }

    /** Valeur par défaut d'une clé de pertes (fraction). */
    public function pertesDefaut(string $cle, ?int $gdsId = null): float
    {
        return $this->parametre($cle, $gdsId);
    }

    /**
     * Prélèvements analysés de l'élevage que l'on peut relier à un lot de stock, avec leur MS ; pour un lecteur externe,
     * seuls ceux dont une analyse est publiée (§ 6.3).
     *
     * @return list<array{prelevement_id: int, libelle: string, ms: float|null}>
     */
    public function prelevementsReliables(Elevage $elevage, bool $publiesSeulement = false): array
    {
        return DB::table('fourrage.prelevement as p')
            ->join('ref.type_fourrage as t', 't.code', '=', 'p.type_fourrage_code')
            ->where('p.elevage_id', $elevage->id)
            ->whereExists(fn ($q) => $this->analysesUtilisables($q->from('fourrage.analyse')->whereColumn('prelevement_id', 'p.id'), $publiesSeulement))
            ->orderByDesc('p.date_prelevement')
            ->get(['p.id', 'p.nom_lot', 'p.date_prelevement', 't.libelle'])
            ->map(fn ($p) => [
                'prelevement_id' => (int) $p->id,
                'libelle' => trim($p->libelle.' '.($p->nom_lot ? '« '.$p->nom_lot.' »' : '').' — '.date('d/m/Y', (int) strtotime((string) $p->date_prelevement))),
                'ms' => $this->msAnalysee((int) $p->id, $publiesSeulement),
            ])->values()->all();
    }

    /**
     * Famille, MS (analyse > saisie > défaut) et pertes (saisie > défaut), avec la valeur par défaut conservée.
     *
     * @param  array<string, mixed>  $donnees
     * @return array<string, mixed>
     */
    private function completer(Inventaire $inventaire, array $donnees, bool $publiesSeulement): array
    {
        $gdsId = (int) DB::table('fourrage.elevage')->where('id', $inventaire->elevage_id)->value('gds_id');

        if (! empty($donnees['type_fourrage_code'])) {
            $donnees['famille_code'] = (string) DB::table('ref.type_fourrage')->where('code', $donnees['type_fourrage_code'])->value('famille_code');
        }

        if (! empty($donnees['prelevement_id'])) {
            if (DB::table('fourrage.prelevement')->where('id', $donnees['prelevement_id'])->value('elevage_id') !== $inventaire->elevage_id) {
                throw new SaisieRefusee('Le prélèvement relié n\'appartient pas à cet élevage.');
            }
            $ms = $this->msAnalysee((int) $donnees['prelevement_id'], $publiesSeulement);
            if ($ms !== null) {
                $donnees['ms_pct'] = $ms;
                $donnees['ms_origine'] = 'analyse';
            }
        }

        if (($donnees['ms_origine'] ?? null) !== 'analyse') {
            if (isset($donnees['ms_pct'])) {
                $donnees['ms_origine'] = 'saisie';
            } elseif (isset(self::MS_DEFAUT[$donnees['famille_code']])) {
                $donnees['ms_pct'] = $this->parametre(self::MS_DEFAUT[$donnees['famille_code']], $gdsId);
                $donnees['ms_origine'] = 'defaut';
            } else {
                throw new SaisieRefusee('Saisir la matière sèche ou relier le lot à une analyse : pas de valeur par défaut pour cette famille de fourrage.');
            }
        }

        $admises = $donnees['forme'] === 'silo' ? self::PERTES_SILO : self::PERTES_BALLES;
        if (! in_array($donnees['mode_stockage'] ?? null, $admises, true)) {
            throw new SaisieRefusee('Choisir le mode de conservation ou de stockage du lot.');
        }
        $donnees['pertes_defaut_pct'] = $this->parametre((string) $donnees['mode_stockage'], $gdsId);
        $donnees['pertes_pct'] ??= $donnees['pertes_defaut_pct'];

        if ($donnees['forme'] === 'vrac' && empty($donnees['poids_brut_kg'])) {
            if ($donnees['famille_code'] !== 'FOIN') {
                throw new SaisieRefusee('Saisir la densité du vrac en kg brut par m³.');
            }
            $donnees['poids_brut_kg'] = $this->parametre('densite.foin_vrac_kg_m3', $gdsId);
        }

        return $donnees;
    }

    /**
     * Résultats RC-5 (silo) ou RC-7 (balles, vrac).
     *
     * @param  array<string, mixed>  $d
     * @return array{volume_calc_m3: float|null, densite_calc: float|null, stock_brut_t_ms: float, stock_utile_t_ms: float}
     */
    private function calculer(Inventaire $inventaire, array $d): array
    {
        $ms = (float) $d['ms_pct'];
        $pertes = (float) $d['pertes_pct'];

        if ($d['forme'] !== 'silo') {
            $nombre = (float) ($d['forme'] === 'vrac' ? $d['volume_m3'] : $d['nombre']);

            return [
                'volume_calc_m3' => null, 'densite_calc' => null,
                'stock_brut_t_ms' => $this->balles->stockBrut($nombre, (float) $d['poids_brut_kg'], $ms),
                'stock_utile_t_ms' => $this->balles->stockUtile($nombre, (float) $d['poids_brut_kg'], $ms, $pertes),
            ];
        }

        $jours = empty($d['date_fermeture']) ? null : (int) $inventaire->date_inventaire->diffInDays($d['date_fermeture'], true);
        $volume = $this->silo->volume((float) $d['l1_m'], (float) ($d['l2_m'] ?? 0), (float) $d['largeur_m'], (float) $d['hauteur_m'])
            * (1 - $this->silo->correctionTassement($jours, $ms));

        if ($d['methode_densite'] === 'equation') {
            $equation = EquationDensite::tryFrom((string) ($d['equation_code'] ?? '')) ?? throw new SaisieRefusee('Choisir l\'équation de densité.');
            $amidon = isset($d['amidon_pct']) ? (float) $d['amidon_pct']
                : $this->silo->amidonDepuisGrain(isset($d['grain_pct']) ? (float) $d['grain_pct'] : null);
            try {
                $densite = $this->silo->densite($equation, (float) $d['hauteur_m'], $ms, $amidon);
            } catch (DensiteHorsDomaine $e) {
                throw new SaisieRefusee($e->getMessage());
            }
        } else {
            $densite = (float) ($d['densite_kg_ms_m3'] ?? 0) ?: throw new SaisieRefusee('Saisir la densité mesurée en kg MS par m³.');
        }

        return [
            'volume_calc_m3' => $volume, 'densite_calc' => $densite,
            'stock_brut_t_ms' => $this->silo->stockUtile($volume, $densite, 0.0),
            'stock_utile_t_ms' => $this->silo->stockUtile($volume, $densite, $pertes),
        ];
    }

    /**
     * Incertitude relative du stock utile d'un lot (RC-14) : composition quadratique des erreurs de volume, de densité
     * ou de poids, de MS et de pertes ; cas absents du tableau de RC-14 tranchés le 17/10/2026 ([CP]).
     *
     * @param  array<string, mixed>  $d
     */
    public function incertitude(Inventaire $inventaire, array $d): float
    {
        $gds = (int) DB::table('fourrage.elevage')->where('id', $inventaire->elevage_id)->value('gds_id');
        $e = fn (string $cle) => $this->parametre('incertitude.'.$cle, $gds);
        $ms = (float) $d['ms_pct'];
        $ensilage = in_array($d['famille_code'], ['ENRUB', 'ENSIL_HERBE', 'ENSIL_MAIS', 'ENSIL_AUTRE'], true);
        $points = match ($d['ms_origine']) {
            'analyse' => $e('ms_analyse_points'),
            'defaut' => $e('ms_defaut_points'),
            default => $ensilage ? $e('ms_ensilage_points') : $e('ms_saisie_points'),
        };
        $facteurs = [$this->incertitudeDomaine->ms($points, $ms), $e('pertes_relatif') * (float) $d['pertes_pct']];

        if ($d['forme'] === 'silo') {
            $facteurs[] = $d['ouvrage'] === 'taupiniere' ? $e('volume_taupiniere') : $e('volume_couloir');
            $facteurs[] = $e('densite_'.($d['methode_densite'] === 'equation' ? 'equation' : $d['methode_densite']));
        } elseif ($d['forme'] === 'vrac') {
            $facteurs[] = $e('vrac_volume');
            $facteurs[] = $e('vrac_densite');
        } else {
            $facteurs[] = ! empty($d['poids_mesure']) ? $e('poids_mesure') : $e('poids_estime');
        }

        return $this->incertitudeDomaine->lot($facteurs);
    }

    private function msAnalysee(int $prelevementId, bool $publiesSeulement): ?float
    {
        $analyses = $this->analysesUtilisables(DB::table('fourrage.analyse')->where('prelevement_id', $prelevementId), $publiesSeulement)->select('id');
        $ms = DB::table('fourrage.resultat')->whereIn('analyse_id', $analyses)->where('parametre_code', 'MS')->whereNotNull('valeur')
            ->orderByDesc('analyse_id')->value('valeur');

        return $ms === null ? null : (float) $ms;
    }

    /** Analyses non annulées ; publiées seulement (interprétation publiée) pour un lecteur externe. */
    private function analysesUtilisables(Builder $requete, bool $publiesSeulement): Builder
    {
        return $requete->where('statut', '!=', Analyse::STATUT_ANNULE)
            ->when($publiesSeulement, fn ($q) => $q->whereExists(fn ($i) => $i->from('fourrage.interpretation')
                ->whereColumn('fourrage.interpretation.analyse_id', 'fourrage.analyse.id')->where('fourrage.interpretation.statut', 'publie')));
    }

    private function parametre(string $cle, ?int $gdsId): float
    {
        return $this->referentiels->parametre($cle, $gdsId) ?? throw new SaisieRefusee("Valeur par défaut absente : {$cle}.");
    }
}
