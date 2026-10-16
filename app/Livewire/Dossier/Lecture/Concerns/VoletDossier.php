<?php

namespace App\Livewire\Dossier\Lecture\Concerns;

use App\Models\App\User;
use App\Models\Fourrage\Elevage;
use App\Services\Dossier\LectureDossierService;
use App\Services\ElevageCourant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use RuntimeException;

/**
 * Volet du dossier élevage (E03 à E11) : l'élevage de l'adresse est lié à travers les scopes de cloisonnement
 * (404 hors périmètre), relu à chaque requête, et devient l'élevage courant du sélecteur (EF-M0-08) pour que
 * l'en-tête et la page parlent du même élevage.
 */
trait VoletDossier
{
    #[Locked]
    public string $elevageId;

    protected function monterVolet(Elevage $elevage): void
    {
        $this->authorize('view', $elevage);
        $this->elevageId = $elevage->id;
        app(ElevageCourant::class)->choisir($this->lecteur(), $elevage);
    }

    protected function elevage(): Elevage
    {
        return Elevage::query()->findOrFail($this->elevageId);
    }

    protected function lecture(): LectureDossierService
    {
        return app(LectureDossierService::class);
    }

    /**
     * Vue du volet dans la mise en page de l'espace, avec l'en-tête du dossier et les mentions de limites.
     *
     * @param  array<string, mixed>  $donnees
     */
    protected function volet(string $vue, string $titre, array $donnees): View
    {
        $elevage = app(ElevageCourant::class)->accessibles()->firstWhere('id', $this->elevageId) ?? $this->elevage();

        return view('livewire.dossier.lecture.'.$vue, [
            ...$donnees,
            'elevage' => $elevage,
            'libelleElevage' => ElevageCourant::libelle($elevage),
            'agent' => $this->lecture()->agent($this->lecteur()),
            'limites' => $this->lecture()->limites(),
            'especes' => DB::table('ref.espece_animale')->pluck('libelle', 'code'),
        ])->layout('components.layouts.espace', ['titre' => $titre]);
    }

    protected function lecteur(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            throw new RuntimeException('Utilisateur non connecté.');
        }

        return $user;
    }
}
