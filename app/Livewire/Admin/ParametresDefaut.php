<?php

namespace App\Livewire\Admin;

use App\Domain\Referentiels\Edition\EditionRefusee;
use App\Domain\Referentiels\StatutEpistemique;
use App\Livewire\Admin\Concerns\AdministrateurCourant;
use App\Models\Ref\VersionReferentiel;
use App\Services\Cloisonnement\Perimetre;
use App\Services\Referentiels\EditionReferentielsService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * E24 — paramètres par défaut (EF-M10-02, app.parametre_defaut) : valeur nationale ou propre au GDS, source et statut
 * épistémique ; chaque modification est datée et porte une version des référentiels (décision du comité technique).
 * Un administrateur de GDS ne modifie que la valeur de ses GDS ; le super-administrateur modifie aussi la valeur
 * nationale.
 */
class ParametresDefaut extends Component
{
    use AdministrateurCourant;

    #[Url]
    public string $filtre = '';

    public ?string $cle = null;

    /** @var array{gds: string, valeur: string, source: string, statut: string, decision: string} */
    public array $saisie = ['gds' => '', 'valeur' => '', 'source' => '', 'statut' => '', 'decision' => ''];

    public function mount(): void
    {
        $this->authorize('viewAny', VersionReferentiel::class);
    }

    public function choisir(string $cle): void
    {
        $this->authorize('ecrire', VersionReferentiel::class);
        $ligne = DB::table('app.parametre_defaut')->where('cle', $cle)->whereNull('gds_id')->whereNull('valide_au')->orderByDesc('valide_du')->first();
        abort_if($ligne === null, 404);
        $this->cle = $cle;
        $this->saisie = ['gds' => $this->global() ? '' : (string) ($this->gdsAdmin()[0] ?? ''), 'valeur' => (string) (float) $ligne->valeur, 'source' => (string) $ligne->source,
            'statut' => (string) $ligne->statut_epistemique, 'decision' => ''];
        $this->resetErrorBag();
    }

    public function enregistrer(EditionReferentielsService $service): void
    {
        $this->authorize('ecrire', VersionReferentiel::class);
        $this->resetErrorBag();
        abort_if($this->cle === null, 404);
        $gds = $this->saisie['gds'] === '' ? null : (int) $this->saisie['gds'];
        if (($gds === null && ! $this->global()) || ($gds !== null && ! in_array($gds, $this->gdsAdmin(), true))) {
            $this->addError('saisie.gds', 'Valeur nationale réservée au super-administrateur ; choisir l\'un de vos GDS.');

            return;
        }
        $valeur = str_replace(',', '.', trim($this->saisie['valeur']));
        if (! is_numeric($valeur)) {
            $this->addError('saisie.valeur', 'Saisir un nombre.');

            return;
        }
        try {
            $service->modifierParametre($this->cle, $gds, (float) $valeur, $this->saisie['source'], $this->saisie['statut'], $this->saisie['decision'], $this->admin());
            session()->flash('status', 'Valeur enregistrée dans une nouvelle version des référentiels.');
            $this->cle = null;
        } catch (EditionRefusee $e) {
            foreach ($e->erreurs as $champ => $message) {
                $this->addError('saisie.'.$champ, $message);
            }
        }
    }

    public function render(EditionReferentielsService $service): View
    {
        $filtre = trim($this->filtre);
        $lignes = DB::table('app.parametre_defaut')->where(fn ($q) => $q->whereNull('valide_au')->orWhereDate('valide_au', '>=', today()))
            ->when($filtre !== '', fn ($q) => $q->where(fn ($q) => $q->where('cle', 'ilike', '%'.$filtre.'%')->orWhere('libelle', 'ilike', '%'.$filtre.'%')))
            ->orderBy('cle')->orderByRaw('gds_id NULLS FIRST')->get();

        return view('livewire.admin.parametres-defaut', [
            'lignes' => $lignes->groupBy('cle'),
            'versionCourante' => $service->versionCourante(),
            'statuts' => collect(StatutEpistemique::cases())->mapWithKeys(fn (StatutEpistemique $s) => [$s->value => $s->libelle().' ['.$s->codeSpecifications().']']),
            'listeGds' => DB::table('app.gds')->whereIn('id', $this->gdsAdmin())->pluck('nom', 'id'),
            'global' => $this->global(),
            'peutEcrire' => $this->admin()->can('referentiel.ecrire'),
            'historique' => $this->cle === null ? collect() : DB::table('app.parametre_defaut')->where('cle', $this->cle)->orderByDesc('valide_du')->limit(20)->get(),
        ]);
    }

    private function global(): bool
    {
        return $this->admin()->hasRole(Perimetre::ROLE_GLOBAL);
    }

    /** @return list<int> */
    private function gdsAdmin(): array
    {
        return DB::table('app.gds')->where('actif', true)
            ->when(! $this->global(), fn ($q) => $q->whereIn('id', app(Perimetre::class)->gdsAccessibles($this->admin())))
            ->orderBy('id')->pluck('id')->map(fn ($i) => (int) $i)->all();
    }
}
