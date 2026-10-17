<?php

namespace App\Livewire\Stocks;

use App\Livewire\Dossier\Lecture\Concerns\VoletDossier;
use App\Models\Fourrage\Elevage;
use App\Models\Stock\Inventaire;
use App\Services\Dossier\SaisieRefusee;
use App\Services\ElevageCourant;
use App\Services\Stocks\StocksService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * E12 — inventaires de stocks de l'élevage (EF-M4-01) : liste et création d'un inventaire (date, type, période de
 * référence, année sèche ; marge de sécurité copiée de la valeur en vigueur, RC-12). Un seul inventaire courant.
 */
class Inventaires extends Component
{
    use VoletDossier;

    public string $dateInventaire = '';

    public string $type = 'fin_recolte';

    public string $dateDebut = '';

    public string $dateFin = '';

    public bool $anneeSeche = false;

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

    public function render(): View
    {
        $elevage = $this->elevage();

        return view('livewire.stocks.inventaires', [
            'elevage' => $elevage,
            'libelleElevage' => ElevageCourant::libelle(app(ElevageCourant::class)->accessibles()->firstWhere('id', $elevage->id) ?? $elevage),
            'inventaires' => Inventaire::query()->where('elevage_id', $elevage->id)->withSum('lots', 'stock_utile_t_ms')->orderByDesc('date_inventaire')->get(),
            'peutCreer' => Gate::allows('create', [Inventaire::class, $elevage]),
            'types' => self::types(),
        ])->layout('components.layouts.espace', ['titre' => 'Stocks fourragers']);
    }

    /** @return array<string, string> */
    public static function types(): array
    {
        return ['fin_recolte' => 'Fin de récolte', 'mi_hiver' => 'Mi-hiver', 'crise' => 'Crise', 'autre' => 'Autre'];
    }
}
