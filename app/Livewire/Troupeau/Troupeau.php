<?php

namespace App\Livewire\Troupeau;

use App\Livewire\Dossier\Lecture\Concerns\VoletDossier;
use App\Models\Dossier\EtiquetteAliment;
use App\Models\Fourrage\Elevage;
use App\Models\Plan\LotAnimaux;
use App\Models\Plan\Phase;
use App\Models\Plan\Ration;
use App\Models\Plan\RationComposant;
use App\Models\Stock\Inventaire;
use App\Models\Stock\LotStock;
use App\Services\Dossier\SaisieRefusee;
use App\Services\ElevageCourant;
use App\Services\Troupeau\TroupeauService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * E13 — troupeau : lots d'animaux, phases, rations (EF-M5-01 à EF-M5-06) pour l'inventaire courant de l'élevage (ou un
 * inventaire choisi) ; besoins par les deux voies (RC-8) ; copie d'un inventaire précédent. Saisie conçue pour le
 * téléphone ; chaque action est autorisée côté serveur (PlanPolicy, ENF-SEC-04).
 */
class Troupeau extends Component
{
    use VoletDossier;

    #[Url]
    public ?int $inventaire = null;

    /** @var array{libelle: string, categorie_code: string, coeff_ugb: string, poids_vif_kg: string, production_valeur: string, production_unite: string} */
    public array $nouveauLot = ['libelle' => '', 'categorie_code' => 'VA', 'coeff_ugb' => '', 'poids_vif_kg' => '', 'production_valeur' => '', 'production_unite' => ''];

    /** @var array<int, array{stade?: string, debut?: string, fin?: string, effectif?: string}> nouvelle phase par lot */
    public array $phases = [];

    /** @var array<int, array{source?: string, reference?: string, brut?: string, kg_ms?: string}> nouveau composant par ration */
    public array $composants = [];

    /** @var array<int, array{mode?: string, pertes?: string}> distribution par ration */
    public array $distributions = [];

    public ?string $inventaireSource = null;

    public function mount(Elevage $elevage): void
    {
        $this->monterVolet($elevage);
        $this->authorize('viewAny', LotAnimaux::class);
    }

    public function creerLot(TroupeauService $service): void
    {
        $inventaire = $this->inventaireChoisi() ?? abort(404);
        $this->authorize('create', [LotAnimaux::class, $this->elevage()]);
        $this->validate([
            'nouveauLot.libelle' => ['required', 'string', 'max:200'],
            'nouveauLot.categorie_code' => ['required', Rule::in(DB::table('ref.categorie_animale')->pluck('code')->all())],
            'nouveauLot.coeff_ugb' => ['nullable', 'numeric', 'gt:0'],
            'nouveauLot.poids_vif_kg' => ['nullable', 'numeric', 'gt:0'],
            'nouveauLot.production_valeur' => ['nullable', 'numeric', 'min:0'],
            'nouveauLot.production_unite' => ['nullable', Rule::in(LotAnimaux::PRODUCTIONS)],
        ], [], ['nouveauLot.libelle' => 'libellé', 'nouveauLot.coeff_ugb' => 'coefficient UGB']);
        $l = $this->nouveauLot;

        $this->executer(fn () => $service->creerLot($this->elevage(), $inventaire, [
            'libelle' => trim($l['libelle']), 'categorie_code' => $l['categorie_code'], 'coeff_ugb' => self::nombre($l['coeff_ugb']),
            'poids_vif_kg' => self::nombre($l['poids_vif_kg']), 'production_valeur' => self::nombre($l['production_valeur']),
            'production_unite' => $l['production_unite'] ?: null,
        ]), 'Lot d\'animaux créé.', fn () => $this->reset('nouveauLot'));
    }

    public function archiverLot(int $id, TroupeauService $service): void
    {
        $lot = $this->lotDeLInventaire($id);
        $this->authorize('update', $lot);
        $service->archiverLot($lot);
    }

