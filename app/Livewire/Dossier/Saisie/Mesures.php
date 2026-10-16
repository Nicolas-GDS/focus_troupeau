<?php

namespace App\Livewire\Dossier\Saisie;

use App\Livewire\Dossier\Concerns\SaisieDeVisite;
use App\Models\Dossier\MesureTerrain;
use App\Services\Dossier\MesuresService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Étape « mesurer » — mesures terrain (EF-M1-05, RC-1.5, EF-GEN-07) : saisie une ligne à la fois sur téléphone
 * (la date, l'espèce, le stade et la mesure restent choisis d'une ligne à l'autre), lecture immédiate, groupes
 * par stade avec médiane, repère, source et lectures combinées proposées ; validation des mesures de la visite.
 */
class Mesures extends Component
{
    use SaisieDeVisite {
        mount as monterVisite;
    }

    public string $date = '';

    public string $espece = 'BOV';

    public string $stade = '';

    public string $mesure = '';

    public string $animal = '';

    public ?string $valeur = null;

    public function mount(int $visiteId): void
    {
        $this->monterVisite($visiteId);
        $visite = $this->visite();
        $this->date = $visite->date_visite->toDateString();
        $this->espece = $visite->elevage->listeEspeces()[0] ?? 'BOV';
    }

    public function ajouter(MesuresService $service): void
    {
        $visite = $this->visite();
        $this->authorize('create', [MesureTerrain::class, $visite->elevage, $visite->id]);
        $this->validate([
            'date' => ['required', 'date'],
            'espece' => ['required', Rule::in(self::codes('ref.espece_animale'))],
            'stade' => ['nullable', Rule::in(array_keys(MesuresService::STADES))],
            'mesure' => ['required', Rule::in(self::codes('ref.mesure_terrain'))],
            'animal' => ['nullable', 'string', 'max:40'],
            'valeur' => ['required', 'numeric'],
        ], [], ['espece' => 'espèce', 'animal' => 'n° de travail']);

        $this->executer(function () use ($service, $visite) {
            $service->ajouter($visite, $this->utilisateur(), $this->date, $this->mesure, (float) self::nombreOuNul($this->valeur),
                $this->espece, $this->stade === '' ? null : $this->stade, $this->animal);
            $this->reset('valeur', 'animal');
        });
    }

    public function supprimer(int $id, MesuresService $service): void
    {
        $mesure = MesureTerrain::query()->where('visite_id', $this->visiteId)->findOrFail($id);
        $this->authorize('delete', $mesure);
        $this->executer(fn () => $service->supprimer($mesure));
    }

    public function validerTout(MesuresService $service): void
    {
        $visite = $this->visite();
        $saisies = MesureTerrain::query()->where('visite_id', $visite->id)->where('statut', MesureTerrain::STATUT_SAISIE)->get();
        foreach ($saisies as $mesure) {
            $this->authorize('valider', $mesure);
        }

        $this->executer(fn () => $service->validerVisite($visite, $this->utilisateur()), 'Mesures terrain validées.');
    }

    public function render(MesuresService $service): View
    {
        $visite = $this->visite();

        return view('livewire.dossier.saisie.mesures', [
            'groupes' => $service->groupes($visite),
            'peutAjouter' => Gate::allows('create', [MesureTerrain::class, $visite->elevage, $visite->id]),
            'aValider' => MesureTerrain::query()->where('visite_id', $visite->id)->where('statut', MesureTerrain::STATUT_SAISIE)->exists() && $this->modifiable(),
            'mesures' => DB::table('ref.mesure_terrain')->orderBy('ordre')->get(['code', 'libelle', 'unite']),
            'especes' => DB::table('ref.espece_animale')->pluck('libelle', 'code'),
            'stades' => MesuresService::STADES,
        ]);
    }
}
