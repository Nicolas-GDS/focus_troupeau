<?php

namespace App\Livewire\Dossier\Saisie;

use App\Livewire\Dossier\Concerns\FormulaireEtiquette;
use App\Livewire\Dossier\Concerns\SaisieDeVisite;
use App\Models\Dossier\EtiquetteAliment;
use App\Services\Dossier\EtiquettesService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Étape « analyser » — minéraux et aliments (EF-M1-06) : étiquette (nature, libellé tel que sur le sac, dose,
 * date limite), composition par kg de produit, apport journalier par élément ; validation par le technicien.
 */
class Etiquettes extends Component
{
    use FormulaireEtiquette;
    use SaisieDeVisite {
        mount as monterVisite;
    }

    public function ajouter(EtiquettesService $service): void
    {
        $visite = $this->visite();
        $this->authorize('create', [EtiquetteAliment::class, $visite->elevage, $visite->id]);
        $saisie = $this->etiquetteSaisie();

        $this->executer(function () use ($service, $visite, $saisie) {
            $service->creer($visite->elevage, $visite->id, $saisie['donnees'], $saisie['composition']);
            $this->viderFormulaireEtiquette();
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
            'natures' => self::natures(),
            ...self::referentielsEtiquette(),
        ]);
    }

    private function etiquette(int $id): EtiquetteAliment
    {
        return EtiquetteAliment::query()->where('elevage_id', $this->visite()->elevage_id)->findOrFail($id);
    }
}
