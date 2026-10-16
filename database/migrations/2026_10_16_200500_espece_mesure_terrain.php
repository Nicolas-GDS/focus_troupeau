<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lot L3, étape 3 — ajout : espèce des mesures terrain (dossier.mesure_terrain.espece_code). Le repère d'une mesure
 * dépend de l'espèce et du stade (RC-1.5, ref.repere_mesure_terrain) ; un élevage peut tenir plusieurs espèces.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE dossier.mesure_terrain ADD COLUMN espece_code text REFERENCES ref.espece_animale(code);
SQL);
    }

    public function down(): void
    {
        DB::unprepared('ALTER TABLE dossier.mesure_terrain DROP COLUMN IF EXISTS espece_code;');
    }
};
