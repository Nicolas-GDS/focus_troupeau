<?php

namespace App\Livewire\Dossier\Lecture;

use App\Livewire\Dossier\Lecture\Concerns\VoletDossier;
use App\Models\Fourrage\Elevage;
use App\Services\Agds\LectureAgdsService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Données AGDS de l'élevage (EF-M9-03, § 6.4) : identité de l'éleveur et composition du troupeau bovin, lues
 * à l'ouverture dans les vues de SAGEMA. Agents GDS seulement (agds.lire) ; élevage lié à travers les scopes.
 */
class Agds extends Component
{
    use VoletDossier;

    public function mount(Elevage $elevage): void
    {
        abort_unless($this->lecteur()->can('agds.lire'), 403);
        $this->monterVolet($elevage);
    }

    public function render(): View
    {
        abort_unless($this->lecteur()->can('agds.lire'), 403);

        return $this->volet('agds', 'Données AGDS', ['agds' => app(LectureAgdsService::class)->pour($this->elevage())]);
    }
}
