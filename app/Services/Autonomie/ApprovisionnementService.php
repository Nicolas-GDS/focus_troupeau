<?php

namespace App\Services\Autonomie;

use App\Domain\Autonomie\Levier;
use App\Domain\Autonomie\ParametresLeviers;
use App\Domain\Autonomie\PropositionInitiale;
use App\Domain\Referentiels\Referentiels;
use App\Domain\Troupeau\ClasseCouverture;
use App\Livewire\Troupeau\Troupeau;
use App\Models\App\User;
use App\Models\Fourrage\Elevage;
use App\Models\Plan\Bilan;
use App\Models\Plan\LevierLigne;
use App\Models\Plan\LotAnimaux;
use App\Models\Plan\Scenario;
use App\Models\Stock\Inventaire;
use App\Models\Stock\LotStock;
use App\Models\Stock\Mouvement;
use App\Services\Dossier\SaisieRefusee;
use App\Services\ElevageCourant;
use App\Services\Notifications\NotificationService;
use App\Services\Notifications\TextesTypes;
use App\Services\Referentiels\ParametresElevage;
use App\Services\Troupeau\AnalyseRationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Approvisionnement (E15, EF-M7-01 à EF-M7-06, RC-13) : proposition initiale (étapes 1 et 2 chiffrées ; étapes 3 et 4
 * en besoin résiduel par animal et par jour, tant que les tables d'aliments sont vides), scénarios et leviers (gain et
 * coût, RC-13.3), évaluation (RC-13.4) et comparaison, scénario retenu (un seul par inventaire) : lots « achat » et
 * mouvements liés aux lignes, retirés quand un autre scénario est retenu ; technicien prévenu. Aucun nom de produit
 * (EF-M7-06) : les concentrés sont décrits par leur famille de base et leur taux.
 */
class ApprovisionnementService
{
    /** Prix indicatif par famille achetée (clés de app.parametre_defaut, RC-13.3). */
    public const PRIX = ['FOIN' => 'prix.foin_t_ms', 'ENRUB' => 'prix.enrubannage_t_ms', 'PAILLE' => 'prix.paille_t_ms', 'CONCENTRE' => 'prix.concentre_t_ms'];

    /** Familles de base d'un concentré en substitution → clé du taux (RC-13.3). */
    public const SUBSTITUTION = ['FOIN' => 'substitution.foin', 'ENSIL_HERBE' => 'substitution.ensilage_herbe', 'ENSIL_MAIS' => 'substitution.ensilage_mais'];

    /** Unité de la quantité de chaque levier. */
    public const UNITES = [
        Levier::ACHAT => 't MS', Levier::CONCENTRE => 't MS', Levier::DECAPITALISATION => 'UGB', Levier::GENISSES => 'têtes',
        Levier::PATURAGE => 'jours', Levier::RATELIER => 'taux de pertes', Levier::REAFFECTATION => 't MS', Levier::AUTRE => 't MS',
    ];

    public const LIBELLES = [
        Levier::ACHAT => 'Achat de fourrage', Levier::CONCENTRE => 'Concentré en substitution', Levier::DECAPITALISATION => 'Décapitalisation (UGB retirés)',
        Levier::GENISSES => 'Génisses de renouvellement supprimées', Levier::PATURAGE => 'Pâturage allongé ou dérobée', Levier::RATELIER => 'Râtelier moins gaspilleur',
        Levier::REAFFECTATION => 'Réaffectation d\'un stock excédentaire', Levier::AUTRE => 'Autre levier', 'correction_nutritionnelle' => 'Correction nutritionnelle',
    ];

    /** Éléments de l'étape 3 (énergie, azote) ; les autres relèvent de l'étape 4 (minéraux). */
    private const ENERGIE_AZOTE = ['UFL_2018', 'PDI', 'MAT'];

    /** Priorité de consommation d'un lot « achat » : après les lots existants de la famille. */
    private const PRIORITE_ACHAT = 32767;

    public const MAX_COMPARAISON = 4;

    public function __construct(
        private readonly BilanService $bilans,
        private readonly AnalyseRationService $analyse,
        private readonly Referentiels $referentiels,
        private readonly NotificationService $notifications,
        private readonly TextesTypes $textes,
        private readonly AlerteAutonomie $alertes,
        private readonly ParametresElevage $parametresElevage,
        private readonly PropositionInitiale $proposition = new PropositionInitiale,
    ) {}

