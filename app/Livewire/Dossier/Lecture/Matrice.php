<?php

namespace App\Livewire\Dossier\Lecture;

use App\Livewire\Dossier\Lecture\Concerns\VoletDossier;
use App\Models\Fourrage\Elevage;
use App\Services\Dossier\FicheVisiteService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * E05 — matrice de vigilance minérale (EF-M1-03) : lots × éléments, valeur et classe, pour une campagne. La variante
 * « ration » (RC-9) rejoint ce volet au lot L5.
 */
class Matrice extends Component
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
        return $this->volet('matrice', 'Matrice de vigilance minérale', [
            'matrice' => $this->lecture()->matrice($this->elevage(), $this->lecteur(), $this->campagne),
            'elements' => FicheVisiteService::PARAMETRES_MATRICE,
            'libelles' => DB::table('ref.parametre')->whereIn('code', FicheVisiteService::PARAMETRES_MATRICE)->pluck('libelle', 'code'),
        ]);
    }
}
