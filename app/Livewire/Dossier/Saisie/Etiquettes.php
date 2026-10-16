<?php

namespace App\Livewire\Dossier\Saisie;

use App\Domain\Dossier\ApportEtiquette;
use App\Livewire\Dossier\Concerns\SaisieDeVisite;
use App\Models\Dossier\EtiquetteAliment;
use App\Services\Dossier\EtiquettesService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Étape « analyser » — minéraux et aliments (EF-M1-06) : étiquette (nature, libellé tel que sur le sac, dose,
 * date limite), composition par kg de produit, apport journalier par élément ; validation par le technicien.
 */
class Etiquettes extends Component
{
    use SaisieDeVisite {
        mount as monterVisite;
    }

    public string $nature = 'mineral';

    public string $libelle = '';

    public ?string $dose = null;

    public ?string $dateLimite = null;

    public string $modeDistribution = '';

    /** @var list<array{code: string, valeur: string|null, unite: string}> */
    public array $composition = [['code' => '', 'valeur' => null, 'unite' => 'mg/kg']];

    public function ajouterLigne(): void
    {
        $this->composition[] = ['code' => '', 'valeur' => null, 'unite' => 'mg/kg'];
    }

    public function retirerLigne(int $index): void
    {
        unset($this->composition[$index]);
        $this->composition = array_values($this->composition);
    }

    public function ajouter(EtiquettesService $service): void
    {
        $visite = $this->visite();
        $this->authorize('create', [EtiquetteAliment::class, $visite->elevage, $visite->id]);
        $this->validate([
            'nature' => ['required', Rule::in(EtiquetteAliment::NATURES)],
            'libelle' => ['required', 'string', 'max:200'],
            'dose' => ['nullable', 'numeric', 'min:0'],
            'dateLimite' => ['nullable', 'date'],
            'modeDistribution' => ['nullable', 'string', 'max:200'],
            'composition.*.code' => ['nullable', 'string', Rule::in(self::codes('ref.parametre'))],
            'composition.*.valeur' => ['nullable', 'numeric', 'min:0'],
            'composition.*.unite' => ['required', Rule::in(ApportEtiquette::unitesAdmises())],
        ], [], ['libelle' => 'libellé', 'dateLimite' => 'date limite', 'composition.*.code' => 'élément', 'composition.*.valeur' => 'teneur']);

        $composition = [];
        foreach ($this->composition as $ligne) {
            if ($ligne['code'] !== '' && ($v = self::nombreOuNul($ligne['valeur'])) !== null) {
                $composition[$ligne['code']] = ['valeur' => $v, 'unite' => $ligne['unite']];
            }
        }

        $this->executer(function () use ($service, $visite, $composition) {
            $service->creer($visite->elevage, $visite->id, [
                'nature' => $this->nature,
                'libelle_eleveur' => trim($this->libelle),
                'dose_g_par_animal_jour' => self::nombreOuNul($this->dose),
                'date_limite' => $this->dateLimite ?: null,
                'mode_distribution' => trim($this->modeDistribution) === '' ? null : trim($this->modeDistribution),
            ], $composition);
            $this->reset('libelle', 'dose', 'dateLimite', 'modeDistribution', 'composition');
        }, 'Étiquette enregistrée.');
    }

    public function valider(int $id, EtiquettesService $service): void
    {
        $etiquette = $this->etiquette($id);
        $this->authorize('valider', $etiquette);
        $this->executer(fn () => $service->valider($etiquette), 'Étiquette validée.');
    }

    public function supprimer(int $id, EtiquettesService $service): void
    {
        $etiquette = $this->etiquette($id);
        $this->authorize('delete', $etiquette);
        $this->executer(fn () => $service->supprimer($etiquette), 'Étiquette supprimée.');
    }

    public function render(EtiquettesService $service): View
    {
        $visite = $this->visite();
        $etiquettes = EtiquetteAliment::query()->where('elevage_id', $visite->elevage_id)
            ->where(fn ($q) => $q->where('visite_id', $visite->id)->orWhereNull('visite_id'))
            ->orderBy('id')->get()->filter(fn (EtiquetteAliment $e) => Gate::allows('view', $e));

        return view('livewire.dossier.saisie.etiquettes', [
            'etiquettes' => $etiquettes->map(fn (EtiquetteAliment $e) => [
                'etiquette' => $e, 'lecture' => $service->lecture($e, $visite->date_visite),
                'peutValider' => Gate::allows('valider', $e), 'peutSupprimer' => Gate::allows('delete', $e),
            ])->values(),
            'peutAjouter' => Gate::allows('create', [EtiquetteAliment::class, $visite->elevage, $visite->id]),
            'natures' => ['mineral' => 'Minéral', 'bolus' => 'Bolus', 'seau' => 'Seau à lécher', 'sel' => 'Sel', 'concentre' => 'Aliment concentré', 'autre' => 'Autre'],
            'elements' => DB::table('ref.parametre')->whereIn('famille_code', ['MINERAL_MAJEUR', 'OLIGO'])->orderBy('ordre')->pluck('libelle', 'code'),
            'unites' => ApportEtiquette::unitesAdmises(),
        ]);
    }

    private function etiquette(int $id): EtiquetteAliment
    {
        return EtiquetteAliment::query()->where('elevage_id', $this->visite()->elevage_id)->findOrFail($id);
    }
}
