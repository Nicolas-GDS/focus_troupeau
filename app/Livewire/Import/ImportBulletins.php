<?php

namespace App\Livewire\Import;

use App\Models\App\Gds;
use App\Models\App\ImportBulletin;
use App\Models\App\User;
use App\Models\Fourrage\Analyse;
use App\Models\Fourrage\Elevage;
use App\Services\ElevageCourant;
use App\Services\Import\ImportBulletinService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * E19 — Import des bulletins (EF-M9-01, EF-M2-01, RC-2) : dépôt de PDF, suivi des imports, rapprochement manuel,
 * traitement des anomalies. Chaque action est autorisée côté serveur (ENF-SEC-04) ; les listes passent par les
 * scopes de cloisonnement.
 */
class ImportBulletins extends Component
{
    use WithFileUploads;

    /** @var list<TemporaryUploadedFile> */
    public array $fichiers = [];

    public ?int $gdsId = null;

    /** @var array<int, string> élevage choisi par import à rapprocher */
    public array $elevagesChoisis = [];

    /** @var array<int, string> motif saisi par analyse en anomalie */
    public array $motifs = [];

    public function mount(): void
    {
        $this->authorize('viewAny', ImportBulletin::class);

        $gds = $this->gdsDisponibles();
        $this->gdsId = $gds->count() === 1 ? (int) $gds->first()?->id : null;
    }

    public function deposer(ImportBulletinService $service): void
    {
        $this->validate([
            'gdsId' => ['required', 'integer'],
            'fichiers' => ['required', 'array', 'min:1', 'max:60'],
            // ENF-SEC-05 : type vérifié (extension et contenu), 20 Mo au plus
            'fichiers.*' => ['file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:20480'],
        ], [], ['gdsId' => 'GDS', 'fichiers' => 'bulletins', 'fichiers.*' => 'bulletin']);

        $this->authorize('create', [ImportBulletin::class, (int) $this->gdsId]);

        foreach ($this->fichiers as $fichier) {
            $service->deposer($fichier->getRealPath(), $fichier->getClientOriginalName(), (int) $this->gdsId, $this->utilisateur());
        }

        session()->flash('status', count($this->fichiers).' bulletin(s) déposé(s) : l\'extraction est en cours.');
        $this->reset('fichiers');
    }

    public function rapprocher(int $importId, ImportBulletinService $service): void
    {
        $import = ImportBulletin::query()->findOrFail($importId);
        $this->authorize('rapprocher', $import);

        $this->validate(["elevagesChoisis.{$importId}" => ['required', 'uuid']], [], ["elevagesChoisis.{$importId}" => 'élevage']);
        $elevage = Elevage::query()->where('gds_id', $import->gds_id)->findOrFail($this->elevagesChoisis[$importId]);

        $import = $service->rapprocher($import, $elevage, $this->utilisateur());
        unset($this->elevagesChoisis[$importId]);

        session()->flash('status', 'Bulletin rapproché : '.self::libelleStatut($import->statut)['libelle'].'.');
    }

    public function leverAnomalie(int $analyseId, ImportBulletinService $service): void
    {
        $analyse = Analyse::query()->findOrFail($analyseId);
        $this->authorize('leverAnomalie', $analyse);
        $this->validerMotif($analyseId);

        $service->leverAnomalie($analyse, $this->motifs[$analyseId], $this->utilisateur());
        unset($this->motifs[$analyseId]);

        session()->flash('status', 'Anomalie levée : le bulletin est contrôlé et réintégré dans les références.');
    }

    public function annuler(int $analyseId, ImportBulletinService $service): void
    {
        $analyse = Analyse::query()->findOrFail($analyseId);
        $this->authorize('annuler', $analyse);
        $this->validerMotif($analyseId);

        $service->annuler($analyse, $this->motifs[$analyseId], $this->utilisateur());
        unset($this->motifs[$analyseId]);

        session()->flash('status', 'Bulletin annulé.');
    }

    public function render(): View
    {
        $imports = ImportBulletin::query()->with('analyse')->orderByDesc('cree_le')->orderByDesc('id')->limit(100)->get();

        return view('livewire.import.import-bulletins', [
            'imports' => $imports,
            'gdsDisponibles' => $this->gdsDisponibles(),
            'elevagesParGds' => app(ElevageCourant::class)->accessibles()->groupBy('gds_id'),
        ]);
    }

    /**
     * Libellé et état visuel d'un statut d'import : la couleur n'est jamais seule (EF-GEN-03).
     *
     * @return array{libelle: string, etat: string}
     */
    public static function libelleStatut(string $statut): array
    {
        return match ($statut) {
            'recu' => ['libelle' => 'Reçu', 'etat' => 'information'],
            'en_extraction' => ['libelle' => 'Extraction en cours', 'etat' => 'information'],
            'importe' => ['libelle' => 'Importé et contrôlé', 'etat' => 'couvert'],
            'a_rapprocher' => ['libelle' => 'À rapprocher', 'etat' => 'vigilance'],
            'anomalie' => ['libelle' => 'Anomalie', 'etat' => 'critique'],
            'doublon' => ['libelle' => 'Doublon', 'etat' => 'information'],
            'erreur' => ['libelle' => 'Erreur', 'etat' => 'alerte'],
            default => ['libelle' => $statut, 'etat' => 'information'],
        };
    }

    /**
     * @return Collection<int, Gds>
     */
    private function gdsDisponibles(): Collection
    {
        return Gds::query()->orderBy('id')->get()->filter(fn (Gds $gds) => Auth::user()?->can('create', [ImportBulletin::class, $gds->id]))->values();
    }

    private function validerMotif(int $analyseId): void
    {
        $this->validate(["motifs.{$analyseId}" => ['required', 'string', 'min:5', 'max:500']], [], ["motifs.{$analyseId}" => 'motif']);
    }

    private function utilisateur(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            throw new RuntimeException('Utilisateur non connecté.');
        }

        return $user;
    }
}
