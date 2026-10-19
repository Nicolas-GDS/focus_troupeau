<?php

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\AdministrateurCourant;
use App\Services\Administration\JournalLecture;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * E23 — journal d'audit (EF-M10-01, ENF-SEC-08) : connexions et actions sensibles, en lecture seule, pour
 * l'administrateur (journal.lire), dans le périmètre de ses GDS.
 */
class Journal extends Component
{
    use AdministrateurCourant, WithPagination;

    #[Url]
    public string $action = '';

    #[Url]
    public string $compte = '';

    #[Url]
    public string $elevage = '';

    #[Url]
    public string $du = '';

    #[Url]
    public string $au = '';

    public function mount(): void
    {
        abort_unless($this->admin()->can('journal.lire'), 403);
    }

    public function updating(string $propriete): void
    {
        if (in_array($propriete, ['action', 'compte', 'elevage', 'du', 'au'], true)) {
            $this->resetPage();
        }
    }

    public function render(JournalLecture $journal): View
    {
        return view('livewire.admin.journal', [
            'entrees' => $journal->entrees($this->admin(), ['action' => $this->action ?: null, 'compte' => trim($this->compte) ?: null,
                'elevage' => trim($this->elevage) ?: null, 'du' => $this->du ?: null, 'au' => $this->au ?: null]),
            'actions' => $journal->actions(),
        ]);
    }
}
