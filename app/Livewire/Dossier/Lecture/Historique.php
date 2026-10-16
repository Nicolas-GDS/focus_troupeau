<?php

namespace App\Livewire\Dossier\Lecture;

use App\Livewire\Dossier\Lecture\Concerns\VoletDossier;
use App\Models\Fourrage\Elevage;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * E10 — historique (EF-M1-08) : campagnes et visites de l'élevage, niveaux, messages et décisions du plan d'action,
 * lots analysés. Les courbes pluriannuelles par paramètre (RC-4) rejoignent ce volet au lot L4.
 */
class Historique extends Component
{
    use VoletDossier;

    public function mount(Elevage $elevage): void
    {
        $this->monterVolet($elevage);
    }

    public function render(): View
    {
        return $this->volet('historique', 'Historique', ['campagnes' => $this->lecture()->historique($this->elevage(), $this->lecteur())]);
    }
}
