<?php

namespace App\Livewire\Pilotage;

use App\Livewire\Admin\Concerns\AdministrateurCourant;
use App\Services\Pilotage\PilotageService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * E25 — indicateurs de pilotage (EF-M10-04) par GDS et par campagne, avec leurs définitions et les exports CSV
 * (indicateurs, références agrégées). Administrateur et technicien (pilotage.lire), dans leurs GDS.
 */
class Indicateurs extends Component
{
    use AdministrateurCourant;

    #[Url]
    public string $gds = '';

    #[Url]
    public string $campagne = '';

    public function mount(PilotageService $pilotage): void
    {
        abort_unless($this->admin()->can('pilotage.lire'), 403);
        $this->gds = $this->gds !== '' ? $this->gds : (string) (array_key_first($pilotage->gds($this->admin())) ?? '');
        $this->campagne = $this->campagne !== '' ? $this->campagne : (string) ($pilotage->campagnes()[0] ?? '');
    }

    public function render(PilotageService $pilotage): View
    {
        $listeGds = $pilotage->gds($this->admin());

        return view('livewire.pilotage.indicateurs', [
            'listeGds' => $listeGds,
            'campagnes' => $pilotage->campagnes(),
            'indicateurs' => $this->gds === '' || $this->campagne === '' || ! isset($listeGds[(int) $this->gds]) ? null : $pilotage->indicateurs((int) $this->gds, $this->campagne, $this->admin()),
        ])->layout('components.layouts.espace', ['titre' => 'Indicateurs de pilotage']);
    }
}
