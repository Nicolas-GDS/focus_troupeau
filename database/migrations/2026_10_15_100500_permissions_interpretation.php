<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions de l'interprétation des bulletins (§ 2.3, ligne « Interprétations et alertes », EF-M2-03, EF-M2-04) :
 * lecture pour tous les rôles (l'éleveur et le vétérinaire d'élevage ne lisent qu'une interprétation publiée, règle
 * de statut) ; rédaction, validation et publication des niveaux 0 à 2 pour le technicien ; visa des niveaux 3 pour
 * le vétérinaire conseil.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> */
    private const MATRICE = [
        'interpretation.lire' => ['eleveur', 'technicien', 'veterinaire_gds', 'veterinaire', 'admin', 'admin_global'],
        'interpretation.rediger' => ['technicien'],
        'interpretation.viser' => ['veterinaire_gds'],
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
