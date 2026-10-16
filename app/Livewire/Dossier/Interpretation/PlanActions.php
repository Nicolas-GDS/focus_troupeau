<?php

namespace App\Livewire\Dossier\Interpretation;

use App\Models\App\User;
use App\Models\Dossier\Action;
use App\Models\Dossier\Controle;
use App\Models\Dossier\InterpretationVisite;
use App\Models\Dossier\PlanAction;
use App\Models\Dossier\Visite;
use App\Services\Dossier\InterpretationVisiteService;
use App\Services\Dossier\SaisieRefusee;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RuntimeException;

/**
 * Plan d'action et contrôles de l'interprétation de visite (EF-M2-06, étapes « agir » et « suivre ») : actions
 * priorisées (1 à 3), chacune avec un responsable, un « comment », un « quand » (date ou jalon du calendrier
 * d'élevage) et ses contrôles (mesure, cible, échéance). Sur le plan publié : l'éleveur marque « faites » ses
 * actions, le technicien saisit le résultat des contrôles ; le vétérinaire conseil valide les actions vétérinaires.
 * En mode « publiée » (volet E11), le composant montre toujours la dernière version publiée, même pour un agent
 * qui prépare la version suivante.
 */
class PlanActions extends Component
{
    #[Locked]
    public int $visiteId;

    #[Locked]
    public bool $publiee = false;

    /** @var array{priorite: string, responsable: string, titre: string, comment: string, quand: string, echeance: string, jalon: string} */
    public array $action = ['priorite' => '1', 'responsable' => 'eleveur', 'titre' => '', 'comment' => '', 'quand' => '', 'echeance' => '', 'jalon' => ''];

    /** @var array<int, array{mesure?: string, cible: string, echeance: string}> nouveau contrôle par action */
    public array $controles = [];

    /** @var array<int, string> résultat saisi par contrôle */
    public array $resultats = [];

    /** @var array<int, string> commentaire de l'éleveur par action */
    public array $commentaires = [];

    public function mount(int $visiteId, bool $publiee = false): void
    {
        $this->visiteId = $visiteId;
        $this->publiee = $publiee;
        $this->authorize('view', Visite::query()->findOrFail($visiteId));
    }

    public function ajouterAction(InterpretationVisiteService $service): void
    {
        $interpretation = $this->interpretationAutorisee();
        $this->validate([
            'action.priorite' => ['required', 'integer', 'between:1,3'],
            'action.responsable' => ['required', Rule::in(Action::RESPONSABLES)],
            'action.titre' => ['required', 'string', 'max:300'],
            'action.comment' => ['nullable', 'string', 'max:2000'],
            'action.quand' => ['nullable', 'string', 'max:300'],
            'action.echeance' => ['nullable', 'date'],
            'action.jalon' => ['nullable', Rule::in(Action::JALONS)],
        ], [], ['action.titre' => 'action', 'action.responsable' => 'responsable', 'action.echeance' => 'échéance', 'action.jalon' => 'jalon']);

        $this->executer(function () use ($service, $interpretation) {
            $service->ajouterAction($interpretation, [
                'priorite' => (int) $this->action['priorite'], 'responsable' => $this->action['responsable'], 'titre' => $this->action['titre'],
                'comment' => $this->action['comment'], 'quand' => $this->action['quand'],
                'echeance' => $this->action['echeance'] ?: null, 'jalon' => $this->action['jalon'] ?: null,
            ], $this->utilisateur());
            $this->action = [...$this->action, 'titre' => '', 'comment' => '', 'quand' => '', 'echeance' => '', 'jalon' => ''];
        });
    }

    public function supprimerAction(int $id, InterpretationVisiteService $service): void
    {
        $action = $this->actionDuPlan($id);
        $this->authorize('delete', $action);
        $this->executer(fn () => $service->supprimerAction($action));
    }

    public function ajouterControle(int $actionId, InterpretationVisiteService $service): void
    {
        $action = $this->actionDuPlan($actionId);
        $this->authorize('update', $action);
        $this->validate([
            "controles.{$actionId}.mesure" => ['nullable', 'string', 'max:300'],
            "controles.{$actionId}.cible" => ['required', 'string', 'max:300'],
            "controles.{$actionId}.echeance" => ['required', 'date'],
        ], [], ["controles.{$actionId}.cible" => 'cible', "controles.{$actionId}.echeance" => 'échéance']);

        $saisie = $this->controles[$actionId];
        $this->executer(function () use ($service, $action, $saisie, $actionId) {
            $service->ajouterControle($action, $saisie['mesure'] ?? null, $saisie['cible'], $saisie['echeance'], $this->utilisateur());
            unset($this->controles[$actionId]);
        });
    }