    /**
     * Proposition initiale (RC-13.1, RC-13.2) : diagnostic par famille, lignes chiffrées (réaffectation, achat) et
     * besoins résiduels par lot d'animaux (énergie et azote, minéraux).
     *
     * @return array{deficits: array<string, float>, excedents: array<string, float>, lignes: list<array{levier: string, source: string|null, famille: string, quantite: float, gain: float, cout: float, prix: float|null, prix_indicatif: bool, consequence: string}>, residuels: list<array{lot: string, phase: string, etape: int, element: string, residuel: float, unite: string}>}
     */
    public function proposition(Inventaire $inventaire, bool $publiesSeulement = false): array
    {
        $calcul = $this->bilans->calculer($inventaire);
        $gds = $this->gds($inventaire);
        ['deficits' => $deficits, 'excedents' => $excedents] = $this->proposition->diagnostic(
            $calcul['centrale']->besoinParFamille, $calcul['securites_famille'], $calcul['stocks_famille']);
        $prix = $this->prixFamilles($gds, $inventaire->elevage_id);

        $substituabilites = [];
        foreach (DB::table('ref.substituabilite')->orderBy('famille_source')->orderBy('famille_cible')->get() as $s) {
            $plafond = $s->part_max_ration === null ? null : $this->plafond($inventaire, (string) $s->famille_cible, (float) $s->part_max_ration, $s->stades_autorises);
            if ($plafond !== null) {
                if ($plafond <= 0) {
                    continue;
                }
                $excedents[$s->famille_source] = min($excedents[$s->famille_source] ?? 0.0, $plafond);
            }
            $substituabilites[] = ['source' => (string) $s->famille_source, 'cible' => (string) $s->famille_cible, 'ratio' => (float) $s->ratio];
        }

        $lignes = array_map(fn (array $l) => [...$l, 'prix' => $l['levier'] === Levier::ACHAT ? ($prix[$l['famille']] ?? null) : null,
            'prix_indicatif' => $l['levier'] === Levier::ACHAT, 'consequence' => $this->consequence($l['levier'], $l['source'], $l['famille'])],
            $this->proposition->proposer($deficits, $excedents, $substituabilites, array_filter($prix, fn ($p) => $p !== null)));

        return ['deficits' => $deficits, 'excedents' => $excedents, 'lignes' => $lignes, 'residuels' => $this->residuels($inventaire, $publiesSeulement)];
    }

    /** Crée un scénario à partir de la proposition initiale (origine « proposition »), ou vide. */
    public function creerScenario(Inventaire $inventaire, string $libelle, bool $depuisProposition, bool $publiesSeulement = false): Scenario
    {
        $this->exigerCourant($inventaire);

        return DB::transaction(function () use ($inventaire, $libelle, $depuisProposition, $publiesSeulement) {
            $scenario = Scenario::query()->create(['inventaire_id' => $inventaire->id, 'libelle' => $libelle, 'origine' => $depuisProposition ? 'proposition' : 'utilisateur']);
            if ($depuisProposition) {
                $proposition = $this->proposition($inventaire, $publiesSeulement);
                foreach ($proposition['lignes'] as $i => $l) {
                    LevierLigne::query()->create([
                        'scenario_id' => $scenario->id, 'ordre' => $i + 1, 'levier_code' => $l['levier'], 'famille_code' => $l['famille'],
                        'famille_source_code' => $l['source'], 'quantite' => $l['quantite'], 'unite' => self::UNITES[$l['levier']],
                        'prix_unitaire' => $l['prix'], 'parametre' => $l['prix'], 'date_effet' => $inventaire->date_debut->toDateString(),
                        'gain_t_ms' => $l['gain'], 'cout_eur' => $l['prix'] === null ? $l['cout'] : $l['quantite'] * $l['prix'],
                        'commentaire' => $l['consequence'],
                    ]);
                }
                foreach ($proposition['residuels'] as $j => $r) {
                    LevierLigne::query()->create([
                        'scenario_id' => $scenario->id, 'ordre' => count($proposition['lignes']) + $j + 1, 'levier_code' => 'correction_nutritionnelle',
                        'quantite' => $r['residuel'], 'unite' => $r['unite'].' par animal et par jour', 'gain_t_ms' => 0, 'cout_eur' => 0,
                        'commentaire' => $this->texteResiduel($r),
                    ]);
                }
            }

            return $scenario;
        });
    }

