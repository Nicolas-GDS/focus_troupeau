<?php

namespace App\Livewire\Elevages;

use App\Livewire\Admin\Concerns\AdministrateurCourant;
use App\Models\Fourrage\Elevage;
use App\Services\Administration\ElevagesService;
use App\Services\Agds\PreremplissageAgds;
use App\Services\Dossier\SaisieRefusee;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * E23 — élevages (EF-M0-02) : liste des élevages du périmètre avec l'état de leur convention, création d'un élevage et
 * de son identité. Technicien et administrateur (elevage.ecrire).
 */
class Liste extends Component
{
    use AdministrateurCourant, WithPagination;

    #[Url]
    public string $recherche = '';

    /** @var array<string, mixed> */
    public array $nouveau = ['gds_id' => '', 'raison_sociale' => '', 'num_cheptel_ede' => '', 'nom_contact' => '', 'code_postal' => '', 'courriel' => '', 'telephone' => '', 'especes' => ['BOV'], 'consentement_donnees' => false];

    public function mount(ElevagesService $service): void
    {
        abort_unless($this->admin()->can('elevage.ecrire'), 403);
        $this->nouveau['gds_id'] = (string) ($service->gdsPossibles($this->admin())[0] ?? '');
    }

    public function updatedRecherche(): void
    {
        $this->resetPage();
    }

    /** Message du pré-remplissage depuis AGDS (succès ou avertissement), affiché sous le numéro EDE. */
    public ?string $messageAgds = null;

    /**
     * Pré-remplit l'identité depuis AGDS à partir du numéro EDE et du GDS choisi (agds.lire) : seuls les champs connus
     * d'AGDS sont remplacés, les autres saisies sont gardées.
     */
    public function preremplirAgds(PreremplissageAgds $preremplissage): void
    {
        abort_unless($this->admin()->can('agds.lire'), 403);
        $this->messageAgds = null;
        $this->resetErrorBag('nouveau.num_cheptel_ede');

        try {
            $proposition = $preremplissage->proposer((string) $this->nouveau['num_cheptel_ede'], (int) $this->nouveau['gds_id'], $this->admin());
        } catch (SaisieRefusee $e) {
            $this->addError('nouveau.num_cheptel_ede', $e->getMessage());

            return;
        }
        $this->nouveau = [...$this->nouveau, ...$proposition['champs']];
        $this->messageAgds = $proposition['message'];
    }

    public function creer(ElevagesService $service): void
    {
        $this->validate(['nouveau.gds_id' => ['required', 'integer'], 'nouveau.raison_sociale' => ['required', 'string', 'max:200'], 'nouveau.courriel' => ['nullable', 'email'],
            'nouveau.especes' => ['array'], 'nouveau.code_postal' => ['nullable', 'regex:/^\d{5}$/']],
            [], ['nouveau.raison_sociale' => 'raison sociale', 'nouveau.courriel' => 'e-mail', 'nouveau.code_postal' => 'code postal']);
        $elevage = null;
        if ($this->executer(function () use ($service, &$elevage) {
            $elevage = $service->creer($this->nouveau, $this->admin());
        }, 'Élevage créé.', 'nouveau')) {
            $this->redirectRoute('elevages.fiche', $elevage->id);
        }
    }

    public function render(ElevagesService $service): View
    {
        $recherche = trim($this->recherche);
        $elevages = Elevage::query()
            ->when($recherche !== '', fn ($q) => $q->where(fn ($q) => $q->where('code_pseudo', 'ilike', '%'.$recherche.'%')
                ->orWhereIn('id', DB::table('prive.elevage_identite')->select('elevage_id')->where(fn ($q) => $q->where('raison_sociale', 'ilike', '%'.$recherche.'%')->orWhere('num_cheptel_ede', 'ilike', '%'.$recherche.'%')))))
            ->orderBy('code_pseudo')->paginate(30);
        $ids = $elevages->getCollection()->pluck('id')->all();

        return view('livewire.elevages.liste', [
            'elevages' => $elevages,
            'identites' => DB::table('prive.elevage_identite')->whereIn('elevage_id', $ids)->get(['elevage_id', 'raison_sociale', 'num_cheptel_ede'])->keyBy('elevage_id'),
            'conventions' => DB::table('app.convention')->whereIn('elevage_id', $ids)->orderBy('date_fin')->get(['elevage_id', 'statut', 'date_fin'])->keyBy('elevage_id'),
            'gds' => DB::table('app.gds')->whereIn('id', $service->gdsPossibles($this->admin()))->pluck('nom', 'id'),
            'especes' => ElevagesService::ESPECES,
            'preremplissageAgds' => config('agds.actif') && $this->admin()->can('agds.lire'),
        ])->layout('components.layouts.espace', ['titre' => 'Élevages']);
    }
}