    public function supprimerControle(int $id, InterpretationVisiteService $service): void
    {
        $controle = $this->controleDuPlan($id);
        $this->authorize('delete', $controle);
        $this->executer(fn () => $service->supprimerControle($controle));
    }

    public function validerVeterinaire(int $id, InterpretationVisiteService $service): void
    {
        $action = $this->actionDuPlan($id);
        $this->authorize('validerVeterinaire', $action);
        $this->executer(fn () => $service->validerActionVeterinaire($action, $this->utilisateur()));
    }

    public function marquerFaite(int $id, InterpretationVisiteService $service): void
    {
        $action = $this->actionDuPlan($id);
        $this->authorize('marquerFaite', $action);
        $this->validate(["commentaires.{$id}" => ['nullable', 'string', 'max:1000']]);
        $this->executer(fn () => $service->marquerFaite($action, $this->utilisateur(), $this->commentaires[$id] ?? null));
    }

    public function saisirResultat(int $id, InterpretationVisiteService $service): void
    {
        $controle = $this->controleDuPlan($id);
        $this->authorize('saisirResultat', $controle);
        $this->validate(["resultats.{$id}" => ['required', 'string', 'max:1000']], [], ["resultats.{$id}" => 'résultat']);
        $this->executer(fn () => $service->saisirResultatControle($controle, $this->resultats[$id], $this->utilisateur()));
    }

    public function render(): View
    {
        $interpretation = $this->interpretation();
        $plan = $interpretation === null ? null : PlanAction::query()->where('interpretation_visite_id', $interpretation->id)->first();
        $actions = $plan === null ? collect() : Action::query()->where('plan_action_id', $plan->id)->with('controles')
            ->orderBy('priorite')->orderBy('ordre')->get();

        return view('livewire.dossier.interpretation.plan-actions', [
            'interpretation' => $interpretation,
            'parPriorite' => $actions->groupBy('priorite'),
            'peutRediger' => $interpretation !== null && Gate::allows('update', $interpretation),
            'responsables' => ['eleveur' => 'Éleveur', 'technicien' => 'Technicien', 'veterinaire' => 'Vétérinaire'],
            'jalons' => self::jalons(),
            'priorites' => [1 => 'Priorité 1 — tout de suite', 2 => 'Priorité 2', 3 => 'Priorité 3'],
            'auteurs' => DB::table('app.user')->whereIn('id', $actions->pluck('valide_par')->filter())->selectRaw("id, concat_ws(' ', prenom, nom) as nom")->pluck('nom', 'id'),
        ]);
    }

    /** @return array<string, string> jalons du calendrier d'élevage (§ 5.3) */
    public static function jalons(): array
    {
        return [
            'entree_hivernage' => 'Entrée en hivernage', 'tarissement' => 'Tarissement', '6_sem_avant_velage' => '6 semaines avant vêlage',
            '3_sem_avant_velage' => '3 semaines avant vêlage', 'velage' => 'Vêlage', 'mise_a_l_herbe' => 'Mise à l\'herbe',
            'campagne_suivante' => 'Campagne suivante',
        ];
    }

    private function interpretation(): ?InterpretationVisite
    {
        $service = app(InterpretationVisiteService::class);
        $visite = Visite::query()->findOrFail($this->visiteId);
        $courante = $this->publiee ? null : $service->courante($visite);

        if ($courante !== null && Gate::allows('view', $courante)) {
            return $courante;
        }

        $publiee = $service->publiee($visite);

        return $publiee !== null && Gate::allows('view', $publiee) ? $publiee : null;
    }

    private function interpretationAutorisee(): InterpretationVisite
    {
        $interpretation = $this->interpretation() ?? abort(404);
        $this->authorize('update', $interpretation);

        return $interpretation;
    }

    private function actionDuPlan(int $id): Action
    {
        $interpretation = $this->interpretation() ?? abort(404);

        return Action::query()->whereIn('plan_action_id', PlanAction::query()->where('interpretation_visite_id', $interpretation->id)->select('id'))
            ->findOrFail($id);
    }

    private function controleDuPlan(int $id): Controle
    {
        $controle = Controle::query()->findOrFail($id);
        $this->actionDuPlan($controle->action_id);

        return $controle;
    }

    private function executer(callable $operation): void
    {
        try {
            $operation();
        } catch (SaisieRefusee $e) {
            $this->addError('plan', $e->getMessage());
        }
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
