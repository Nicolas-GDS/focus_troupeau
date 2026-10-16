<?php

namespace App\Livewire\Dossier\Lecture;

use App\Livewire\Dossier\Lecture\Concerns\VoletDossier;
use App\Models\Fourrage\Elevage;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * E06 — volet Eau (EF-M1-04) : analyses d'eau lisibles, classées par la grille eau d'abreuvement.
 */
class Eau extends Component
{
    use VoletDossier;

    public function mount(Elevage $elevage): void
    {
        $this->monterVolet($elevage);
    }

    public function render(): View
    {
        return $this->volet('eau', 'Eau d\'abreuvement', ['analyses' => $this->lecture()->eau($this->elevage(), $this->lecteur())]);
    }
}
