<?php

namespace App\Livewire\Stocks;

use App\Domain\Stocks\EquationDensite;
use App\Livewire\Dossier\Lecture\Concerns\VoletDossier;
use App\Models\Fourrage\Elevage;
use App\Models\Stock\Inventaire;
use App\Models\Stock\LotStock;
use App\Models\Stock\Mouvement;
use App\Services\Cloisonnement\RegleStatut;
use App\Services\Dossier\SaisieRefusee;
use App\Services\ElevageCourant;
use App\Services\Stocks\StocksService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * E12 — saisie d'un inventaire (EF-M4-02 à EF-M4-06), conçue d'abord pour le téléphone (EF-GEN-07) : tableau de bord
 * par famille, lots (silos, balles, vrac) avec leurs indicateurs analysé / saisi / par défaut et pesé / estimé
 * (EF-GEN-05), aperçu du calcul pendant la saisie, mouvements. Chaque action est autorisée côté serveur (ENF-SEC-04).
 */
class SaisieInventaire extends Component
{
    use VoletDossier;

    #[Locked]
    public int $inventaireId;

    /** Lot en cours de modification (null : nouveau lot). */
    #[Locked]
    public ?int $lotId = null;

    /** @var array<string, mixed> champs du formulaire du lot */
    public array $lot = [];

    /** @var array<int, array{type: string, date: string, quantite: string|null, prix?: string|null, motif?: string|null}> nouveau mouvement par lot (prix et motif facultatifs) */
    public array $mouvements = [];

    public function mount(Elevage $elevage, Inventaire $inventaire): void
    {
        abort_unless($inventaire->elevage_id === $elevage->id, 404);
        $this->authorize('view', $inventaire);
        $this->monterVolet($elevage);
        $this->inventaireId = $inventaire->id;
        $this->nouveauLot();
    }

    public function nouveauLot(string $forme = 'balle_ronde'): void
    {
        $this->lotId = null;
        $this->lot = [
            'forme' => $forme, 'libelle' => '', 'type_fourrage_code' => '', 'famille_code' => 'FOIN', 'prelevement_id' => '',
            'ms_pct' => '', 'mode_stockage' => $forme === 'silo' ? StocksService::PERTES_SILO[0] : 'pertes.stockage.sous_toit', 'pertes_pct' => '',
            'nombre' => '', 'poids_brut_kg' => '', 'poids_mesure' => false, 'volume_m3' => '',
            'ouvrage' => 'couloir', 'l1_m' => '', 'l2_m' => '', 'largeur_m' => '', 'hauteur_m' => '', 'nb_mesures_hauteur' => '',
            'date_fermeture' => '', 'methode_densite' => 'equation', 'equation_code' => 'mais_couloir', 'densite_kg_ms_m3' => '',
            'amidon_pct' => '', 'grain_pct' => '', 'date_recolte' => '', 'commentaire' => '',
        ];
        $this->resetErrorBag();
    }

    public function modifierLot(int $id): void
    {
        $lot = $this->lotDeLInventaire($id);
        $this->authorize('update', $lot);
        $this->lotId = $lot->id;
        $this->lot = array_map(fn ($v) => $v ?? '', $lot->only(array_keys($this->lot)));
        $this->lot['poids_mesure'] = $lot->poids_mesure;
        $this->lot['pertes_pct'] = (string) round(100 * (float) $lot->pertes_pct, 2);
        foreach (['date_fermeture', 'date_recolte'] as $date) {
            $this->lot[$date] = $lot->{$date}?->toDateString() ?? '';
        }
    }