    public function dupliquer(Scenario $source, string $libelle): Scenario
    {
        $inventaire = $this->inventaire($source);
        $this->exigerCourant($inventaire);

        return DB::transaction(function () use ($source, $libelle) {
            $copie = Scenario::query()->create(['inventaire_id' => $source->inventaire_id, 'libelle' => $libelle, 'origine' => 'utilisateur']);
            foreach ($this->lignes($source) as $l) {
                LevierLigne::query()->create([...$l->only($l->getFillable()), 'scenario_id' => $copie->id]);
            }

            return $copie;
        });
    }

    /**
     * Ajoute ou modifie un levier : gain et coût recalculés (RC-13.3) ; famille imputée par défaut à la famille du plus
     * fort déficit (concentré : famille de base) ; date d'effet par défaut au début de la période.
     *
     * @param  array{levier_code: string, quantite: float, parametre?: float|null, famille_code?: string|null, famille_source_code?: string|null, date_effet?: string|null, commentaire?: string|null}  $d
     */
    public function enregistrerLigne(Scenario $scenario, array $d, ?LevierLigne $ligne = null): LevierLigne
    {
        $inventaire = $this->inventaire($scenario);
        $this->exigerCourant($inventaire);
        $code = $d['levier_code'];
        if (! in_array($code, Levier::CODES, true)) {
            throw new SaisieRefusee('Levier inconnu.');
        }
        $quantite = (float) $d['quantite'];
        if ($code !== Levier::RATELIER && $quantite <= 0) {
            throw new SaisieRefusee('Saisir une quantité positive.');
        }

        $calcul = $this->bilans->calculer($inventaire);
        $gds = $this->gds($inventaire);
        $famille = ($d['famille_code'] ?? null) ?: $this->familleParDefaut($calcul);
        if ($code === Levier::CONCENTRE && ! isset(self::SUBSTITUTION[$famille])) {
            throw new SaisieRefusee('Un concentré en substitution se rapporte à une ration à base de foin, d\'ensilage d\'herbe ou d\'ensilage de maïs.');
        }
        if ($code === Levier::REAFFECTATION && empty($d['famille_source_code'])) {
            throw new SaisieRefusee('Indiquer la famille cédée par la réaffectation.');
        }
        $parametre = $d['parametre'] ?? null;
        $parametre = $parametre === null ? $this->parametreDefaut($code, $famille, $gds, $inventaire->elevage_id) : (float) $parametre;
        if ($code === Levier::RATELIER && ($parametre === null || $parametre < 0 || $parametre >= 1)) {
            throw new SaisieRefusee('Saisir le nouveau taux de pertes à la distribution (entre 0 et 1).');
        }

        $jours = $calcul['centrale']->jours;
        $levier = new Levier($this->parametresLeviers($gds));
        ['gain' => $gain, 'cout' => $cout] = $levier->evaluer($code, $code === Levier::RATELIER ? 0.0 : $quantite, (float) $parametre, $famille, [
            'jours' => $jours, 'besoin_jour_t_ms' => $jours > 0 ? $calcul['centrale']->besoinStock / $jours : 0.0,
            'besoin_ration_t_ms' => $calcul['besoin_ration_t_ms'], 'pertes_distribution' => $calcul['pertes_moyennes'],
        ]);

        $attributs = [
            'scenario_id' => $scenario->id, 'levier_code' => $code, 'famille_code' => $famille, 'famille_source_code' => $code === Levier::REAFFECTATION ? $d['famille_source_code'] : null,
            'quantite' => $code === Levier::RATELIER ? $parametre : $quantite, 'unite' => self::UNITES[$code], 'parametre' => $parametre,
            'prix_unitaire' => in_array($code, [Levier::ACHAT, Levier::CONCENTRE], true) ? $parametre : null,
            'date_effet' => ($d['date_effet'] ?? null) ?: $inventaire->date_debut->toDateString(), 'gain_t_ms' => $gain, 'cout_eur' => $cout,
            'commentaire' => ($d['commentaire'] ?? null) ?: $this->consequence($code, $d['famille_source_code'] ?? null, $famille),
        ];

        if ($ligne === null) {
            return LevierLigne::query()->create([...$attributs, 'ordre' => (int) LevierLigne::query()->where('scenario_id', $scenario->id)->max('ordre') + 1]);
        }
        $ligne->update($attributs);

        return $ligne;
    }

