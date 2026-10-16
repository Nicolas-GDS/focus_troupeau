<?php

namespace App\Livewire\Dossier\Lecture;

use App\Livewire\Dossier\Lecture\Concerns\VoletDossier;
use App\Models\Fourrage\Elevage;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * E09 — bilan sanitaire et événements (EF-M1-07) : indicateurs d'une campagne positionnés sur les seuils S1/S2,
 * événements sanitaires déclarés.
 */
class Sanitaire extends Component
{
    use VoletDossier;

    #[Url]
    public ?string $campagne = null;

    public function mount(Elevage $elevage): void
    {
        $this->monterVolet($elevage);
    }

    public function render(): View
    {
        return $this->volet('sanitaire', 'Bilan sanitaire', ['bilan' => $this->lecture()->sanitaire($this->elevage(), $this->campagne)]);
    }
}
