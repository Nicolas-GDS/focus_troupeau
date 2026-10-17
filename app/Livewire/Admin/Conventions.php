<?php

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\AdministrateurCourant;
use App\Models\App\Convention;
use App\Services\Administration\ConventionsService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * E23 — conventions (EF-M0-06, § 2.4) : liste par statut et échéance, prolongation, résiliation (administrateur).
 * La création se fait depuis la fiche de l'élevage.
 */
class Conventions extends Component
{
    use AdministrateurCourant, WithPagination;

    #[Url]
    public string $statut = 'active';

    #[Url]
    public string $recherche = '';

    public ?int $choisie = null;

    public string $nouvelleFin = '';

    public string $motif = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Convention::class);
    }

    public function choisir(int $id): void
    {
        $this->choisie = $this->convention($id)->id;
        $this->reset('nouvelleFin', 'motif');
        $this->resetErrorBag();
    }

    public function prolonger(ConventionsService $service): void
    {
        $convention = $this->convention((int) $this->choisie);
        $this->authorize('update', $convention);
        $this->validate(['nouvelleFin' => ['required', 'date']], [], ['nouvelleFin' => 'nouvelle date de fin']);
        $this->executer(fn () => $service->prolonger($convention, $this->nouvelleFin, $this->admin()), 'Convention prolongée.', 'convention');
    }

    public function resilier(ConventionsService $service): void
    {
        $convention = $this->convention((int) $this->choisie);
        abort_unless($this->admin()->can('convention.resilier'), 403);
        $this->executer(fn () => $service->resilier($convention, $this->motif, $this->admin()), 'Convention résiliée.', 'convention');
    }

    public function render(): View
    {
        $recherche = trim($this->recherche);
        $conventions = Convention::query()
            ->when($this->statut !== '', fn ($q) => $q->where('statut', $this->statut))
            ->when($recherche !== '', fn ($q) => $q->whereIn('elevage_id', DB::table('fourrage.elevage as e')->leftJoin('prive.elevage_identite as i', 'i.elevage_id', '=', 'e.id')
                ->where(fn ($q) => $q->where('e.code_pseudo', 'ilike', '%'.$recherche.'%')->orWhere('i.raison_sociale', 'ilike', '%'.$recherche.'%'))->select('e.id')))
            ->orderBy('date_fin')->orderBy('id')->paginate(30);
        $ids = $conventions->getCollection()->pluck('elevage_id')->all();

        return view('livewire.admin.conventions', [
            'conventions' => $conventions,
            'elevages' => DB::table('fourrage.elevage as e')->leftJoin('prive.elevage_identite as i', 'i.elevage_id', '=', 'e.id')->whereIn('e.id', $ids)
                ->get(['e.id', 'e.code_pseudo', 'i.raison_sociale'])->keyBy('id'),
            'selection' => $this->choisie === null ? null : $this->convention($this->choisie),
            'rappel' => (int) DB::table('app.parametre_defaut')->where('cle', 'convention.rappel_jours')->whereNull('gds_id')->whereNull('valide_au')->value('valeur'),
            'peutResilier' => $this->admin()->can('convention.resilier'),
        ]);
    }

    private function convention(int $id): Convention
    {
        return Convention::query()->findOrFail($id);
    }
}
