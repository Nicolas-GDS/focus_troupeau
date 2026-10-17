<?php

namespace App\Livewire\Stocks;

use App\Livewire\Dossier\Lecture\Concerns\VoletDossier;
use App\Models\Fourrage\Elevage;
use App\Models\Stock\Inventaire;
use App\Models\Stock\LotStock;
use App\Services\Dossier\SaisieRefusee;
use App\Services\ElevageCourant;
use App\Services\Stocks\ImportCalculateurService;
use App\Services\Stocks\StocksService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * E12 — inventaires de stocks de l'élevage (EF-M4-01) : liste et création d'un inventaire (date, type, période de
 * référence, année sèche ; marge de sécurité copiée de la valeur en vigueur, RC-12). Un seul inventaire courant.
 * Reprise d'un bilan du calculateur HTML (EF-M9-06, T-28) : collage, aperçu modifiable, création de l'inventaire.
 */
class Inventaires extends Component
{
    use VoletDossier;

    public string $dateInventaire = '';

    public string $type = 'fin_recolte';

    public string $dateDebut = '';

    public string $dateFin = '';

    public bool $anneeSeche = false;

    /** Contenu collé depuis le calculateur d'autonomie fourragère (EF-M9-06, T-28). */
    public string $contenuCalculateur = '';

    /** @var array{inventaire: array<string, mixed>, lots: list<array{donnees: array<string, mixed>, alertes: list<string>}>, non_repris: list<string>}|null */
    public ?array $apercu = null;

    public function mount(Elevage $elevage): void
    {
        $this->monterVolet($elevage);
        $this->authorize('viewAny', Inventaire::class);
        $this->dateInventaire = $this->dateDebut = today()->toDateString();
    }

    public function creer(StocksService $service): void
    {
        $elevage = $this->elevage();
        $this->authorize('create', [Inventaire::class, $elevage]);
        $this->validate([
            'dateInventaire' => ['required', 'date'],
            'type' => ['required', 'in:'.implode(',', Inventaire::TYPES)],
            'dateDebut' => ['required', 'date'],
            'dateFin' => ['required', 'date', 'after_or_equal:dateDebut'],
        ], [], ['dateInventaire' => 'date de l\'inventaire', 'dateDebut' => 'début de la période', 'dateFin' => 'fin de la période']);

        try {
            $inventaire = $service->creerInventaire($elevage, [
                'date_inventaire' => $this->dateInventaire, 'type' => $this->type,
                'date_debut' => $this->dateDebut, 'date_fin' => $this->dateFin, 'annee_seche' => $this->anneeSeche,
            ]);
            $this->redirectRoute('stocks.inventaire', [$elevage->id, $inventaire->id]);
        } catch (SaisieRefusee $e) {
            $this->addError('inventaire', $e->getMessage());
        }
    }

    public function analyserCalculateur(ImportCalculateurService $import): void
    {
        $this->authorize('create', [Inventaire::class, $this->elevage()]);
        $this->validate(['contenuCalculateur' => ['required', 'string', 'max:200000']], [], ['contenuCalculateur' => 'contenu du calculateur']);

        try {
            $this->apercu = $import->analyser($this->contenuCalculateur);
        } catch (SaisieRefusee $e) {
            $this->apercu = null;
            $this->addError('contenuCalculateur', $e->getMessage());
        }
    }

    public function importerCalculateur(ImportCalculateurService $import): void
    {
        $elevage = $this->elevage();
        $this->authorize('create', [Inventaire::class, $elevage]);
        if ($this->apercu === null) {
            return;
        }
        $this->validate([
            'apercu.lots.*.donnees.famille_code' => ['required', Rule::in(DB::table('ref.famille_fourrage')->pluck('code')->all())],
            'apercu.lots.*.donnees.forme' => ['required', Rule::in(LotStock::FORMES)],
        ]);

        try {
            $resultat = $import->importer($elevage, $this->apercu);
            session()->flash('status', $resultat['lots'].' lot(s) repris du calculateur.'.($resultat['refus'] === [] ? '' : ' Non repris : '.implode(' ; ', $resultat['refus'])));
            $this->redirectRoute('stocks.inventaire', [$elevage->id, $resultat['inventaire']->id]);
        } catch (SaisieRefusee $e) {
            $this->addError('contenuCalculateur', $e->getMessage());
        }
    }

    public function render(): View
    {
        $elevage = $this->elevage();

        return view('livewire.stocks.inventaires', [
            'elevage' => $elevage,
            'libelleElevage' => ElevageCourant::libelle(app(ElevageCourant::class)->accessibles()->firstWhere('id', $elevage->id) ?? $elevage),
            'inventaires' => Inventaire::query()->where('elevage_id', $elevage->id)->withSum('lots', 'stock_utile_t_ms')->orderByDesc('date_inventaire')->get(),
            'peutCreer' => Gate::allows('create', [Inventaire::class, $elevage]),
            'types' => self::types(),
            'familles' => DB::table('ref.famille_fourrage')->where('code', '!=', 'CONCENTRE')->pluck('libelle', 'code'),
        ])->layout('components.layouts.espace', ['titre' => 'Stocks fourragers']);
    }

    /** @return array<string, string> */
    public static function types(): array
    {
        return ['fin_recolte' => 'Fin de récolte', 'mi_hiver' => 'Mi-hiver', 'crise' => 'Crise', 'autre' => 'Autre'];
    }
}
