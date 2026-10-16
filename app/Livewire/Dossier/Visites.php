<?php

namespace App\Livewire\Dossier;

use App\Models\App\User;
use App\Models\Dossier\Visite;
use App\Models\Fourrage\Elevage;
use App\Services\Dossier\VisiteService;
use App\Services\ElevageCourant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use RuntimeException;

/**
 * Visites de l'élevage courant (EF-M1-09) : liste (les comptes externes ne voient que les visites publiées) et
 * création d'une visite par le technicien. L'élevage est celui du sélecteur (EF-M0-08), relu à travers les scopes.
 */
class Visites extends Component
{
    public string $dateVisite = '';

    public string $motif = 'initiale';

    public string $motifDetail = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Visite::class);
        $this->dateVisite = today()->toDateString();
    }

    public function creer(VisiteService $service): void
    {
        $elevage = $this->elevage();
        $this->authorize('create', [Visite::class, $elevage]);
        $this->validate([
            'dateVisite' => ['required', 'date'],
            'motif' => ['required', Rule::in(Visite::MOTIFS)],
            'motifDetail' => ['nullable', 'string', 'max:500'],
        ], [], ['dateVisite' => 'date de visite', 'motifDetail' => 'précision du motif']);

        $visite = $service->creer($elevage, $this->utilisateur(), $this->dateVisite, $this->motif, $this->motifDetail);

        $this->redirectRoute('visites.saisie', $visite->id);
    }

    public function render(): View
    {
        $elevage = $this->elevage();
        $visites = $elevage === null ? collect() : Visite::query()->where('elevage_id', $elevage->id)
            ->orderByDesc('date_visite')->orderByDesc('id')->get()
            ->filter(fn (Visite $v) => Gate::allows('view', $v))->values();

        return view('livewire.dossier.visites', [
            'elevage' => $elevage,
            'libelleElevage' => $elevage === null ? null : ElevageCourant::libelle($elevage),
            'visites' => $visites,
            'peutCreer' => $elevage !== null && Gate::allows('create', [Visite::class, $elevage]),
            'peutSaisir' => fn (Visite $v) => Gate::allows('update', $v),
            'motifs' => self::motifs(),
        ])->layout('components.layouts.espace', ['titre' => 'Visites']);
    }

    /** @return array<string, string> */
    public static function motifs(): array
    {
        return [
            'initiale' => 'Visite initiale',
            'suivi' => 'Visite de suivi',
            'question_sanitaire' => 'Question sanitaire',
            'bilan_stocks' => 'Bilan des stocks',
            'autre' => 'Autre motif',
        ];
    }

    private function elevage(): ?Elevage
    {
        return app(ElevageCourant::class)->courant();
    }

    private function utilisateur(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            throw new RuntimeException('Utilisateur non connecté.');
        }

        return $user;
    }
}
