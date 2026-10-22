<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Lecture des données AGDS par SAGEMA (EF-M9-03, § 6.4) : identité de l'éleveur et troupeau bovin, réservés aux agents GDS
 * (les comptes éleveur et vétérinaire d'élevage n'y ont pas accès).
 */
return new class extends Migration
{
    private const PERMISSION = 'agds.lire';

    private const ROLES = ['technicien', 'veterinaire_gds', 'admin', 'admin_global'];

    public function up(): void
    {
        $roles = DB::table('app.roles')->pluck('id', 'name');
        $id = DB::table('app.permissions')->insertGetId(['name' => self::PERMISSION, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
        foreach (self::ROLES as $role) {
            DB::table('app.role_has_permissions')->insert(['permission_id' => $id, 'role_id' => $roles[$role]]);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('app.permissions')->where('name', self::PERMISSION)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
