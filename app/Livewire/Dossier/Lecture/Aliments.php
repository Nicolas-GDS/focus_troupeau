<?php

namespace App\Livewire\Dossier\Lecture;

use App\Livewire\Dossier\Concerns\FormulaireEtiquette;
use App\Livewire\Dossier\Lecture\Concerns\VoletDossier;
use App\Models\Dossier\EtiquetteAliment;
use App\Models\Fourrage\Elevage;
use App\Services\Dossier\EtiquettesService;
use App\Services\Dossier\SaisieRefusee;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * E08 — minéraux et aliments (EF-M1-06) : étiquettes lisibles et apport journalier par élément (dose × teneur).
 * L'éleveur peut ajouter une étiquette hors visite ; le technicien la valide depuis la saisie de sa prochaine visite.
 * La comparaison des apports au besoin (RC-9) rejoint ce volet au lot L5.
 */
class Aliments extends Component
{
    use FormulaireEtiquette;
    use VoletDossier;

    public function mount(Elevage $elevage): void
    {
        $this->monterVolet($elevage);
    }

    public function ajouter(EtiquettesService $service): void
    {
        $elevage = $this->elevage();
        $this->authorize('create', [EtiquetteAliment::class, $elevage, null]);
        $saisie = $this->etiquetteSaisie();

        try {
            $service->creer($elevage, null, $saisie['donnees'], $saisie['composition']);
            $this->viderFormulaireEtiquette();
            session()->flash('status', 'Étiquette enregistrée : votre technicien la validera.');
        } catch (SaisieRefusee $e) {
            $this->addError('saisie', $e->getMessage());
        }
    }

    public function render(): View
    {
        $elevage = $this->elevage();

        return $this->volet('aliments', 'Minéraux et aliments', [
            'etiquettes' => $this->lecture()->aliments($elevage, $this->lecteur()),
            'peutAjouter' => Gate::allows('create', [EtiquetteAliment::class, $elevage, null]),
            'natures' => self::natures(),
            ...self::referentielsEtiquette(),
        ]);
    }
}