    public function enregistrerLot(StocksService $service): void
    {
        $inventaire = $this->inventaire();
        $lot = $this->lotId === null ? null : $this->lotDeLInventaire($this->lotId);
        $this->authorize($lot === null ? 'create' : 'update', $lot ?? [Inventaire::class, $this->elevage()]);
        if ($lot === null) {
            $this->authorize('update', $inventaire);
        }
        $this->validate($this->regles(), [], $this->attributs());

        try {
            $service->enregistrerLot($inventaire, $this->donneesLot(), $lot, ! $this->agent());
            session()->flash('status', $lot === null ? 'Lot ajouté à l\'inventaire.' : 'Lot mis à jour.');
            $this->nouveauLot($this->lot['forme']);
        } catch (SaisieRefusee $e) {
            $this->addError('lot', $e->getMessage());
        }
    }

    public function supprimerLot(int $id, StocksService $service): void
    {
        $lot = $this->lotDeLInventaire($id);
        $this->authorize('delete', $lot);
        $service->supprimerLot($lot);
        session()->flash('status', 'Lot supprimé.');
    }

    public function ajouterMouvement(int $id, StocksService $service): void
    {
        $lot = $this->lotDeLInventaire($id);
        $this->authorize('update', $lot);
        $this->validate([
            "mouvements.{$id}.type" => ['required', Rule::in(Mouvement::TYPES)],
            "mouvements.{$id}.date" => ['required', 'date'],
            "mouvements.{$id}.quantite" => ['required', 'numeric'],
            "mouvements.{$id}.prix" => ['nullable', 'numeric', 'min:0'],
            "mouvements.{$id}.motif" => ['nullable', 'string', 'max:300'],
        ], [], ["mouvements.{$id}.quantite" => 'quantité', "mouvements.{$id}.date" => 'date']);
        $m = $this->mouvements[$id];

        try {
            $service->ajouterMouvement($lot, $m['type'], $m['date'], (float) $m['quantite'], self::nombre($m['prix'] ?? null), $m['motif'] ?? null);
            unset($this->mouvements[$id]);
        } catch (SaisieRefusee $e) {
            $this->addError("mouvements.{$id}.quantite", $e->getMessage());
        }
    }

    public function supprimerMouvement(int $id, StocksService $service): void
    {
        $mouvement = Mouvement::query()->whereIn('lot_stock_id', LotStock::query()->where('inventaire_id', $this->inventaireId)->select('id'))->findOrFail($id);
        $this->authorize('delete', $mouvement);
        $service->supprimerMouvement($mouvement);
    }

    public function clore(StocksService $service): void
    {
        $inventaire = $this->inventaire();
        $this->authorize('update', $inventaire);
        $service->clore($inventaire);
        session()->flash('status', 'Inventaire clos : il reste consultable, une nouvelle saisie demande un nouvel inventaire.');
    }

    public function render(StocksService $service): View
    {
        $inventaire = $this->inventaire();
        $elevage = $this->elevage();
        $lots = LotStock::query()->where('inventaire_id', $inventaire->id)->with(['mouvements' => fn ($q) => $q->orderBy('date_mouvement')])->orderBy('famille_code')->orderBy('id')->get();

        return view('livewire.stocks.saisie-inventaire', [
            'elevage' => $elevage,
            'libelleElevage' => ElevageCourant::libelle(app(ElevageCourant::class)->accessibles()->firstWhere('id', $elevage->id) ?? $elevage),
            'inventaire' => $inventaire,
            'tableau' => $service->tableauDeBord($inventaire),
            'lots' => $lots,
            'apercu' => $this->apercu($service),
            'peutSaisir' => Gate::allows('update', $inventaire),
            'peutModifier' => fn (LotStock $l) => Gate::allows('update', $l),
            'familles' => DB::table('ref.famille_fourrage')->where('code', '!=', 'CONCENTRE')->pluck('libelle', 'code'),
            'typesFourrage' => DB::table('ref.type_fourrage')->where('code', '!=', 'CONCENTRE')->orderBy('libelle')->pluck('libelle', 'code'),
            'prelevements' => $service->prelevementsReliables($elevage, ! $this->agent()),
            'pertes' => $this->pertesProposees($service, $elevage),
            'equations' => collect(EquationDensite::cases())->mapWithKeys(fn ($e) => [$e->value => $e->libelle()]),
            'types' => Inventaires::types(),
            'typesMouvement' => ['achat' => 'Achat', 'vente' => 'Vente', 'recomptage' => 'Re-comptage', 'ajustement' => 'Ajustement'],
        ])->layout('components.layouts.espace', ['titre' => 'Inventaire du '.$inventaire->date_inventaire->format('d/m/Y')]);
    }