    public function supprimerLigne(LevierLigne $ligne): void
    {
        $this->exigerCourant($this->inventaire(Scenario::query()->withoutGlobalScopes()->findOrFail($ligne->scenario_id)));
        $ligne->delete();
    }

    /**
     * Évaluation d'un scénario (RC-13.4) : bilan avec les gains datés, coût total, lots d'animaux en déficit
     * nutritionnel, points de vigilance.
     *
     * @return array{scenario: Scenario, bilan: Bilan, cout: float, lots_deficit: int, vigilance: list<string>, lignes: Collection<int, LevierLigne>}
     */
    public function evaluer(Scenario $scenario, bool $publiesSeulement = false): array
    {
        $inventaire = $this->inventaire($scenario);
        $bilan = $this->bilans->enregistrer($inventaire, $scenario);
        $lignes = $this->lignes($scenario);
        $gds = $this->gds($inventaire);
        $repere = $this->referentiels->parametre('autonomie.repere_couverture', $gds);
        $residuels = collect($this->residuels($inventaire, $publiesSeulement));

        $vigilance = [];
        if ($bilan->date_rupture !== null) {
            $vigilance[] = 'Rupture encore prévue le '.$bilan->date_rupture->format('d/m/Y').'.';
        }
        if ($repere !== null && (float) $bilan->couverture < $repere) {
            $vigilance[] = 'Couverture sous le repère de '.round(100 * $repere).' % : un achat extérieur reste nécessaire.';
        }
        foreach ($lignes as $l) {
            $prixDefaut = $l->famille_code === null ? null : $this->parametreDefaut($l->levier_code, $l->famille_code, $gds, $inventaire->elevage_id);
            if (in_array($l->levier_code, [Levier::ACHAT, Levier::CONCENTRE], true) && $prixDefaut !== null && abs((float) $l->prix_unitaire - $prixDefaut) < 1e-9) {
                $vigilance[] = 'Prix indicatif à valider : '.mb_strtolower(self::LIBELLES[$l->levier_code]).' à '.round($prixDefaut).' €/t MS.';
            }
            if ($l->levier_code === Levier::CONCENTRE) {
                $vigilance[] = 'Taux de substitution du concentré : référence à vérifier.';
            }
            if ($l->levier_code === Levier::ACHAT) {
                $vigilance[] = 'Fourrage acheté : valeurs nutritionnelles inconnues, à faire analyser.';
            }
        }

        return [
            'scenario' => $scenario, 'bilan' => $bilan, 'cout' => (float) $lignes->sum(fn (LevierLigne $l) => (float) $l->cout_eur),
            'lots_deficit' => $residuels->pluck('lot')->unique()->count(), 'vigilance' => array_values(array_unique($vigilance)), 'lignes' => $lignes,
        ];
    }

    /**
     * Comparaison côte à côte (EF-M7-02, T-23) : quatre scénarios au plus.
     *
     * @param  list<int>  $ids
     * @return list<array{scenario: Scenario, bilan: Bilan, cout: float, lots_deficit: int, vigilance: list<string>, lignes: Collection<int, LevierLigne>}>
     */
    public function comparer(Inventaire $inventaire, array $ids, bool $publiesSeulement = false): array
    {
        if (count($ids) > self::MAX_COMPARAISON) {
            throw new SaisieRefusee('Comparer quatre scénarios au plus.');
        }

        return Scenario::query()->where('inventaire_id', $inventaire->id)->whereIn('id', $ids)->orderBy('id')->get()
            ->map(fn (Scenario $s) => $this->evaluer($s, $publiesSeulement))->values()->all();
    }

