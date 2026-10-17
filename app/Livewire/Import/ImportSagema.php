<?php

namespace App\Livewire\Import;

use App\Livewire\Admin\Concerns\AdministrateurCourant;
use App\Services\Administration\ElevagesService;
use App\Services\Import\ImportSagemaService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Import SAGEMA (EF-M9-03, T-27) : dépôt du fichier des élevages ou des indicateurs du bilan sanitaire, aperçu de ce
 * qui sera créé, mis à jour, laissé inchangé ou rejeté, puis import et rapport. Technicien (sagema.importer).
 */
class ImportSagema extends Component
{
    use AdministrateurCourant, WithFileUploads;

    public string $type = 'elevages';

    public string $gds = '';

    /** @var TemporaryUploadedFile|null */
    public $fichier = null;

    /** @var array{lignes: list<array<string, mixed>>, totaux: array<string, int>}|null */
    public ?array $rapport = null;

    public bool $importe = false;

    public function mount(ElevagesService $elevages): void
    {
        abort_unless($this->admin()->can('sagema.importer'), 403);
        $this->gds = (string) ($elevages->gdsPossibles($this->admin())[0] ?? '');
    }

    public function updated(string $propriete): void
    {
        if (in_array($propriete, ['type', 'fichier', 'gds'], true)) {
            $this->reset('rapport', 'importe');
        }
    }

    public function apercu(ImportSagemaService $service): void
    {
        $this->traiter($service, false);
    }

    public function importer(ImportSagemaService $service): void
    {
        $this->traiter($service, true);
    }

    public function render(ElevagesService $elevages): View
    {
        return view('livewire.import.import-sagema', [
            'listeGds' => DB::table('app.gds')->whereIn('id', $elevages->gdsPossibles($this->admin()))->pluck('nom', 'id'),
            'colonnes' => $this->type === 'elevages' ? ImportSagemaService::COLONNES_ELEVAGES : ImportSagemaService::COLONNES_INDICATEURS,
        ])->layout('components.layouts.espace', ['titre' => 'Import SAGEMA']);
    }

    private function traiter(ImportSagemaService $service, bool $appliquer): void
    {
        $this->validate(['fichier' => ['required', 'file', 'max:10240', 'mimes:csv,txt'], 'type' => ['required', 'in:elevages,indicateurs']],
            [], ['fichier' => 'fichier']);
        $contenu = (string) file_get_contents($this->fichier->getRealPath());
        $this->executer(function () use ($service, $contenu, $appliquer) {
            $this->rapport = $this->type === 'elevages'
                ? $service->elevages($contenu, (int) $this->gds, $this->admin(), $appliquer)
                : $service->indicateurs($contenu, $this->admin(), $appliquer);
            $this->importe = $appliquer;
        }, $appliquer ? 'Import terminé.' : 'Aperçu prêt : rien n\'est encore écrit.', 'fichier');
    }
}