    public function ajouterPhase(int $lotId, TroupeauService $service): void
    {
        $lot = $this->lotDeLInventaire($lotId);
        $this->authorize('update', $lot);
        $this->validate([
            "phases.{$lotId}.stade" => ['required', Rule::in(Phase::STADES)],
            "phases.{$lotId}.debut" => ['required', 'date'],
            "phases.{$lotId}.fin" => ['required', 'date', "after_or_equal:phases.{$lotId}.debut"],
            "phases.{$lotId}.effectif" => ['required', 'integer', 'min:0'],
        ], [], ["phases.{$lotId}.debut" => 'début', "phases.{$lotId}.fin" => 'fin', "phases.{$lotId}.effectif" => 'effectif']);
        $p = $this->phases[$lotId];

        $this->executer(fn () => $service->ajouterPhase($lot, (string) $p['stade'], (string) $p['debut'], (string) $p['fin'], (int) $p['effectif']),
            null, fn () => $this->phases[$lotId] = [], "phases.{$lotId}.debut");
    }

    public function supprimerPhase(int $id, TroupeauService $service): void
    {
        $phase = Phase::query()->whereIn('lot_animaux_id', $this->lotsQuery()->select('id'))->findOrFail($id);
        $this->authorize('delete', $phase);
        $service->supprimerPhase($phase);
    }

    public function enregistrerDistribution(int $rationId, TroupeauService $service): void
    {
        $ration = $this->rationDeLInventaire($rationId);
        $this->authorize('update', $ration);
        $this->validate(["distributions.{$rationId}.pertes" => ['nullable', 'numeric', 'min:0', 'lt:100']]);
        $d = $this->distributions[$rationId] ?? [];
        $pertes = self::nombre($d['pertes'] ?? null);

        $this->executer(fn () => $service->enregistrerDistribution($ration, (string) ($d['mode'] ?? $ration->mode_distribution), $pertes === null ? null : $pertes / 100),
            null, fn () => $this->distributions[$rationId] = [], "distributions.{$rationId}.pertes");
    }

    public function ajouterComposant(int $rationId, TroupeauService $service): void
    {
        $ration = $this->rationDeLInventaire($rationId);
        $this->authorize('update', $ration);
        $this->validate([
            "composants.{$rationId}.source" => ['required', Rule::in(['lot_stock', 'famille_stock', 'etiquette', 'paturage'])],
            "composants.{$rationId}.brut" => ['nullable', 'numeric', 'min:0'],
            "composants.{$rationId}.kg_ms" => ['nullable', 'numeric', 'min:0'],
        ], [], ["composants.{$rationId}.brut" => 'quantité brute', "composants.{$rationId}.kg_ms" => 'quantité en kg MS']);
        $c = $this->composants[$rationId];
        $reference = $c['reference'] ?? '';

        $this->executer(fn () => $service->ajouterComposant($ration, [
            'source' => (string) $c['source'],
            'lot_stock_id' => $c['source'] === 'lot_stock' ? (int) $reference : null,
            'famille_code' => $c['source'] === 'famille_stock' ? $reference : null,
            'etiquette_id' => $c['source'] === 'etiquette' ? (int) $reference : null,
            'quantite_brute_kg' => self::nombre($c['brut'] ?? null), 'kg_ms' => self::nombre($c['kg_ms'] ?? null),
        ]), null, fn () => $this->composants[$rationId] = [], "composants.{$rationId}.kg_ms");
    }

    public function supprimerComposant(int $id, TroupeauService $service): void
    {
        $composant = RationComposant::query()->whereIn('ration_id', Ration::query()->whereIn('phase_id', Phase::query()->whereIn('lot_animaux_id', $this->lotsQuery()->select('id'))->select('id'))->select('id'))->findOrFail($id);
        $this->authorize('delete', $composant);
        $service->supprimerComposant($composant);
    }

    public function copier(TroupeauService $service): void
    {
        $cible = $this->inventaireChoisi() ?? abort(404);
        $this->authorize('create', [LotAnimaux::class, $this->elevage()]);
        $source = Inventaire::query()->where('elevage_id', $this->elevageId)->findOrFail((int) $this->inventaireSource);

        $this->executer(fn () => $service->copierDepuis($source, $cible), 'Lots, phases et rations copiés de l\'inventaire du '.$source->date_inventaire->format('d/m/Y').'.');
    }

