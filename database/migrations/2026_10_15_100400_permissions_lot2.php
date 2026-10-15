<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions du lot L2 (§ 2.3, ligne « Prélèvements et bulletins de fourrages ») : lecture pour tous les rôles
 * (l'éleveur et le vétérinaire d'élevage ne lisent que les bulletins publiés, règle de statut), import et contrôle
 * (levée d'anomalie, annulation) pour le technicien.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> */
    private const MATRICE = [
        'bulletin.lire' => ['eleveur', 'technicien', 'veterinaire_gds', 'veterinaire', 'admin', 'admin_global'],
        'bulletin.importer' => ['technicien'],
        'bulletin.controler' => ['technicien'],
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