    /**
     * Aperçu du calcul du lot en cours de saisie (calcul en ligne, EF-M4-02), sans enregistrement.
     *
     * @return array{stock_utile_t_ms: float, stock_brut_t_ms: float, volume_calc_m3: float|null, densite_calc: float|null, ms_pct: float, ms_origine: string, pertes_pct: float}|string|null
     */
    private function apercu(StocksService $service): array|string|null
    {
        $donnees = $this->donneesLot();
        $requis = $donnees['forme'] === 'silo' ? ['l1_m', 'largeur_m', 'hauteur_m'] : [$donnees['forme'] === 'vrac' ? 'volume_m3' : 'nombre'];
        foreach ($requis as $champ) {
            if ($donnees[$champ] === null) {
                return null;
            }
        }
        if ($donnees['forme'] !== 'vrac' && $donnees['forme'] !== 'silo' && $donnees['poids_brut_kg'] === null) {
            return null;
        }

        try {
            return $service->apercu($this->inventaire(), $donnees, ! $this->agent());
        } catch (SaisieRefusee $e) {
            return $e->getMessage();
        }
    }

    /** @return array<string, mixed> */
    private function donneesLot(): array
    {
        $l = $this->lot;
        $silo = $l['forme'] === 'silo';
        $vrac = $l['forme'] === 'vrac';

        return [
            'libelle' => trim((string) $l['libelle']), 'forme' => $l['forme'],
            'type_fourrage_code' => $l['type_fourrage_code'] ?: null, 'famille_code' => $l['famille_code'],
            'prelevement_id' => $l['prelevement_id'] ? (int) $l['prelevement_id'] : null,
            'ms_pct' => self::nombre($l['ms_pct']), 'mode_stockage' => $l['mode_stockage'],
            'pertes_pct' => ($p = self::nombre($l['pertes_pct'])) === null ? null : $p / 100,
            'date_recolte' => $l['date_recolte'] ?: null, 'commentaire' => trim((string) $l['commentaire']) ?: null,
            'nombre' => $silo || $vrac ? null : self::nombre($l['nombre']),
            'poids_brut_kg' => $silo ? null : self::nombre($l['poids_brut_kg']),
            'poids_mesure' => ! $silo && ! $vrac && (bool) $l['poids_mesure'],
            'volume_m3' => $vrac ? self::nombre($l['volume_m3']) : null,
            'ouvrage' => $silo ? $l['ouvrage'] : null,
            'l1_m' => $silo ? self::nombre($l['l1_m']) : null, 'l2_m' => $silo ? (self::nombre($l['l2_m']) ?? 0.0) : null,
            'largeur_m' => $silo ? self::nombre($l['largeur_m']) : null, 'hauteur_m' => $silo ? self::nombre($l['hauteur_m']) : null,
            'nb_mesures_hauteur' => $silo && $l['nb_mesures_hauteur'] !== '' ? (int) $l['nb_mesures_hauteur'] : null,
            'date_fermeture' => $silo && $l['date_fermeture'] ? $l['date_fermeture'] : null,
            'methode_densite' => $silo ? $l['methode_densite'] : null,
            'equation_code' => $silo && $l['methode_densite'] === 'equation' ? $l['equation_code'] : null,
            'densite_kg_ms_m3' => $silo && $l['methode_densite'] !== 'equation' ? self::nombre($l['densite_kg_ms_m3']) : null,
            'amidon_pct' => $silo ? self::nombre($l['amidon_pct']) : null, 'grain_pct' => $silo ? self::nombre($l['grain_pct']) : null,
        ];
    }

