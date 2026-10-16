<?php

namespace App\Livewire\Dossier\Saisie;

use App\Livewire\Dossier\Concerns\SaisieDeVisite;
use App\Models\Dossier\AnalyseEau;
use App\Services\Dossier\EauService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Étape « analyser » — eau d'abreuvement (EF-M1-04, EF-M9-02) : saisie d'une analyse (point de prélèvement, date,
 * laboratoire, paramètres), lecture par la grille eau avec repère et source, validation.
 */
class Eau extends Component
{
    use SaisieDeVisite {
        mount as monterVisite;
    }

    public string $point = '';

    public string $date = '';

    public ?string $laboratoire = null;

    /** @var array<string, string|null> valeur saisie par paramètre EAU_* */
    public array $valeurs = [];

    /** @var array<string, bool> résultat inférieur à la limite de quantification */
    public array $censures = [];

    public function mount(int $visiteId): void
    {
        $this->monterVisite($visiteId);
        $this->date = $this->visite()->date_visite->toDateString();
    }

    public function ajouter(EauService $service): void
    {
        $visite = $this->visite();
        $this->authorize('create', [AnalyseEau::class, $visite->elevage, $visite->id]);
        $this->validate([
            'point' => ['required', 'string', 'max:200'],
            'date' => ['required', 'date'],
            'laboratoire' => ['nullable', 'integer', Rule::in(self::codes('ref.laboratoire', 'id'))],
            'valeurs.*' => ['nullable', 'numeric', 'min:0'],
        ], [], ['point' => 'point de prélèvement', 'date' => 'date de prélèvement', 'valeurs.*' => 'valeur']);

        $resultats = [];
        foreach ($this->valeurs as $code => $valeur) {
            if (($v = self::nombreOuNul($valeur)) !== null) {
                $resultats[$code] = ['valeur' => $v, 'censure' => ($this->censures[$code] ?? false) ? '<' : null];
            }
        }

        $this->executer(function () use ($service, $visite, $resultats) {
            $service->creer($visite, $this->point, $this->date, $this->laboratoire === null || $this->laboratoire === '' ? null : (int) $this->laboratoire, $resultats);
            $this->reset('point', 'valeurs', 'censures');
        }, 'Analyse d\'eau enregistrée.');
    }

    public function valider(int $analyseId, EauService $service): void
    {
        $analyse = $this->analyse($analyseId);
        $this->authorize('valider', $analyse);
        $this->executer(fn () => $service->valider($analyse), 'Analyse d\'eau validée.');
    }

    public function supprimer(int $analyseId, EauService $service): void
    {
        $analyse = $this->analyse($analyseId);
        $this->authorize('delete', $analyse);
        $this->executer(fn () => $service->supprimer($analyse), 'Analyse d\'eau supprimée.');
    }

    public function render(EauService $service): View
    {
        $visite = $this->visite();
        $analyses = AnalyseEau::query()->where('visite_id', $visite->id)->orderBy('date_prelevement')->get();

        return view('livewire.dossier.saisie.eau', [
            'analyses' => $analyses->map(fn (AnalyseEau $a) => [
                'analyse' => $a, 'lignes' => $service->lignes($a),
                'peutValider' => Gate::allows('valider', $a), 'peutSupprimer' => Gate::allows('delete', $a),
            ]),
            'peutAjouter' => Gate::allows('create', [AnalyseEau::class, $visite->elevage, $visite->id]),
            'parametres' => DB::table('ref.parametre')->where('famille_code', 'EAU')->orderBy('ordre')->get(['code', 'libelle', 'unite']),
            'laboratoires' => DB::table('ref.laboratoire')->where('actif', true)->orderBy('code')->pluck('raison_sociale', 'id'),
        ]);
    }

    private function analyse(int $id): AnalyseEau
    {
        return AnalyseEau::query()->where('visite_id', $this->visiteId)->findOrFail($id);
    }
}
