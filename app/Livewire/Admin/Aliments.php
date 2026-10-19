<?php

namespace App\Livewire\Admin;

use App\Domain\Referentiels\StatutEpistemique;
use App\Livewire\Admin\Concerns\AdministrateurCourant;
use App\Models\Ref\VersionReferentiel;
use App\Services\Referentiels\AlimentsReferenceService;
use App\Services\Referentiels\EditionReferentielsService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * E24 — aliments de table (EF-M10-02) : saisie des aliments de référence et de leurs valeurs, en attendant le
 * chargement des tables INRAE-CIRAD-AFZ (point ouvert 5). Administrateur.
 */
class Aliments extends Component
{
    use AdministrateurCourant;

    public ?int $alimentId = null;

    public bool $formulaireOuvert = false;

    /** @var array<string, mixed> */
    public array $aliment = [];

    /** @var array<string, string> */
    public array $valeurs = [];

    public function mount(): void
    {
        $this->authorize('viewAny', VersionReferentiel::class);
    }

    public function nouveau(): void
    {
        $this->authorize('ecrire', VersionReferentiel::class);
        $this->alimentId = null;
        $this->aliment = ['code' => '', 'libelle' => '', 'categorie' => 'correcteur_azote', 'famille_code' => '', 'ms_pct' => '', 'reference_id' => '',
            'statut_epistemique' => StatutEpistemique::HypotheseLocale->value, 'actif' => true];
        $this->valeurs = array_fill_keys(AlimentsReferenceService::PARAMETRES, '');
        $this->formulaireOuvert = true;
        $this->resetErrorBag();
    }

    public function modifier(int $id): void
    {
        $this->authorize('ecrire', VersionReferentiel::class);
        $a = (array) (DB::table('ref.aliment_reference')->where('id', $id)->first() ?? abort(404));
        $this->alimentId = $id;
        $this->aliment = [...array_map(fn ($v) => is_bool($v) ? $v : (string) $v, $a)];
        $existantes = DB::table('ref.aliment_reference_valeur')->where('aliment_reference_id', $id)->pluck('valeur', 'parametre_code');
        $this->valeurs = array_map(fn ($p) => isset($existantes[$p]) ? (string) (float) $existantes[$p] : '', array_combine(AlimentsReferenceService::PARAMETRES, AlimentsReferenceService::PARAMETRES));
        $this->formulaireOuvert = true;
        $this->resetErrorBag();
    }

    public function enregistrer(AlimentsReferenceService $service): void
    {
        $this->authorize('ecrire', VersionReferentiel::class);
        $this->resetErrorBag();
        $this->executer(function () use ($service) {
            $service->enregistrer($this->alimentId, $this->aliment, $this->valeurs, $this->admin());
            $this->formulaireOuvert = false;
        }, 'Aliment enregistré.', 'aliment');
    }

    public function render(EditionReferentielsService $edition): View
    {
        return view('livewire.admin.aliments', [
            'aliments' => DB::table('ref.aliment_reference')->orderBy('categorie')->orderBy('libelle')->get(),
            'categories' => AlimentsReferenceService::CATEGORIES,
            'parametres' => DB::table('ref.parametre')->whereIn('code', AlimentsReferenceService::PARAMETRES)->get(['code', 'libelle', 'unite'])->keyBy('code'),
            'familles' => DB::table('ref.famille_fourrage')->pluck('libelle', 'code'),
            'references' => DB::table('ref.reference_biblio')->orderBy('cle')->pluck('cle', 'id'),
            'statuts' => collect(StatutEpistemique::cases())->mapWithKeys(fn (StatutEpistemique $s) => [$s->value => $s->libelle().' ['.$s->codeSpecifications().']']),
            'versionCourante' => $edition->versionCourante(),
            'peutEcrire' => $this->admin()->can('referentiel.ecrire'),
        ]);
    }
}
