<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions nommées de la matrice des droits (§ 2.3) pour les objets du lot L1.
 * Les lots suivants ajoutent les permissions de leurs objets dans leurs propres migrations.
 *
 * La permission dit ce qu'un rôle peut faire sur un type d'objet ; le périmètre (GDS, élevage),
 * le statut (publie) et la convention sont vérifiés en plus par les policies (chapitre 6.3).
 *
 * Écart signalé : le § 2.3 donne au technicien « L » sur les conventions (ligne « Comptes, rôles,
 * conventions, consentements ») mais « L/E » sur le dossier élevage (« identité, convention ») ;
 * EF-M0-02 et EF-M0-06 (le technicien crée et prolonge la convention) tranchent pour L/E.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> */
    private const MATRICE = [
        'eleveur' => [
            'elevage.lire', 'convention.lire', 'consentement.lire', 'consentement.gerer_propre',
            'stock.lire', 'stock.ecrire', 'plan.lire', 'plan.ecrire',
        ],
        'technicien' => [
            'elevage.lire', 'elevage.ecrire', 'convention.lire', 'convention.ecrire', 'consentement.lire',
            'compte.lire', 'compte.inviter', 'referentiel.lire',
            'stock.lire', 'stock.ecrire', 'plan.lire', 'plan.ecrire',
        ],
        'veterinaire_gds' => [
            'elevage.lire', 'convention.lire', 'referentiel.lire', 'stock.lire', 'plan.lire',
        ],
        'veterinaire' => [
            'elevage.lire', 'convention.lire', 'stock.lire', 'plan.lire',
        ],
        'admin' => [
            'elevage.lire', 'elevage.ecrire', 'convention.lire', 'convention.ecrire',
            'consentement.lire', 'consentement.ecrire', 'compte.lire', 'compte.ecrire', 'compte.inviter',
            'referentiel.lire', 'referentiel.ecrire', 'journal.lire', 'stock.lire', 'plan.lire',
        ],
        'admin_global' => [
            'elevage.lire', 'elevage.ecrire', 'convention.lire', 'convention.ecrire',
            'consentement.lire', 'consentement.ecrire', 'compte.lire', 'compte.ecrire', 'compte.inviter',
            'referentiel.lire', 'referentiel.ecrire', 'journal.lire', 'stock.lire', 'plan.lire',
        ],
    ];

    public function up(): void
    {
        $permissions = array_values(array_unique(array_merge(...array_values(self::MATRICE))));
        sort($permissions);

        foreach ($permissions as $permission) {
            DB::table('app.permissions')->insert([
                'name' => $permission, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $idsPermissions = DB::table('app.permissions')->pluck('id', 'name');
        $idsRoles = DB::table('app.roles')->pluck('id', 'name');

        foreach (self::MATRICE as $role => $permissionsDuRole) {
            foreach ($permissionsDuRole as $permission) {
                DB::table('app.role_has_permissions')->insert([
                    'permission_id' => $idsPermissions[$permission],
                    'role_id' => $idsRoles[$role],
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissions = array_values(array_unique(array_merge(...array_values(self::MATRICE))));
        DB::table('app.permissions')->whereIn('name', $permissions)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
