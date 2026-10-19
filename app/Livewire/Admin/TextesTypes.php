<?php

namespace App\Livewire\Admin;

use App\Domain\Referentiels\Edition\EditionRefusee;
use App\Livewire\Admin\Concerns\AdministrateurCourant;
use App\Models\Ref\VersionReferentiel;
use App\Services\Referentiels\EditionReferentielsService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * E24 — textes types (EF-M10-03, ref.texte_type) : notifications, mentions de limites, repères affichés, notice ; une
 * modification clôt le texte en vigueur et crée une version des référentiels. Les variables « {nom} » doivent être
 * conservées.
 */
class TextesTypes extends Component
{
    use AdministrateurCourant;

    #[Url]
    public string $filtre = '';

    public ?string $code = null;

    public string $texte = '';

    public string $decision = '';

    public function mount(): void
    {
        $this->authorize('viewAny', VersionReferentiel::class);
    }

    public function choisir(string $code): void
    {
        $this->authorize('ecrire', VersionReferentiel::class);
        $texte = DB::table('ref.texte_type')->where('code', $code)->whereNull('version_fin')->value('texte');
        abort_if($texte === null, 404);
        $this->code = $code;
        $this->texte = (string) $texte;
        $this->decision = '';
        $this->resetErrorBag();
    }

    public function enregistrer(EditionReferentielsService $service): void
    {
        $this->authorize('ecrire', VersionReferentiel::class);
        $this->resetErrorBag();
        abort_if($this->code === null, 404);
        $ancien = (string) DB::table('ref.texte_type')->where('code', $this->code)->whereNull('version_fin')->value('texte');
        preg_match_all('/\{[a-z_]+\}/', $ancien, $avant);
        preg_match_all('/\{[a-z_]+\}/', $this->texte, $apres);
        $manquantes = array_diff(array_unique($avant[0]), $apres[0]);
        if ($manquantes !== []) {
            $this->addError('texte', 'Variables à conserver : '.implode(', ', $manquantes).'.');

            return;
        }
        try {
            $service->modifierTexte($this->code, $this->texte, $this->decision, $this->admin());
            session()->flash('status', 'Texte enregistré dans une nouvelle version des référentiels.');
            $this->reset('code', 'texte', 'decision');
        } catch (EditionRefusee $e) {
            foreach ($e->erreurs as $champ => $message) {
                $this->addError($champ, $message);
            }
        }
    }

    public function render(EditionReferentielsService $service): View
    {
        $filtre = trim($this->filtre);

        return view('livewire.admin.textes-types', [
            'textes' => DB::table('ref.texte_type')->whereNull('version_fin')
                ->when($filtre !== '', fn ($q) => $q->where(fn ($q) => $q->where('code', 'ilike', '%'.$filtre.'%')->orWhere('texte', 'ilike', '%'.$filtre.'%')))
                ->orderBy('code')->get(),
            'versionCourante' => $service->versionCourante(),
            'peutEcrire' => $this->admin()->can('referentiel.ecrire'),
            'historique' => $this->code === null ? collect() : DB::table('ref.texte_type')->where('code', $this->code)->orderByDesc('id')->get(),
        ]);
    }
}