    /**
     * Retient un scénario (EF-M7-05, T-24) : le scénario retenu précédent repasse en brouillon et ses lots et mouvements
     * d'achat sont retirés ; chaque ligne d'achat crée un lot « achat » de la famille (stock initial nul, consommé après
     * les lots existants) et un mouvement d'achat daté de la livraison, lié à la ligne. Le technicien est prévenu.
     */
    public function retenir(Scenario $scenario, User $auteur): Scenario
    {
        $inventaire = $this->inventaire($scenario);
        $this->exigerCourant($inventaire);

        DB::transaction(function () use ($scenario, $inventaire) {
            foreach (Scenario::query()->where('inventaire_id', $inventaire->id)->where('statut', Scenario::STATUT_RETENU)->whereKeyNot($scenario->id)->get() as $ancien) {
                $this->retirerAchats($ancien);
                $ancien->update(['statut' => Scenario::STATUT_BROUILLON, 'retenu_le' => null]);
            }
            $this->retirerAchats($scenario);

            foreach ($this->lignes($scenario)->where('levier_code', Levier::ACHAT) as $ligne) {
                $lot = LotStock::query()->create([
                    'inventaire_id' => $inventaire->id, 'elevage_id' => $inventaire->elevage_id, 'famille_code' => $ligne->famille_code,
                    'libelle' => 'Achat — '.DB::table('ref.famille_fourrage')->where('code', $ligne->famille_code)->value('libelle'),
                    'forme' => 'vrac', 'pertes_pct' => 0, 'stock_brut_t_ms' => 0, 'stock_utile_t_ms' => 0, 'incertitude_rel' => 0,
                    'priorite_consommation' => self::PRIORITE_ACHAT, 'scenario_ligne_id' => $ligne->id,
                    'commentaire' => 'Lot créé par le scénario « '.$scenario->libelle.' » ; à compléter à la livraison.',
                ]);
                Mouvement::query()->create([
                    'lot_stock_id' => $lot->id, 'date_mouvement' => ($ligne->date_effet ?? $inventaire->date_debut)->toDateString(), 'type' => 'achat',
                    'quantite_t_ms' => $ligne->gain_t_ms, 'prix_eur' => $ligne->cout_eur, 'scenario_ligne_id' => $ligne->id,
                    'motif' => 'Achat prévu par le scénario « '.$scenario->libelle.' »',
                ]);
            }

            $scenario->update(['statut' => Scenario::STATUT_RETENU, 'retenu_le' => now()]);
        });

        $this->prevenirTechnicien($scenario->refresh(), $inventaire, $auteur);

        return $scenario;
    }

    public function supprimerScenario(Scenario $scenario): void
    {
        $scenario->delete();
    }

    /** @return array<string, float|null> prix indicatif par famille (celui de l'élevage s'il a été modifié, E16) */
    public function prixFamilles(?int $gds, ?string $elevageId = null): array
    {
        return array_map(fn (string $cle) => $this->parametresElevage->valeur($cle, $elevageId, $gds), self::PRIX);
    }

    /** Paramètre par défaut d'un levier (prix, perte par UGB, t MS par génisse) ; null s'il se saisit. */
    public function parametreDefaut(string $code, ?string $famille, ?int $gds, ?string $elevageId = null): ?float
    {
        return match ($code) {
            Levier::ACHAT => isset(self::PRIX[$famille ?? '']) ? $this->parametresElevage->valeur(self::PRIX[$famille], $elevageId, $gds) : null,
            Levier::CONCENTRE => $this->parametresElevage->valeur(self::PRIX['CONCENTRE'], $elevageId, $gds),
            Levier::DECAPITALISATION => $this->referentiels->parametre('decapitalisation.perte_eur_ugb', $gds),
            Levier::GENISSES => $this->referentiels->parametre('genisse.t_ms_par_tete', $gds),
            default => null,
        };
    }

    /** @return Collection<int, Scenario> */
    public function scenarios(Inventaire $inventaire): Collection
    {
        return Scenario::query()->where('inventaire_id', $inventaire->id)->orderByRaw("statut = 'retenu' DESC")->orderBy('id')->get();
    }

    /** @return Collection<int, LevierLigne> */
    public function lignes(Scenario $scenario): Collection
    {
        return LevierLigne::query()->withoutGlobalScopes()->where('scenario_id', $scenario->id)->orderBy('ordre')->get();
    }

