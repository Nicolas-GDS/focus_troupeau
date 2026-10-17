<?php

namespace App\Livewire\Autonomie;

use App\Livewire\Dossier\Lecture\Concerns\VoletDossier;
use App\Models\Fourrage\Elevage;
use App\Models\Stock\Inventaire;
use App\Models\Stock\PeriodePhase;
use App\Services\Autonomie\EcranBilan;
use App\Services\Autonomie\PeriodesService;
use App\Services\Dossier\SaisieRefusee;
use App\Services\ElevageCourant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * E14 — autonomie, bilan et projection (EF-M6-01 à EF-M6-05) pour l'inventaire courant (ou un inventaire choisi) :
 * trois indicateurs avec leur fourchette (RC-14) et le repère d'action, tableau mensuel par famille, graphique,
 * ruptures par famille et par lot, phases de la période de référence (RC-10, voie UGB). Lecture : éleveur et
 * technicien (StockPolicy) ; saisie des phases : convention active (T-32).
 */
class Bilan extends Component
{
    use VoletDossier;

    #[Url]
    public ?int $inventaire = null;

    /** @var array{libelle: string, debut: string, fin: string, coefficient: string} */
    public array $nouvellePeriode = ['libelle' => 'hivernage', 'debut' => '', 'fin' => '', 'coefficient' => ''];

    public function mount(Elevage $elevage): void
    {
        $this->monterVolet($elevage);
        $this->authorize('viewAny', Inventaire::class);
    }

    public function ajouterPeriode(PeriodesService $periodes): void
    {
        $inventaire = $this->inventaireChoisi() ?? abort(404);
        $this->authorize('create', [PeriodePhase::class, $this->elevage()]);
        abort_unless($inventaire->statut === Inventaire::STATUT_COURANT, 403);
        $this->nouvellePeriode['coefficient'] = str_replace(',', '.', trim($this->nouvellePeriode['coefficient']));
        $this->validate([
            'nouvellePeriode.libelle' => ['required', Rule::in(PeriodePhase::LIBELLES)],
            'nouvellePeriode.debut' => ['required', 'date'],
            'nouvellePeriode.fin' => ['required', 'date'],
            'nouvellePeriode.coefficient' => ['nullable', 'numeric', 'gt:0', 'lte:1'],
        ], [], ['nouvellePeriode.debut' => 'début', 'nouvellePeriode.fin' => 'fin', 'nouvellePeriode.coefficient' => 'coefficient']);
        $p = $this->nouvellePeriode;

        try {
            $periodes->ajouter($inventaire, $p['libelle'], $p['debut'], $p['fin'], $p['coefficient'] === '' ? null : (float) $p['coefficient']);
            $this->reset('nouvellePeriode');
            session()->flash('status', 'Phase de la période ajoutée.');
        } catch (SaisieRefusee $e) {
            $this->addError('periode', $e->getMessage());
        }
    }

    public function supprimerPeriode(int $id, PeriodesService $periodes): void
    {
        $phase = PeriodePhase::query()->where('inventaire_id', $this->inventaireChoisi()->id ?? 0)->findOrFail($id);
        $this->authorize('delete', $phase);
        $periodes->supprimer($phase);
    }

    public function render(EcranBilan $ecran): View
    {
        $inventaire = $this->inventaireChoisi();
        $elevage = $this->elevage();

        return view('livewire.autonomie.bilan', [
            'elevage' => $elevage,
            'libelleElevage' => ElevageCourant::libelle($elevage),
            'inventaireCourant' => $inventaire,
            'inventaires' => $ecran->inventaires($this->elevageId),
            'ecran' => $inventaire === null ? null : $ecran->donnees($inventaire),
            'peutSaisir' => $inventaire !== null && $inventaire->statut === Inventaire::STATUT_COURANT && Gate::allows('create', [PeriodePhase::class, $elevage]),
            'libellesPeriode' => self::libellesPeriode(),
        ])->layout('components.layouts.espace', ['titre' => 'Autonomie fourragère']);
    }

    /** @return array<string, string> */
    public static function libellesPeriode(): array
    {
        return ['hivernage' => 'Hivernage (pleine ration)', 'transition' => 'Transition de mise à l\'herbe', 'complementation_estivale' => 'Complémentation estivale'];
    }

    private function inventaireChoisi(): ?Inventaire
    {
        $requete = Inventaire::query()->where('elevage_id', $this->elevageId);

        return $this->inventaire === null
            ? $requete->where('statut', Inventaire::STATUT_COURANT)->first() ?? $requete->orderByDesc('date_inventaire')->first()
            : $requete->find($this->inventaire);
    }
}
