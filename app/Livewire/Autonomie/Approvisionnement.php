<?php

namespace App\Livewire\Autonomie;

use App\Livewire\Dossier\Lecture\Concerns\VoletDossier;
use App\Models\Fourrage\Elevage;
use App\Models\Plan\Scenario;
use App\Models\Stock\Inventaire;
use App\Services\Autonomie\ApprovisionnementService;
use App\Services\Cloisonnement\RegleStatut;
use App\Services\Dossier\SaisieRefusee;
use App\Services\ElevageCourant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * E15 — approvisionnement (EF-M7-01 à EF-M7-05, RC-13) : proposition initiale sur l'inventaire en cours, scénarios,
 * comparaison de quatre scénarios au plus (T-23), scénario retenu (un seul, T-24). Lecture : éleveur et technicien ;
 * écriture : convention active (ScenarioPolicy, T-32).
 */
class Approvisionnement extends Component
{
    use VoletDossier;

    public string $libelle = '';

    /** @var array<int, int|string> scénarios cochés pour la comparaison (chaînes à la saisie) */
    public array $compares = [];

    public function mount(Elevage $elevage): void
    {
        $this->monterVolet($elevage);
        $this->authorize('viewAny', Scenario::class);
    }

    public function creer(ApprovisionnementService $service, bool $depuisProposition = true): void
    {
        $inventaire = $this->inventaireCourant() ?? abort(404);
        $this->authorize('create', [Scenario::class, $this->elevage()]);
        $libelle = trim($this->libelle) ?: ($depuisProposition ? 'Proposition initiale' : 'Scénario').' du '.now()->format('d/m/Y');

        $this->executer(function () use ($service, $inventaire, $libelle, $depuisProposition) {
            $scenario = $service->creerScenario($inventaire, $libelle, $depuisProposition, $this->externe());
            $this->reset('libelle');
            $this->redirectRoute('scenarios.voir', [$this->elevageId, $scenario->id], navigate: false);
        });
    }

    public function dupliquer(int $id, ApprovisionnementService $service): void
    {
        $scenario = $this->scenario($id);
        $this->authorize('create', [Scenario::class, $this->elevage()]);
        $this->executer(fn () => $service->dupliquer($scenario, 'Copie de '.$scenario->libelle), 'Scénario dupliqué.');
    }

    public function retenir(int $id, ApprovisionnementService $service): void
    {
        $scenario = $this->scenario($id);
        $this->authorize('update', $scenario);
        $this->executer(fn () => $service->retenir($scenario, $this->lecteur()), 'Scénario retenu : les achats prévus sont ajoutés à l\'inventaire en cours.');
    }

    public function supprimer(int $id, ApprovisionnementService $service): void
    {
        $scenario = $this->scenario($id);
        $this->authorize('delete', $scenario);
        $service->supprimerScenario($scenario);
        $this->compares = array_values(array_filter($this->compares, fn ($c) => (int) $c !== $id));
    }

    public function updatedCompares(): void
    {
        $this->compares = array_map(fn ($c) => (int) $c, array_values($this->compares));
        if (count($this->compares) > ApprovisionnementService::MAX_COMPARAISON) {
            $this->compares = array_slice($this->compares, 0, ApprovisionnementService::MAX_COMPARAISON);
            $this->addError('compares', 'Comparer quatre scénarios au plus.');
        }
    }

    public function render(ApprovisionnementService $service): View
    {
        $inventaire = $this->inventaireCourant();
        $elevage = $this->elevage();
        $peutSaisir = $inventaire !== null && Gate::allows('create', [Scenario::class, $elevage]);

        return view('livewire.autonomie.approvisionnement', [
            'elevage' => $elevage,
            'libelleElevage' => ElevageCourant::libelle($elevage),
            'inventaireCourant' => $inventaire,
            'proposition' => $inventaire === null ? null : $service->proposition($inventaire, $this->externe()),
            'scenarios' => $inventaire === null ? collect() : $service->scenarios($inventaire),
            'comparaison' => $inventaire === null || $this->compares === [] ? [] : $service->comparer($inventaire, array_map(fn ($c) => (int) $c, array_values($this->compares)), $this->externe()),
            'familles' => DB::table('ref.famille_fourrage')->pluck('libelle', 'code'),

            'peutSaisir' => $peutSaisir,
        ])->layout('components.layouts.espace', ['titre' => 'Approvisionnement']);
    }

    /** Lecteur externe : seules les analyses publiées fournissent les teneurs (§ 6.3). */
    private function externe(): bool
    {
        return ! app(RegleStatut::class)->voitTousLesStatuts($this->lecteur());
    }

    private function inventaireCourant(): ?Inventaire
    {
        return Inventaire::query()->where('elevage_id', $this->elevageId)->where('statut', Inventaire::STATUT_COURANT)->first();
    }

    private function scenario(int $id): Scenario
    {
        return Scenario::query()->where('inventaire_id', $this->inventaireCourant()->id ?? 0)->findOrFail($id);
    }

    private function executer(callable $operation, ?string $message = null): void
    {
        try {
            $operation();
            if ($message !== null) {
                session()->flash('status', $message);
            }
        } catch (SaisieRefusee $e) {
            $this->addError('scenario', $e->getMessage());
        }
    }
}