    public function gds(Inventaire $inventaire): ?int
    {
        $gds = DB::table('fourrage.elevage')->where('id', $inventaire->elevage_id)->value('gds_id');

        return $gds === null ? null : (int) $gds;
    }

    private function parametresLeviers(?int $gds): ParametresLeviers
    {
        return new ParametresLeviers(
            array_map(fn (string $cle) => (float) $this->referentiels->parametre($cle, $gds), self::SUBSTITUTION),
            (float) $this->referentiels->parametre('ugb.kg_ms_par_ugb_jour', $gds),
        );
    }

    /** @param  array<string, mixed>  $calcul */
    private function familleParDefaut(array $calcul): string
    {
        ['deficits' => $deficits] = $this->proposition->diagnostic($calcul['centrale']->besoinParFamille, $calcul['securites_famille'], $calcul['stocks_famille']);
        arsort($deficits);

        return (string) (array_key_first($deficits) ?? array_key_first($calcul['stocks_famille']) ?? 'FOIN');
    }

    /**
     * Quantité de la famille source au plus substituable à la famille cible (RC-13.2 : part maximale de la ration des
     * lots aux stades autorisés qui consomment la cible), en t MS sur la période.
     */
    private function plafond(Inventaire $inventaire, string $cible, float $part, ?string $stades): float
    {
        $stades = $stades === null ? null : str_getcsv(trim($stades, '{}'));
        $total = 0.0;
        $lots = LotAnimaux::query()->withoutGlobalScopes()->where('inventaire_id', $inventaire->id)->whereNull('archive_le')
            ->with(['phases' => fn ($q) => $q->withoutGlobalScopes(), 'phases.ration' => fn ($q) => $q->withoutGlobalScopes(), 'phases.ration.composants' => fn ($q) => $q->withoutGlobalScopes()])->get();
        foreach ($lots as $lot) {
            foreach ($lot->phases as $phase) {
                $composants = collect($phase->ration->composants ?? []);
                $consommeCible = $composants->contains(fn ($c) => $c->famille_code === $cible
                    || ($c->source === 'lot_stock' && DB::table('stock.lot_stock')->where('id', $c->lot_stock_id)->value('famille_code') === $cible));
                if (! $consommeCible || ($stades !== null && ! in_array($phase->stade, $stades, true))) {
                    continue;
                }
                $debut = max($phase->date_debut, $inventaire->date_debut);
                $fin = min($phase->date_fin, $inventaire->date_fin);
                $jours = $fin->lt($debut) ? 0 : (int) $debut->diffInDays($fin) + 1;
                $total += $phase->effectif * (float) $composants->sum(fn ($c) => (float) $c->kg_ms) * $jours / 1000;
            }
        }

        return $part * $total;
    }

    /**
     * Étapes 3 et 4 de RC-13.2 : besoin résiduel par animal et par jour, sans quantité de concentré ni de minéral.
     *
     * @return list<array{lot: string, phase: string, etape: int, element: string, residuel: float, unite: string}>
     */
    private function residuels(Inventaire $inventaire, bool $publiesSeulement): array
    {
        $residuels = [];
        $stades = Troupeau::stades();
        $lots = LotAnimaux::query()->withoutGlobalScopes()->where('inventaire_id', $inventaire->id)->whereNull('archive_le')->orderBy('id')
            ->with(['phases' => fn ($q) => $q->withoutGlobalScopes()->orderBy('date_debut')])->get();
        foreach ($lots as $lot) {
            foreach ($lot->phases as $phase) {
                $a = $this->analyse->analyser($phase, $lot, $publiesSeulement);
                if (! $a['complet']) {
                    continue;
                }
                foreach ($a['elements'] as $e) {
                    $r = $e['resultat'];
                    if ($r->residuel !== null && $r->residuel > 1e-9 && in_array($r->classe, [ClasseCouverture::Deficit, ClasseCouverture::Limite], true)) {
                        $residuels[] = [
                            'lot' => $lot->libelle, 'phase' => ($stades[$phase->stade] ?? $phase->stade).' du '.$phase->date_debut->format('d/m/Y'),
                            'etape' => in_array($e['code'], self::ENERGIE_AZOTE, true) ? 3 : 4, 'element' => $e['libelle'], 'residuel' => $r->residuel,
                            'unite' => self::uniteJournaliere($e['unite']),
                        ];
                    }
                }
            }
        }

        return $residuels;
    }

