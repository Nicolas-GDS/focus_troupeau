<?php

namespace App\Livewire\Dossier\Lecture;

use App\Livewire\Dossier\Lecture\Concerns\VoletDossier;
use App\Models\Fourrage\Elevage;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * E10 — historique (EF-M1-08, RC-4, EF-M3-05) : courbes pluriannuelles par type de fourrage (MAT, UFL, cuivre, zinc)
 * avec la variation entre les deux dernières campagnes, sans classe ; puis, par campagne, les visites (niveau,
 * messages), les lots analysés et la frise des décisions (actions des plans publiés, recommandations des lots).
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
        $elevage = $this->elevage();

        return $this->volet('historique', 'Historique', [
            'evolution' => $this->lecture()->evolution($elevage, $this->lecteur()),
            'campagnes' => $this->lecture()->historique($elevage, $this->lecteur()),
            'decisions' => $this->lecture()->decisions($elevage, $this->lecteur()),
        ]);
    }
}
