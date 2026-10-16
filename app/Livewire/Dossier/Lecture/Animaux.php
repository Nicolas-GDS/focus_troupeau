<?php

namespace App\Livewire\Dossier\Lecture;

use App\Livewire\Dossier\Lecture\Concerns\VoletDossier;
use App\Models\Fourrage\Elevage;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * E07 — volet Animaux (EF-M1-05) : mesures terrain par stade et profils sanguins, par date, avec les repères.
 */
class Animaux extends Component
{
    use VoletDossier;

    public function mount(Elevage $elevage): void
    {
        $this->monterVolet($elevage);
    }

    public function render(): View
    {
        return $this->volet('animaux', 'Animaux', ['dates' => $this->lecture()->animaux($this->elevage(), $this->lecteur())]);
    }
}
