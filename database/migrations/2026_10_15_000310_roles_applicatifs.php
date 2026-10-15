<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Rôles applicatifs du § 2.2 (spatie/laravel-permission). Ils font partie du modèle, pas des données :
 * ils sont donc créés par migration. Les permissions nommées sont ajoutées avec les policies (étape 5).
 */
return new class extends Migration
{
    private const ROLES = ['eleveur', 'technicien', 'veterinaire_gds', 'veterinaire', 'admin', 'admin_global'];

    public function up(): void
    {
        foreach (self::ROLES as $role) {
            DB::table('app.roles')->insert([
                'name' => $role,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('app.roles')->whereIn('name', self::ROLES)->delete();
    }
};
