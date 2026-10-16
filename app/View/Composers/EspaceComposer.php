<?php

namespace App\View\Composers;

use App\Models\App\Gds;
use App\Models\App\User;
use App\Services\Cloisonnement\Perimetre;
use App\Services\ElevageCourant;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Données communes de l'espace connecté : élevage courant et sélecteur (EF-M0-08), GDS de l'en-tête
 * (co-branding, § 8.1), navigation selon le rôle (annexe 10).
 */
class EspaceComposer
{
    public function __construct(
        private readonly Request $request,
        private readonly ElevageCourant $elevageCourant,
    ) {}

    public function compose(View $view): void
    {
        $user = $this->request->user();

        if (! $user instanceof User) {
            return;
        }

        // Calculé une fois par requête (mise en page et page partagent les mêmes données),
        // mémorisé sur la requête elle-même pour ne jamais fuiter d'une requête à l'autre.
        if (! $this->request->attributes->has('espace')) {
            $elevages = $this->elevageCourant->accessibles();
            $courant = $this->elevageCourant->courant($elevages);
            $gdsId = $this->elevageCourant->gdsAffiche($user, $courant);

            $this->request->attributes->set('espace', [
                'utilisateur' => $user,
                'elevages' => $elevages,
                'elevageCourant' => $courant,
                'gds' => $gdsId === null ? null : Gds::query()->find($gdsId),
                'navigation' => $this->navigation($user),
            ]);
        }

        $view->with($this->request->attributes->get('espace'));
    }

    /**
     * Liens de navigation ; « parametres » : paramètres de route éventuels (E24 : table éditée).
     *
     * @return list<array{libelle: string, route: string, parametres?: array<string, string>}>
     */
    private function navigation(User $user): array
    {
        $liens = [];

        if ($user->hasAnyRole(['eleveur', 'veterinaire'])) {
            $liens[] = ['libelle' => 'Accueil', 'route' => 'accueil'];
        }

        if ($user->hasAnyRole(['technicien', 'veterinaire_gds'])) {
            $liens[] = ['libelle' => 'Mon travail', 'route' => 'travail'];
        }

        // Lot L3 — visites (E03, saisie) ; l'espace éleveur du dossier vient à l'étape 7 du lot
        if ($user->can('visite.rediger')) {
            $liens[] = ['libelle' => 'Visites', 'route' => 'visites'];
        }

        if ($user->can('bulletin.importer')) {
            $liens[] = ['libelle' => 'Import des bulletins', 'route' => 'import.bulletins'];
        }

        if ($user->hasAnyRole(['admin', Perimetre::ROLE_GLOBAL])) {
            $liens[] = ['libelle' => 'Utilisateurs', 'route' => 'admin.utilisateurs'];
            $liens[] = ['libelle' => 'Conventions', 'route' => 'admin.conventions'];
            $liens[] = ['libelle' => 'GDS', 'route' => 'admin.gds'];
            $liens[] = ['libelle' => 'Référentiels', 'route' => 'admin.referentiels', 'parametres' => ['table' => 'seuils']];
        }

        return $liens;
    }
}
