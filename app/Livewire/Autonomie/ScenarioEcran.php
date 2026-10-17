<?php

namespace App\Livewire\Autonomie;

use App\Domain\Autonomie\Levier;
use App\Livewire\Dossier\Lecture\Concerns\VoletDossier;
use App\Models\Fourrage\Elevage;
use App\Models\Plan\LevierLigne;
use App\Models\Plan\Scenario;
use App\Models\Stock\Inventaire;
use App\Services\Autonomie\ApprovisionnementService;
use App\Services\Cloisonnement\RegleStatut;
use App\Services\Dossier\SaisieRefusee;
use App\Services\ElevageCourant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * E15 — un scénario d'approvisionnement (EF-M7-02 à EF-M7-06, RC-13.3, RC-13.4) : leviers du catalogue avec leur gain,
 * leur coût et leur conséquence nutritionnelle, prix indicatifs signalés, évaluation du bilan, points de vigilance,
 * retenue du scénario. Le scénario appartient à l'élevage de l'adresse (404 sinon, T-40).
 */
class ScenarioEcran extends Component
{
    use VoletDossier;

    #[Locked]
    public int $scenarioId;

    /** @var array{levier_code: string, quantite: string, parametre: string, famille_code: string, famille_source_code: string, date_effet: string} */
    public array $ligne = ['levier_code' => Levier::ACHAT, 'quantite' => '', 'parametre' => '', 'famille_code' => '', 'famille_source_code' => '', 'date_effet' => ''];

    public ?int $ligneEditee = null;

    public string $libelle = '';

    public function mount(Elevage $elevage, string $scenario): void
    {
        $this->monterVolet($elevage);
        abort_unless(ctype_digit($scenario), 404);
        $s = Scenario::query()->whereIn('inventaire_id', Inventaire::query()->where('elevage_id', $elevage->id)->select('id'))->findOrFail((int) $scenario);
        $this->authorize('view', $s);
        $this->scenarioId = $s->id;
        $this->libelle = $s->libelle;
    }

    public function enregistrerLigne(ApprovisionnementService $service): void
    {
        $scenario = $this->scenario();
        $this->authorize('update', $scenario);
        foreach (['quantite', 'parametre'] as $champ) {
            $this->ligne[$champ] = str_replace(',', '.', trim($this->ligne[$champ]));
        }
        $this->validate([
            'ligne.levier_code' => ['required', Rule::in(Levier::CODES)],
            'ligne.quantite' => [$this->ligne['levier_code'] === Levier::RATELIER ? 'nullable' : 'required', 'numeric', 'min:0'],
            'ligne.parametre' => ['nullable', 'numeric', 'min:0'],
            'ligne.famille_code' => ['nullable', Rule::in(DB::table('ref.famille_fourrage')->pluck('code')->all())],
            'ligne.famille_source_code' => ['nullable', Rule::in(DB::table('ref.famille_fourrage')->pluck('code')->all())],
            'ligne.date_effet' => ['nullable', 'date'],
        ], [], ['ligne.quantite' => 'quantité', 'ligne.parametre' => 'paramètre', 'ligne.date_effet' => 'date d\'effet']);
        $l = $this->ligne;
        $existante = $this->ligneEditee === null ? null : LevierLigne::query()->where('scenario_id', $scenario->id)->findOrFail($this->ligneEditee);

        try {
            $service->enregistrerLigne($scenario, [
                'levier_code' => $l['levier_code'], 'quantite' => (float) ($l['quantite'] ?: 0), 'parametre' => $l['parametre'] === '' ? null : (float) $l['parametre'],
                'famille_code' => $l['famille_code'] ?: null, 'famille_source_code' => $l['famille_source_code'] ?: null, 'date_effet' => $l['date_effet'] ?: null,
            ], $existante);
            $this->reset('ligne', 'ligneEditee');
            session()->flash('status', 'Levier enregistré.');
        } catch (SaisieRefusee $e) {
            $this->addError('ligne', $e->getMessage());
        }
    }

    public function editer(int $id): void
    {
        $l = LevierLigne::query()->where('scenario_id', $this->scenarioId)->findOrFail($id);
        $this->authorize('update', $l);
        $this->ligneEditee = $l->id;
        $this->ligne = [
            'levier_code' => $l->levier_code, 'quantite' => $l->levier_code === Levier::RATELIER ? '' : (string) (float) $l->quantite,
            'parametre' => $l->parametre === null ? '' : (string) (float) $l->parametre, 'famille_code' => (string) $l->famille_code,
            'famille_source_code' => (string) $l->famille_source_code, 'date_effet' => $l->date_effet?->toDateString() ?? '',
        ];
    }

    public function supprimerLigne(int $id, ApprovisionnementService $service): void
    {
        $l = LevierLigne::query()->where('scenario_id', $this->scenarioId)->findOrFail($id);
        $this->authorize('delete', $l);
        $service->supprimerLigne($l);
    }

    public function renommer(): void
    {
        $scenario = $this->scenario();
        $this->authorize('update', $scenario);
        $this->validate(['libelle' => ['required', 'string', 'max:200']]);
        $scenario->update(['libelle' => trim($this->libelle)]);
    }

    public function retenir(ApprovisionnementService $service): void
    {
        $scenario = $this->scenario();
        $this->authorize('update', $scenario);
        try {
            $service->retenir($scenario, $this->lecteur());
            session()->flash('status', 'Scénario retenu : les achats prévus sont ajoutés à l\'inventaire en cours, le technicien est prévenu.');
        } catch (SaisieRefusee $e) {
            $this->addError('ligne', $e->getMessage());
        }
    }

    public function render(ApprovisionnementService $service): View
    {
        $scenario = $this->scenario();
        $elevage = $this->elevage();
        $evaluation = $service->evaluer($scenario, ! app(RegleStatut::class)->voitTousLesStatuts($this->lecteur()));
        $inventaire = Inventaire::query()->findOrFail($scenario->inventaire_id);

        return view('livewire.autonomie.scenario', [
            'elevage' => $elevage,
            'libelleElevage' => ElevageCourant::libelle($elevage),
            'scenario' => $scenario,
            'inventaire' => $inventaire,
            'evaluation' => $evaluation,
            'familles' => DB::table('ref.famille_fourrage')->pluck('libelle', 'code'),
            'libelles' => ApprovisionnementService::LIBELLES,
            'unites' => ApprovisionnementService::UNITES,
            'defaut' => $service->parametreDefaut($this->ligne['levier_code'], $this->ligne['famille_code'] ?: null, $service->gds($inventaire)),
            'peutSaisir' => Gate::allows('update', $scenario),
        ])->layout('components.layouts.espace', ['titre' => 'Scénario d\'approvisionnement']);
    }

    private function scenario(): Scenario
    {
        return Scenario::query()->findOrFail($this->scenarioId);
    }
}
