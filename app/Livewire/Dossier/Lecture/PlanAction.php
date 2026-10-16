<?php

namespace App\Livewire\Dossier\Lecture;

use App\Livewire\Dossier\Lecture\Concerns\VoletDossier;
use App\Models\Fourrage\Elevage;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * E11 — plan d'action et contrôles (EF-M2-06) : plans publiés de l'élevage, le plus récent d'abord. L'éleveur y marque
 * ses actions faites, le technicien y saisit les résultats des contrôles (composant PlanActions, version publiée).
 */
class PlanAction extends Component
{
    use VoletDossier;

    public function mount(Elevage $elevage): void
    {
        $this->monterVolet($elevage);
    }

    public function render(): View
    {
        return $this->volet('plan-action', 'Plan d\'action', ['plans' => $this->lecture()->interpretationsPubliees($this->elevage(), $this->lecteur())]);
    }
}
