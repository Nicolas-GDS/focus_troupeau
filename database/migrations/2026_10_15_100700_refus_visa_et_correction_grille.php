<?php

use App\Domain\Referentiels\Referentiels;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Décisions de revue du lot L2 (15/10/2026) :
 * - EF-M2-04 : le vétérinaire conseil peut refuser un visa ; l'interprétation revient en brouillon avec le motif
 *   (colonnes refus_visa_*, ajout) ;
 * - version 3 des référentiels : le seuil Ca/P des mâles (catégorie BOUC_MALE, de l'espèce caprine) portait l'espèce
 *   ovine dans la grille du document 02 ; ramené à l'espèce caprine, il est identique au seuil caprin existant et
 *   est donc clôturé (rien n'est supprimé, la grille v1 reste consultable).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE fourrage.interpretation
  ADD COLUMN refus_visa_motif text,                                 -- ajout
  ADD COLUMN refus_visa_par bigint REFERENCES app."user"(id),       -- ajout
  ADD COLUMN refus_visa_le timestamptz;                             -- ajout

INSERT INTO ref.version_referentiel (numero, date_version, commentaire)
VALUES ('3', DATE '2026-10-15', 'Correction : seuil Ca/P des mâles (BOUC_MALE) rattaché à l''espèce caprine, comme sa catégorie ; identique au seuil caprin existant, il est clôturé (décision du 15/10/2026)');

UPDATE ref.seuil_interpretation SET version_fin = '3'
 WHERE parametre_code = 'CA_P' AND espece_code = 'OVI' AND categorie_code = 'BOUC_MALE' AND version_fin IS NULL;
SQL);

        app(Referentiels::class)->oublier();
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
UPDATE ref.seuil_interpretation SET version_fin = NULL
 WHERE parametre_code = 'CA_P' AND espece_code = 'OVI' AND categorie_code = 'BOUC_MALE' AND version_fin = '3';
DELETE FROM ref.version_referentiel WHERE numero = '3';
ALTER TABLE fourrage.interpretation
  DROP COLUMN IF EXISTS refus_visa_motif, DROP COLUMN IF EXISTS refus_visa_par, DROP COLUMN IF EXISTS refus_visa_le;
SQL);

        app(Referentiels::class)->oublier();
    }
};
