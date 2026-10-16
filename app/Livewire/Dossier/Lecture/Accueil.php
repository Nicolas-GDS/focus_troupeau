<?php

namespace App\Livewire\Dossier\Lecture;

use App\Models\App\User;
use App\Services\Dossier\LectureDossierService;
use App\Services\ElevageCourant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Livewire\Component;
use RuntimeException;

/**
 * E02 — accueil de l'éleveur et du vétérinaire d'élevage (EF-M1-01) pour l'élevage courant du sélecteur (EF-M0-08) :
 * dernière visite publiée, messages hiérarchisés, actions qui incombent au lecteur, contrôles à venir. L'état de
 * l'autonomie fourragère rejoint l'accueil au lot L6.
 */
class Accueil extends Component
{
    public function render(LectureDossierService $lecture): View
    {
        $elevage = app(ElevageCourant::class)->courant();
        $accueil = $elevage === null ? null : $lecture->accueil($elevage, $this->lecteur());
        $interpretation = $accueil['interpretation'] ?? null;

        return view('livewire.dossier.lecture.accueil', [
            'elevage' => $elevage,
            'libelleElevage' => $elevage === null ? null : ElevageCourant::libelle($elevage),
            'accueil' => $accueil,
            'lienFiche' => $interpretation === null || $interpretation->fiche_html === null ? null
                : URL::temporarySignedRoute('visites.fiche.pdf', now()->addMinutes(30), ['visite' => $interpretation->visite_id, 'version' => $interpretation->version]),
            'limites' => $lecture->limites(),
        ])->layout('components.layouts.espace', ['titre' => 'Accueil']);
    }

    private function lecteur(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            throw new RuntimeException('Utilisateur non connecté.');
        }

        return $user;
    }
}
