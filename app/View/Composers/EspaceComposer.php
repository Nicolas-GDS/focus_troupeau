<?php

namespace App\View\Composers;

use App\Models\App\Gds;
use App\Models\App\User;
use App\Models\Fourrage\Elevage;
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
                'navigation' => $this->navigation($user, $courant),
            ]);
        }

        $view->with($this->request->attributes->get('espace'));
    }

    /**
     * Liens de navigation ; « parametres » : paramètres de route éventuels (E24 : table éditée, volets du dossier :
     * élevage courant) ; « actif » : motif des routes où le lien est marqué comme page courante.
     *
     * @return list<array{libelle: string, route: string, parametres?: array<string, string>, actif?: string}>
     */
    private function navigation(User $user, ?Elevage $courant): array
    {
        $liens = [];
        $dossier = $courant === null ? null : ['elevage' => $courant->id];

        if ($user->hasAnyRole(['eleveur', 'veterinaire'])) {
            $liens[] = ['libelle' => 'Accueil', 'route' => 'accueil'];

            // E03 à E11 : l'éleveur entre par ses visites, puis navigue par volet (§ 3.4)
            if ($dossier !== null) {
                $liens[] = ['libelle' => 'Mon dossier', 'route' => 'visites', 'actif' => 'visites|dossier.*'];
                $liens[] = ['libelle' => 'Plan d\'action', 'route' => 'dossier.plan', 'parametres' => $dossier];
            }
        }

        if ($user->hasAnyRole(['technicien', 'veterinaire_gds'])) {
            $liens[] = ['libelle' => 'Mon travail', 'route' => 'travail'];
        }

        // Lot L3 — visites (saisie, E21)
        if ($user->can('visite.rediger')) {
            $liens[] = ['libelle' => 'Visites', 'route' => 'visites'];
        }

        if ($dossier !== null && $user->hasAnyRole(['technicien', 'veterinaire_gds'])) {
            $liens[] = ['libelle' => 'Dossier', 'route' => 'dossier.fourrages', 'parametres' => $dossier, 'actif' => 'dossier.*'];
        }

        // E12 à E16 — stocks fourragers, troupeau, autonomie, approvisionnement et paramètres de l'élevage (annexe 10 : éleveur, technicien)
        if ($dossier !== null && $user->hasAnyRole(['eleveur', 'technicien'])) {
            $liens[] = ['libelle' => 'Stocks', 'route' => 'stocks', 'parametres' => $dossier, 'actif' => 'stocks*'];
            $liens[] = ['libelle' => 'Troupeau', 'route' => 'troupeau', 'parametres' => $dossier];
            $liens[] = ['libelle' => 'Autonomie', 'route' => 'autonomie', 'parametres' => $dossier];
            $liens[] = ['libelle' => 'Approvisionnement', 'route' => 'approvisionnement', 'parametres' => $dossier, 'actif' => 'approvisionnement|scenarios.*'];
            $liens[] = ['libelle' => 'Paramètres', 'route' => 'parametres', 'parametres' => $dossier];
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
