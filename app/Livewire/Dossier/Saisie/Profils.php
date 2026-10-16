<?php

namespace App\Livewire\Dossier\Saisie;

use App\Livewire\Dossier\Concerns\SaisieDeVisite;
use App\Models\Fourrage\AnalyseAnimale;
use App\Services\Dossier\ProfilsService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Étape « mesurer » — profils sanguins (EF-M1-05, EF-M9-02, RC-1.5) : saisie d'un résultat de laboratoire par
 * élément (moyenne, mini, maxi du lot), lecture par rapport au repère, validation par le technicien.
 */
class Profils extends Component
{
    use SaisieDeVisite {
        mount as monterVisite;
    }

    public string $date = '';

    public string $espece = 'BOV';

    public string $matrice = 'serum';

    public string $element = '';

    public ?string $moyenne = null;

    public ?string $minimum = null;

    public ?string $maximum = null;

    public string $unite = 'mg/l';

    public ?string $nbAnimaux = null;

    public ?string $laboratoire = null;

    public string $commentaire = '';

    public function mount(int $visiteId): void
    {
        $this->monterVisite($visiteId);
        $visite = $this->visite();
        $this->date = $visite->date_visite->toDateString();
        $this->espece = $visite->elevage->listeEspeces()[0] ?? 'BOV';
    }

    public function ajouter(ProfilsService $service): void
    {
        $visite = $this->visite();
        $this->authorize('create', [AnalyseAnimale::class, $visite->elevage, $visite->id]);
        $this->validate([
            'date' => ['required', 'date'],
            'espece' => ['required', Rule::in(self::codes('ref.espece_animale'))],
            'matrice' => ['required', Rule::in(AnalyseAnimale::MATRICES)],
            'element' => ['required', 'string', 'max:60'],
            'moyenne' => ['required', 'numeric', 'min:0'],
            'minimum' => ['nullable', 'numeric', 'min:0'],
            'maximum' => ['nullable', 'numeric', 'min:0'],
            'unite' => ['required', 'string', 'max:20'],
            'nbAnimaux' => ['nullable', 'integer', 'min:1'],
            'laboratoire' => ['nullable', 'integer', Rule::in(self::codes('ref.laboratoire', 'id'))],
            'commentaire' => ['nullable', 'string', 'max:1000'],
        ], [], ['espece' => 'espèce', 'element' => 'élément', 'moyenne' => 'valeur moyenne', 'unite' => 'unité', 'nbAnimaux' => 'nombre d\'animaux']);

        $this->executer(function () use ($service, $visite) {
            $service->ajouter($visite, $this->utilisateur(), [
                'date_prelevement' => $this->date,
                'espece_code' => $this->espece,
                'nb_animaux' => $this->nbAnimaux === null || $this->nbAnimaux === '' ? null : (int) $this->nbAnimaux,
                'matrice' => $this->matrice,
                'parametre' => trim($this->element),
                'valeur_moyenne' => (float) self::nombreOuNul($this->moyenne),
                'valeur_min' => self::nombreOuNul($this->minimum),
                'valeur_max' => self::nombreOuNul($this->maximum),
                'unite' => trim($this->unite),
                'laboratoire_id' => $this->laboratoire === null || $this->laboratoire === '' ? null : (int) $this->laboratoire,
                'commentaire' => trim($this->commentaire) === '' ? null : trim($this->commentaire),
            ]);
            $this->reset('element', 'moyenne', 'minimum', 'maximum', 'commentaire');
        }, 'Résultat enregistré.');
    }

    public function valider(int $id, ProfilsService $service): void
    {
        $profil = $this->profil($id);
        $this->authorize('valider', $profil);
        $this->executer(fn () => $service->valider($profil), 'Résultat validé.');
    }

    public function supprimer(int $id, ProfilsService $service): void
    {
        $profil = $this->profil($id);
        $this->authorize('delete', $profil);
        $this->executer(fn () => $service->supprimer($profil), 'Résultat supprimé.');
    }

    public function render(ProfilsService $service): View
    {
        $visite = $this->visite();
        $lignes = array_values(array_filter($service->lignes($visite), fn (array $l) => Gate::allows('view', $l['profil'])));

        return view('livewire.dossier.saisie.profils', [
            'lignes' => array_map(fn (array $l) => [...$l, 'peutValider' => Gate::allows('valider', $l['profil']), 'peutSupprimer' => Gate::allows('delete', $l['profil'])], $lignes),
            'peutAjouter' => Gate::allows('create', [AnalyseAnimale::class, $visite->elevage, $visite->id]),
            'elements' => $service->elementsReperes(),
            'matrices' => AnalyseAnimale::MATRICES,
            'especes' => DB::table('ref.espece_animale')->pluck('libelle', 'code'),
            'laboratoires' => DB::table('ref.laboratoire')->where('actif', true)->orderBy('code')->pluck('raison_sociale', 'id'),
        ]);
    }

    private function profil(int $id): AnalyseAnimale
    {
        return AnalyseAnimale::query()->where('visite_id', $this->visiteId)->findOrFail($id);
    }
}
