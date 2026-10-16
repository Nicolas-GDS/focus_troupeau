<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions du lot L3 (§ 2.3, lignes « Dossier élevage » à « Plan d'action et contrôles ») : lecture pour tous
 * les rôles — l'éleveur et le vétérinaire d'élevage ne lisent que les objets publiés (règle de statut, policies) ;
 * saisie, validation et publication pour le technicien ; profils sanguins aussi saisis par les vétérinaires ;
 * étiquettes ajoutées par l'éleveur et validées par le technicien ; actions « faites » marquées par l'éleveur ;
 * actions vétérinaires validées par le vétérinaire conseil.
 *
 * L'interprétation de visite (E21) reprend les permissions interpretation.* du lot L2 (rédiger, viser, lire).
 */
return new class extends Migration
{
    private const TOUS = ['eleveur', 'technicien', 'veterinaire_gds', 'veterinaire', 'admin', 'admin_global'];

    /** @var array<string, list<string>> */
    private const MATRICE = [
        'visite.lire' => self::TOUS,
        'visite.rediger' => ['technicien'],
        'eau.lire' => self::TOUS,
        'eau.rediger' => ['technicien'],
        'profil_sanguin.lire' => self::TOUS,
        'profil_sanguin.rediger' => ['technicien', 'veterinaire_gds', 'veterinaire'],
        'profil_sanguin.valider' => ['technicien'],
        'mesure_terrain.lire' => self::TOUS,
        'mesure_terrain.rediger' => ['technicien'],
        'etiquette.lire' => self::TOUS,
        'etiquette.rediger' => ['eleveur', 'technicien'],
        'etiquette.valider' => ['technicien'],
        'bilan_sanitaire.lire' => self::TOUS,
        'bilan_sanitaire.rediger' => ['technicien'],
        'plan_action.lire' => self::TOUS,
        'plan_action.rediger' => ['technicien'],
        'plan_action.marquer_fait' => ['eleveur'],
        'plan_action.valider_veterinaire' => ['veterinaire_gds'],
        'note_liaison.rediger' => ['technicien'],
    ];

    public function up(): void
    {
        $roles = DB::table('app.roles')->pluck('id', 'name');

        foreach (self::MATRICE as $permission => $rolesAutorises) {
            $id = DB::table('app.permissions')->insertGetId([
                'name' => $permission, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now(),
            ]);

            foreach ($rolesAutorises as $role) {
                DB::table('app.role_has_permissions')->insert(['permission_id' => $id, 'role_id' => $roles[$role]]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('app.permissions')->whereIn('name', array_keys(self::MATRICE))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
