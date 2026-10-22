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
     * Entrées de la barre de navigation : liens directs et menus déroulants des liens portant un « groupe » (élevage
     * courant pour les agents, imports, administration), pour que la barre tienne sur l'écran quel que soit le cumul des rôles.
     *
     * @return list<array{libelle: string, route?: string, parametres?: array<string, string>, actif?: string, liens?: list<array{libelle: string, route: string, parametres?: array<string, string>, actif?: string}>}>
     */
    private function navigation(User $user, ?Elevage $courant): array
    {
        // Chaque menu prend la place de son premier lien, ses liens y sont rangés dans l'ordre.
        $entrees = [];
        $positions = [];
        foreach ($this->liens($user, $courant) as $lien) {
            if (! isset($lien['groupe'])) {
                $entrees[] = $lien;

                continue;
            }
            $groupe = $lien['groupe'];
            unset($lien['groupe']);
            if (! isset($positions[$groupe])) {
                $positions[$groupe] = count($entrees);
                $entrees[] = ['libelle' => $groupe, 'liens' => []];
            }
            $entrees[$positions[$groupe]]['liens'][] = $lien;
        }

        return array_map(fn (array $entree) => isset($entree['liens']) && count($entree['liens']) === 1 ? $entree['liens'][0] : $entree, $entrees);
    }

    /**
     * Liens de navigation ; « parametres » : paramètres de route éventuels (E24 : table éditée, volets du dossier :
     * élevage courant) ; « actif » : motif des routes où le lien est marqué comme page courante ; « groupe » : menu
     * déroulant qui le range.
     *
     * @return list<array{libelle: string, route: string, parametres?: array<string, string>, actif?: string, groupe?: string}>
     */
    private function liens(User $user, ?Elevage $courant): array
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
        // L'éleveur les garde en liens directs ; un agent, dont la barre est plus chargée, les trouve dans le menu « Élevage ».
        if ($dossier !== null && $user->hasAnyRole(['eleveur', 'technicien'])) {
            $groupe = $user->hasRole('eleveur') ? [] : ['groupe' => 'Élevage'];
            $liens[] = ['libelle' => 'Stocks', 'route' => 'stocks', 'parametres' => $dossier, 'actif' => 'stocks*', ...$groupe];
            $liens[] = ['libelle' => 'Troupeau', 'route' => 'troupeau', 'parametres' => $dossier, ...$groupe];
            $liens[] = ['libelle' => 'Autonomie', 'route' => 'autonomie', 'parametres' => $dossier, ...$groupe];
            $liens[] = ['libelle' => 'Approvisionnement', 'route' => 'approvisionnement', 'parametres' => $dossier, 'actif' => 'approvisionnement|scenarios.*', ...$groupe];
            $liens[] = ['libelle' => 'Paramètres', 'route' => 'parametres', 'parametres' => $dossier, ...$groupe];
        }

        // E23 — élevages (EF-M0-02)
        if ($user->can('elevage.ecrire')) {
            $liens[] = ['libelle' => 'Élevages', 'route' => 'elevages', 'actif' => 'elevages|elevages.fiche'];
        }

        if ($user->can('bulletin.importer')) {
            $liens[] = ['libelle' => 'Import des bulletins', 'route' => 'import.bulletins', 'groupe' => 'Imports'];
        }

        if ($user->can('pilotage.lire')) {
            $liens[] = ['libelle' => 'Pilotage', 'route' => 'pilotage', 'actif' => 'pilotage*'];
        }

        if ($user->can('sagema.importer')) {
            $liens[] = ['libelle' => 'Import SAGEMA', 'route' => 'import.sagema', 'groupe' => 'Imports'];
        }

        if ($user->hasAnyRole(['admin', Perimetre::ROLE_GLOBAL])) {
            $liens[] = ['libelle' => 'Utilisateurs', 'route' => 'admin.utilisateurs', 'groupe' => 'Administration'];
            $liens[] = ['libelle' => 'Conventions', 'route' => 'admin.conventions', 'groupe' => 'Administration'];
            $liens[] = ['libelle' => 'GDS', 'route' => 'admin.gds', 'groupe' => 'Administration'];
            $liens[] = ['libelle' => 'Journal', 'route' => 'admin.journal', 'groupe' => 'Administration'];
            $liens[] = ['libelle' => 'Exploitation', 'route' => 'admin.exploitation', 'groupe' => 'Administration'];
            $liens[] = ['libelle' => 'Référentiels', 'route' => 'admin.referentiels', 'parametres' => ['table' => 'seuils'], 'actif' => 'admin.referentiels', 'groupe' => 'Administration'];
        }

        return $liens;
    }
}