    public function render(TroupeauService $service): View
    {
        $elevage = $this->elevage();
        $inventaire = $this->inventaireChoisi();

        return view('livewire.troupeau.troupeau', [
            'elevage' => $elevage,
            'libelleElevage' => ElevageCourant::libelle(app(ElevageCourant::class)->accessibles()->firstWhere('id', $elevage->id) ?? $elevage),
            'inventaireCourant' => $inventaire,
            'inventaires' => Inventaire::query()->where('elevage_id', $elevage->id)->orderByDesc('date_inventaire')->get(),
            'besoins' => $inventaire === null ? null : $service->besoins($inventaire),
            'peutSaisir' => $inventaire !== null && Gate::allows('create', [LotAnimaux::class, $elevage]) && $inventaire->statut === Inventaire::STATUT_COURANT,
            'categories' => DB::table('ref.categorie_animale as c')->leftJoin('ref.coefficient_ugb as u', 'u.code', '=', 'c.coefficient_ugb_code')
                ->orderBy('c.espece_code')->orderBy('c.libelle')->get(['c.code', 'c.libelle', 'u.coefficient']),
            'coefficients' => DB::table('ref.coefficient_ugb')->orderBy('ordre')->get(),
            'lotsStock' => $inventaire === null ? collect() : LotStock::query()->where('inventaire_id', $inventaire->id)->orderBy('libelle')->get(),
            'familles' => DB::table('ref.famille_fourrage')->where('code', '!=', 'CONCENTRE')->pluck('libelle', 'code'),
            'etiquettes' => EtiquetteAliment::query()->where('elevage_id', $elevage->id)->orderBy('libelle_eleveur')->get()->filter(fn ($e) => Gate::allows('view', $e))->values(),
            'distributionsProposees' => DB::table('app.parametre_defaut')->whereIn('cle', TroupeauService::PERTES_DISTRIBUTION)->whereNull('valide_au')->pluck('libelle', 'cle'),
            'stades' => self::stades(),
        ])->layout('components.layouts.espace', ['titre' => 'Troupeau']);
    }

    /** @return array<string, string> */
    public static function stades(): array
    {
        return [
            'entretien' => 'Entretien', 'gestation' => 'Gestation', 'fin_gestation' => 'Fin de gestation', 'prepa_velage' => 'Préparation au vêlage',
            'lactation' => 'Lactation', 'croissance' => 'Croissance', 'engraissement' => 'Engraissement', 'tarissement' => 'Tarissement',
        ];
    }

    private function inventaireChoisi(): ?Inventaire
    {
        $requete = Inventaire::query()->where('elevage_id', $this->elevageId);

        return $this->inventaire === null
            ? $requete->where('statut', Inventaire::STATUT_COURANT)->first() ?? $requete->orderByDesc('date_inventaire')->first()
            : $requete->find($this->inventaire);
    }

    /** @return Builder<LotAnimaux> */
    private function lotsQuery()
    {
        return LotAnimaux::query()->where('inventaire_id', $this->inventaireChoisi()->id ?? 0);
    }

    private function lotDeLInventaire(int $id): LotAnimaux
    {
        return $this->lotsQuery()->findOrFail($id);
    }

    private function rationDeLInventaire(int $id): Ration
    {
        return Ration::query()->whereIn('phase_id', Phase::query()->whereIn('lot_animaux_id', $this->lotsQuery()->select('id'))->select('id'))->findOrFail($id);
    }

    private function executer(callable $operation, ?string $message = null, ?callable $apres = null, string $champErreur = 'troupeau'): void
    {
        try {
            $operation();
            if ($apres !== null) {
                $apres();
            }
            if ($message !== null) {
                session()->flash('status', $message);
            }
        } catch (SaisieRefusee $e) {
            $this->addError($champErreur, $e->getMessage());
        }
    }

    private static function nombre(mixed $valeur): ?float
    {
        return $valeur === null || $valeur === '' ? null : (float) str_replace(',', '.', (string) $valeur);
    }
}
