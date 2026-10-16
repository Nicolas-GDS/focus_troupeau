<?php

namespace App\Livewire\Dossier\Saisie;

use App\Livewire\Dossier\Concerns\SaisieDeVisite;
use App\Models\Fourrage\Prelevement;
use App\Services\Dossier\VisiteService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Étape « analyser » — fourrages (EF-M1-02, EF-M1-09) : rattachement à la visite des lots prélevés dans l'élevage ;
 * chaque lot mène à l'interprétation de son bulletin (E20).
 */
class Prelevements extends Component
{
    use SaisieDeVisite {
        mount as monterVisite;
    }

    /** @var list<int|string> */
    public array $rattaches = [];

    public function mount(int $visiteId): void
    {
        $this->monterVisite($visiteId);
        $this->rattaches = Prelevement::query()->where('visite_id', $visiteId)->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    public function rattacher(VisiteService $service): void
    {
        $visite = $this->visite();
        $this->authorize('update', $visite);
        $this->validate(['rattaches' => ['array'], 'rattaches.*' => ['integer']]);

        $this->executer(fn () => $service->rattacherPrelevements($visite, array_map('intval', $this->rattaches)), 'Prélèvements rattachés à la visite.');
    }

    public function render(): View
    {
        $visite = $this->visite();
        $lots = DB::table('fourrage.prelevement as p')
            ->leftJoin('ref.type_fourrage as t', 't.code', '=', 'p.type_fourrage_code')
            ->leftJoin('fourrage.analyse as a', 'a.prelevement_id', '=', 'p.id')
            ->where('p.elevage_id', $visite->elevage_id)
            ->where(fn ($q) => $q->whereNull('p.visite_id')->orWhere('p.visite_id', $visite->id))
            ->orderByDesc('p.date_prelevement')
            ->get(['p.id', 'p.nom_lot', 'p.date_prelevement', 'p.campagne_code', 'p.visite_id', 't.libelle as type', 'a.id as analyse_id', 'a.num_echantillon_labo', 'a.statut as statut_analyse']);

        return view('livewire.dossier.saisie.prelevements', ['lots' => $lots, 'modifiable' => $this->modifiable()]);
    }
}
