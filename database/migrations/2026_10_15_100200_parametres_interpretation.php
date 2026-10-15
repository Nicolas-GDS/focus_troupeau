<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Paramètres par défaut du lot L2 (app.parametre_defaut, D10) : valeurs des spécifications, avec leur source.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('app.parametre_defaut')->insert([
            'gds_id' => null,
            'cle' => 'interpretation.fenetre_evenements_mois',
            'valeur' => 12,
            'unite' => 'mois',
            'libelle' => 'Fenêtre de recherche des événements sanitaires compatibles avec une alerte (rehaussement du niveau proposé)',
            'source' => 'Spécifications § 4.1, RC-1.4',
            'statut_epistemique' => 'hypothese_locale',
            'valide_du' => '2026-10-15',
        ]);
    }

    public function down(): void
    {
        DB::table('app.parametre_defaut')->where('cle', 'interpretation.fenetre_evenements_mois')->delete();
    }
};
