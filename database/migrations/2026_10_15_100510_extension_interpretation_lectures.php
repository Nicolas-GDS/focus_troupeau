<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lot L2, E20 — extension de fourrage.interpretation pour les trois lectures d'EF-M2-03 et la trace du niveau proposé.
 *
 * Ajouts signalés « ajout » (absents du § 5.3) :
 * - lecture_mineraux : EF-M2-03 impose trois lectures dans l'ordre « propreté et conservation ; minéraux ; valeur
 *   nutritionnelle » ; le socle n'en porte que deux (lecture_sanitaire, reprise pour « propreté et conservation »,
 *   et lecture_nutritionnelle) ;
 * - motifs_rehaussement : motifs des rehaussements du niveau proposé (RC-1.4), conservés avec le niveau proposé
 *   pour que la proposition reste lisible après coup (proposition, à valider).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE fourrage.interpretation
  ADD COLUMN lecture_mineraux text,          -- ajout (EF-M2-03 : lecture « minéraux »)
  ADD COLUMN motifs_rehaussement text[];    -- ajout (RC-1.4 : motifs du niveau proposé)
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE fourrage.interpretation
  DROP COLUMN IF EXISTS lecture_mineraux,
  DROP COLUMN IF EXISTS motifs_rehaussement;
SQL);
    }
};
