<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lot L6 — mention de limites des PDF du bilan d'autonomie et d'un scénario (EF-M6-06, EF-GEN-08).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
INSERT INTO ref.texte_type (code, texte, source, version_creation) VALUES
 ('autonomie.limites', 'Projection indicative : elle dépend des stocks, des rations et des valeurs par défaut saisis, avec l''incertitude affichée. Elle ne remplace ni un calcul de ration ni l''avis du technicien.', 'EF-M6-06 ; EF-GEN-08', '5');
SQL);
    }

    public function down(): void
    {
        DB::table('ref.texte_type')->where('code', 'autonomie.limites')->delete();
    }
};
