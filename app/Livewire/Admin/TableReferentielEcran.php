<?php

namespace App\Livewire\Admin;

use App\Domain\Referentiels\Edition\EditionRefusee;
use App\Domain\Referentiels\Edition\TableReferentiel;
use App\Domain\Referentiels\StatutEpistemique;
use App\Livewire\Admin\Concerns\AdministrateurCourant;
use App\Models\Ref\VersionReferentiel;
use App\Services\Referentiels\EditionReferentielsService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * E24 — édition d'un référentiel versionné décrit par TableReferentiel (besoins journaliers, repères des mesures
 * terrain et des profils sanguins, seuils du bilan sanitaire ; EF-M10-02, lot L8) : lignes en vigueur filtrables,
 * création, modification et retrait, chacun dans une nouvelle version portant la décision du comité technique.
 */
class TableReferentielEcran extends Component
{
    use AdministrateurCourant;

    #[Locked]
    public string $table;

    public string $filtre = '';

    #[Locked]
    public ?int $editionId = null;

    public bool $formulaireOuvert = false;

    /** @var array<string, mixed> */
    public array $saisie = [];

    public string $decision = '';

    public function mount(string $table): void
    {
        abort_unless(isset(TableReferentiel::tous()[$table]), 404);
        $this->authorize('viewAny', VersionReferentiel::class);
        $this->table = $table;
    }

    public function nouveau(): void
    {
        $this->authorize('ecrire', VersionReferentiel::class);
        $this->editionId = null;
        $this->saisie = array_fill_keys(array_keys($this->descripteur()->colonnes), null);
        $this->saisie['statut_epistemique'] = StatutEpistemique::HypotheseLocale->value;
        $this->ouvrir();
    }

    public function modifier(int $id): void
    {
        $this->authorize('ecrire', VersionReferentiel::class);
        $ligne = (array) (DB::table($this->descripteur()->table)->where('id', $id)->whereNull('version_fin')->first() ?? abort(404));
        $this->editionId = $id;
        $this->saisie = array_map(fn ($v) => is_bool($v) ? $v : ($v === null ? null : (string) $v), array_intersect_key($ligne, $this->descripteur()->colonnes));
        $this->ouvrir();
    }

    public function enregistrer(EditionReferentielsService $service): void
    {
        $this->authorize('ecrire', VersionReferentiel::class);
        $this->resetErrorBag();
        try {
            $service->enregistrerLigne($this->descripteur(), $this->editionId, $this->saisie, $this->decision, $this->admin());
            session()->flash('status', 'Ligne enregistrée dans une nouvelle version des référentiels.');
            $this->fermer();
        } catch (EditionRefusee $e) {
            foreach ($e->erreurs as $champ => $message) {
                $this->addError($champ === 'decision' ? 'decision' : 'saisie.'.$champ, $message);
            }
        } catch (QueryException $e) {
            $this->addError('saisie', 'Enregistrement refusé par la base (valeur incohérente avec le schéma).');
        }
    }

    public function retirer(int $id, EditionReferentielsService $service): void
    {
        $this->authorize('ecrire', VersionReferentiel::class);
        $this->resetErrorBag();
        try {
            $service->retirerLigne($this->descripteur(), $id, $this->decision, $this->admin());
            session()->flash('status', 'Ligne retirée dans une nouvelle version des référentiels.');
            $this->fermer();
        } catch (EditionRefusee $e) {
            $this->addError('decision', implode(' ', $e->erreurs));
        }
    }

    public function fermer(): void
    {
        $this->formulaireOuvert = false;
        $this->editionId = null;
        $this->decision = '';
    }

    public function render(EditionReferentielsService $service): View
    {
        $d = $this->descripteur();
        $filtre = trim($this->filtre);
        $lignes = DB::table($d->table)->whereNull('version_fin')
            ->when($d->avecActif, fn ($q) => $q->where('actif', true))
            ->when($filtre !== '', fn ($q) => $q->where(fn ($q) => collect($d->identification)->each(fn ($c) => $q->orWhere($c, 'ilike', '%'.$filtre.'%'))))
            ->orderBy($d->identification[0])->orderBy('id')->get();

        return view('livewire.admin.table-referentiel', [
            'd' => $d,
            'lignes' => $lignes,
            'choix' => $service->choix($d),
            'references' => DB::table('ref.reference_biblio')->orderBy('cle')->pluck('cle', 'id'),
            'statuts' => collect(StatutEpistemique::cases())->mapWithKeys(fn (StatutEpistemique $s) => [$s->value => $s->libelle().' ['.$s->codeSpecifications().']']),
            'versionCourante' => $service->versionCourante(),
            'peutEcrire' => $this->admin()->can('referentiel.ecrire'),
            'retirees' => DB::table($d->table)->whereNotNull('version_fin')->count(),
        ]);
    }

    private function ouvrir(): void
    {
        $this->decision = '';
        $this->resetErrorBag();
        $this->formulaireOuvert = true;
    }

    private function descripteur(): TableReferentiel
    {
        return TableReferentiel::tous()[$this->table];
    }
}