    /** « g/kg MS » → « g », « UFL/kg MS » → « UFL » : l'unité du besoin résiduel par animal et par jour. */
    public static function uniteJournaliere(string $unite): string
    {
        return trim(explode('/', $unite)[0]) ?: $unite;
    }

    /** @param  array{lot: string, phase: string, etape: int, element: string, residuel: float, unite: string}  $r */
    public function texteResiduel(array $r): string
    {
        $valeur = number_format($r['residuel'], 2, ',', ' ');

        return $r['lot'].' ('.$r['phase'].') : il manque '.$valeur.' '.$r['unite'].' de '.$r['element'].' par animal et par jour'
            .($r['etape'] === 3 ? ' ; un concentré de la catégorie adaptée est à définir avec le technicien (tables d\'aliments non chargées).'
                : ' ; la complémentation minérale est à définir avec le technicien.');
    }

    /** Conséquence nutritionnelle affichée avec chaque levier (EF-M7-04). */
    public function consequence(string $code, ?string $source, ?string $famille): string
    {
        return match (true) {
            $code === Levier::REAFFECTATION && $source === 'PAILLE' => 'La paille à la place du foin baisse l\'UFL moyenne de la ration : couverture à vérifier sur les lots concernés.',
            $code === Levier::REAFFECTATION => 'Substitution à 1:1 en MS : les teneurs de la ration suivent celles des lots réaffectés.',
            $code === Levier::ACHAT => 'Valeurs nutritionnelles inconnues tant que le lot acheté n\'est pas analysé.',
            $code === Levier::CONCENTRE => 'Le concentré se substitue en partie au fourrage et relève la densité énergétique de la ration.',
            $code === Levier::RATELIER => 'Ration inchangée : seules les pertes à la distribution baissent.',
            $code === Levier::PATURAGE => 'Herbe pâturée : couverture à vérifier sur la période allongée.',
            in_array($code, [Levier::DECAPITALISATION, Levier::GENISSES], true) => 'Ration des animaux restants inchangée.',
            default => 'Effet sur la ration à préciser.',
        };
    }

    private function retirerAchats(Scenario $scenario): void
    {
        $ids = LevierLigne::query()->withoutGlobalScopes()->where('scenario_id', $scenario->id)->pluck('id');
        Mouvement::query()->withoutGlobalScopes()->whereIn('scenario_ligne_id', $ids)->get()->each->delete();
        LotStock::query()->withoutGlobalScopes()->whereIn('scenario_ligne_id', $ids)->get()->each->delete();
    }

    private function prevenirTechnicien(Scenario $scenario, Inventaire $inventaire, User $auteur): void
    {
        $elevage = Elevage::query()->withoutGlobalScopes()->findOrFail($inventaire->elevage_id);
        $elevage->setAttribute('raison_sociale', DB::table('prive.elevage_identite')->where('elevage_id', $elevage->id)->value('raison_sociale'));
        $variables = ['elevage' => ElevageCourant::libelle($elevage), 'scenario' => $scenario->libelle, 'auteur' => $auteur->nomComplet(),
            'date' => Carbon::now()->format('d/m/Y')];
        $objet = $this->textes->texte('notification.scenario_retenu.objet', $variables);
        $lignes = array_map(fn (int $n) => $this->textes->texte("notification.scenario_retenu.ligne{$n}", $variables), [1, 2, 3]);

        foreach ($this->alertes->techniciens($elevage) as $technicien) {
            if ($technicien->id !== $auteur->id) {
                $this->notifications->envoyer($technicien, NotificationService::EMAIL, 'scenario_retenu', $objet, $lignes,
                    route('scenarios.voir', [$elevage->id, $scenario->id]), $elevage->id, 'plan.scenario:'.$scenario->id);
            }
        }
    }

    private function inventaire(Scenario $scenario): Inventaire
    {
        return Inventaire::query()->withoutGlobalScopes()->findOrFail($scenario->inventaire_id);
    }

    private function exigerCourant(Inventaire $inventaire): void
    {
        if ($inventaire->statut !== Inventaire::STATUT_COURANT) {
            throw new SaisieRefusee('Les scénarios se construisent sur l\'inventaire en cours.');
        }
    }
}
