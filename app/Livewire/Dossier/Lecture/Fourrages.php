<?php

namespace App\Livewire\Dossier\Lecture;

use App\Livewire\Dossier\Lecture\Concerns\VoletDossier;
use App\Models\Fourrage\Elevage;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * E04 — volet Fourrages (EF-M1-02) : lots analysés lisibles, par campagne, avec leur niveau et l'utilisation conseillée.
 */
class Fourrages extends Component
{
    use VoletDossier;

    public function mount(Elevage $elevage): void
    {
        $this->monterVolet($elevage);
    }

    public function render(): View
    {
        return $this->volet('fourrages', 'Fourrages', [
            'campagnes' => $this->lecture()->fourrages($this->elevage(), $this->lecteur()),
        ]);
    }
}
