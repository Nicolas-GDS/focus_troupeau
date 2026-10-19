<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Lot L7 — administration et pilotage (§ 2.3, § 3.13 « Précisions du lot L7 ») : permissions nouvelles (résiliation
 * de convention et anonymisation par l'administrateur, édition des GDS, pilotage, import SAGEMA, export des données de
 * l'élevage) et textes types du rappel d'échéance de convention (EF-M0-06).
 */
return new class extends Migration
{
    /** @var array<string, list<string>> */
    private const MATRICE = [
        'convention.resilier' => ['admin', 'admin_global'],
        'gds.ecrire' => ['admin', 'admin_global'],
        'elevage.anonymiser' => ['admin', 'admin_global'],
        'pilotage.lire' => ['technicien', 'admin', 'admin_global'],
        'sagema.importer' => ['technicien'],
        'donnees.exporter' => ['eleveur', 'technicien', 'admin', 'admin_global'],
    ];

    public function up(): void
    {
        $roles = DB::table('app.roles')->pluck('id', 'name');
        foreach (self::MATRICE as $permission => $rolesAutorises) {
            $id = DB::table('app.permissions')->insertGetId(['name' => $permission, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
            foreach ($rolesAutorises as $role) {
                DB::table('app.role_has_permissions')->insert(['permission_id' => $id, 'role_id' => $roles[$role]]);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::unprepared(<<<'SQL'
INSERT INTO ref.texte_type (code, texte, source, version_creation) VALUES
 ('notification.rappel_convention.objet', 'Convention de {elevage} : échéance le {date}', 'EF-M0-06', '5'),
 ('notification.rappel_convention.ligne1', 'La convention d''approche globale de {elevage} arrive à échéance le {date}.', 'EF-M0-06', '5'),
 ('notification.rappel_convention.ligne2', 'À l''échéance, l''accès de l''éleveur passe en lecture seule (stocks et scénarios fermés).', 'EF-M0-06', '5'),
 ('notification.rappel_convention.ligne3', 'Prolongez la convention depuis la fiche de l''élevage si le suivi continue.', 'EF-M0-06', '5');
SQL);
    }

    public function down(): void
    {
        DB::table('ref.texte_type')->where('code', 'like', 'notification.rappel_convention.%')->delete();
        DB::table('app.permissions')->whereIn('name', array_keys(self::MATRICE))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
