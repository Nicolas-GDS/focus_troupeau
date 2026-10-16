<?php

namespace App\Livewire\Dossier\Lecture;

use App\Livewire\Dossier\Lecture\Concerns\VoletDossier;
use App\Models\Fourrage\Analyse;
use App\Models\Fourrage\Elevage;
use App\Models\Fourrage\Prelevement;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * E04 — détail d'un lot (EF-M1-02) : résultats avec classe, repère et source (EF-GEN-02, EF-GEN-03), lectures du
 * technicien dans l'ordre imposé, utilisation conseillée, bulletin PDF original. Un lot sans bulletin lisible est
 * introuvable (404). La position dans la campagne (RC-3) rejoint ce détail au lot L4.
 */
class Fourrage extends Component
{
    use VoletDossier;

    #[Locked]
    public int $prelevementId;

    public function mount(Elevage $elevage, Prelevement $prelevement): void
    {
        abort_unless($prelevement->elevage_id === $elevage->id, 404);
        $this->monterVolet($elevage);
        $this->prelevementId = $prelevement->id;
        abort_if($this->lecture()->fourrage($prelevement, $this->lecteur()) === [], 404);
    }

    public function render(): View
    {
        $prelevement = Prelevement::query()->with('typeFourrage')->where('elevage_id', $this->elevageId)->findOrFail($this->prelevementId);
        $lots = $this->lecture()->fourrage($prelevement, $this->lecteur());

        return $this->volet('fourrage', ($prelevement->typeFourrage->libelle ?? $prelevement->type_fourrage_code).($prelevement->nom_lot ? ' — '.$prelevement->nom_lot : ''), [
            'prelevement' => $prelevement,
            'lots' => $lots,
            'categories' => DB::table('ref.categorie_animale')->pluck('libelle', 'code'),
            'lienPdf' => fn (Analyse $a) => $a->fichier_pdf === null ? null : URL::temporarySignedRoute('bulletins.pdf', now()->addMinutes(30), ['analyse' => $a->id]),
        ]);
    }
}