    /** @return array<string, list<string>> */
    private function regles(): array
    {
        $positif = ['nullable', 'numeric', 'gt:0'];
        $silo = $this->lot['forme'] === 'silo';

        return [
            'lot.libelle' => ['required', 'string', 'max:200'],
            'lot.forme' => ['required', Rule::in(LotStock::FORMES)],
            'lot.famille_code' => ['required', 'string'],
            'lot.ms_pct' => ['nullable', 'numeric', 'gt:0', 'max:100'],
            'lot.pertes_pct' => ['nullable', 'numeric', 'min:0', 'lt:100'],
            'lot.nombre' => $this->lot['forme'] === 'vrac' || $silo ? $positif : ['required', 'numeric', 'gt:0'],
            'lot.poids_brut_kg' => $positif,
            'lot.volume_m3' => $this->lot['forme'] === 'vrac' ? ['required', 'numeric', 'gt:0'] : $positif,
            'lot.l1_m' => $silo ? ['required', 'numeric', 'gt:0'] : $positif,
            'lot.l2_m' => ['nullable', 'numeric', 'min:0'],
            'lot.largeur_m' => $silo ? ['required', 'numeric', 'gt:0'] : $positif,
            'lot.hauteur_m' => $silo ? ['required', 'numeric', 'gt:0'] : $positif,
            'lot.ouvrage' => $silo ? ['required', Rule::in(LotStock::OUVRAGES)] : ['nullable'],
            'lot.methode_densite' => $silo ? ['required', Rule::in(LotStock::METHODES_DENSITE)] : ['nullable'],
            'lot.date_fermeture' => ['nullable', 'date'],
            'lot.date_recolte' => ['nullable', 'date'],
        ];
    }

    /** @return array<string, string> */
    private function attributs(): array
    {
        return [
            'lot.libelle' => 'désignation', 'lot.ms_pct' => 'matière sèche', 'lot.pertes_pct' => 'pertes', 'lot.nombre' => 'nombre',
            'lot.poids_brut_kg' => 'poids', 'lot.volume_m3' => 'volume', 'lot.l1_m' => 'longueur L1', 'lot.largeur_m' => 'largeur',
            'lot.hauteur_m' => 'hauteur', 'lot.famille_code' => 'famille',
        ];
    }

    /** @return array<string, array{libelle: string, defaut: float}> */
    private function pertesProposees(StocksService $service, Elevage $elevage): array
    {
        $libelles = DB::table('app.parametre_defaut')->whereIn('cle', [...StocksService::PERTES_SILO, ...StocksService::PERTES_BALLES])->pluck('libelle', 'cle');
        $cles = $this->lot['forme'] === 'silo' ? StocksService::PERTES_SILO : StocksService::PERTES_BALLES;

        return collect($cles)->mapWithKeys(fn (string $cle) => [$cle => [
            'libelle' => (string) ($libelles[$cle] ?? $cle), 'defaut' => $service->pertesDefaut($cle, $elevage->gds_id, $elevage->id),
        ]])->all();
    }

    private function inventaire(): Inventaire
    {
        return Inventaire::query()->where('elevage_id', $this->elevageId)->findOrFail($this->inventaireId);
    }

    private function lotDeLInventaire(int $id): LotStock
    {
        return LotStock::query()->where('inventaire_id', $this->inventaireId)->findOrFail($id);
    }

    private function agent(): bool
    {
        return app(RegleStatut::class)->voitTousLesStatuts($this->lecteur());
    }

    private static function nombre(mixed $valeur): ?float
    {
        return $valeur === null || $valeur === '' ? null : (float) str_replace(',', '.', (string) $valeur);
    }
}
