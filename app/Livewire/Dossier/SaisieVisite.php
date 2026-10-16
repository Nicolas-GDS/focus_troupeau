<?php

namespace App\Livewire\Dossier;

use App\Models\Dossier\Visite;
use App\Services\ElevageCourant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * E03, côté technicien — saisie d'une visite dans l'ordre des étapes de la démarche (EF-M1-09) : collecter
 * (compte rendu, bilan sanitaire, événements), analyser (prélèvements, eau, étiquettes), mesurer (mesures terrain,
 * profils sanguins). Les étapes « croiser », « agir » et « suivre » relèvent de l'interprétation de visite (E21).
 * Chaque volet est un composant qui autorise ses propres actions.
 */
class SaisieVisite extends Component
{
    #[Locked]
    public int $visiteId;

    public function mount(Visite $visite): void
    {
        $this->authorize('view', $visite);
        $this->visiteId = $visite->id;
    }

    public function render(): View
    {
        $visite = Visite::query()->with('elevage')->findOrFail($this->visiteId);

        return view('livewire.dossier.saisie-visite', [
            'visite' => $visite,
            'libelleElevage' => ElevageCourant::libelle($visite->elevage),
            'modifiable' => Gate::allows('update', $visite),
            'motif' => Visites::motifs()[$visite->motif] ?? $visite->motif,
        ])->layout('components.layouts.espace', ['titre' => 'Visite du '.$visite->date_visite->format('d/m/Y')]);
    }
}
