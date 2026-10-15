<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * EF-M0-06 : le technicien est prévenu 30 jours avant l'échéance d'une convention (files de travail E18 au lot L2,
 * notification au lot L3).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('app.parametre_defaut')->insert([
            'gds_id' => null,
            'cle' => 'convention.rappel_jours',
            'valeur' => 30,
            'unite' => 'jours',
            'libelle' => 'Délai de rappel avant l\'échéance d\'une convention',
            'source' => 'Spécifications § 3.3, EF-M0-06',
            'statut_epistemique' => 'hypothese_locale',
            'valide_du' => '2026-10-15',
        ]);
    }

    public function down(): void
    {
        DB::table('app.parametre_defaut')->where('cle', 'convention.rappel_jours')->delete();
    }
};
