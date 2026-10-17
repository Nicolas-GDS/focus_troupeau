<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lot L5, étape 4 — besoins du socle et contrôles de la ration (RC-9 ; décision du 17/10/2026, transcription
 * explicite de ref.besoin_animal, le socle restant intact) :
 *
 * - ref.besoin_journalier.maximum (ajout) : maximum réglementaire de la concentration (cuivre des ovins, 15 mg/kg MS,
 *   EFSA 2016) ;
 * - transcription ligne par ligne de ref.besoin_animal : une valeur minérale « par kg MS de ration » devient un minimum
 *   par kg MS ; « (max 15) » un maximum ; le stade « debut_lactation » est rattaché à « lactation » ; une ligne sans stade
 *   vaut pour tous les stades (« tous ») ; la référence bibliographique est reprise, y compris sur les repères de RC-9
 *   chargés à l'étape 1 (mêmes valeurs) ; statut « secondaire » du socle → publie_source_secondaire ; toutes les lignes
 *   restent « à valider » (point ouvert 6). Le maximum de BACA de la vache tarie (ligne 14) est un contrôle de ration
 *   (RC-9) : il est repris comme paramètre ci-dessous, avec sa source ;
 * - app.parametre_defaut controle.* : seuils des contrôles de la ration de RC-9 (Ca/P, K/Mg, BACA, PDI/UFL).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE ref.besoin_journalier ADD COLUMN maximum numeric');
        $socle = DB::table('ref.besoin_animal')->get()->keyBy('id');
        $statut = fn (object $l) => $l->statut_epistemique === 'secondaire' ? 'publie_source_secondaire' : $l->statut_epistemique;

        // Repères de RC-9 déjà chargés : mêmes valeurs que le socle, dont on reprend la référence.
        foreach ([[3, 'VL', 'UFL_2018', 'animal'], [1, 'VL', 'UFL_2018', 'par_kg_lait'], [2, 'VL', 'PDI', 'par_kg_lait'],
            [15, 'BREBIS_ENT', 'UFL_2018', 'animal'], [16, 'BREBIS_ENT', 'PDI', 'animal'], [21, 'CHEVRE', 'UFL_2018', 'animal']] as [$id, $categorie, $parametre, $base]) {
            DB::table('ref.besoin_journalier')->where(['categorie_code' => $categorie, 'parametre_code' => $parametre, 'base' => $base])
                ->update(['reference_id' => $socle[$id]->reference_id]);
        }

        // [id du socle, catégorie, stade retenu, paramètre, mode, base, min, maximum, valeur]
        $lignes = [
            [17, 'BREBIS_ALL', 'fin_gestation', 'UFL_2018', 'par_jour', 'animal', null, null, 1.24],
            [18, 'BREBIS_ALL', 'lactation', 'UFL_2018', 'par_jour', 'animal', null, null, 2.41],
            [19, 'BREBIS_ALL', 'tous', 'CU', 'par_kg_ms', 'animal', 8, 15, null],
            [21, 'CHEVRE', 'lactation', 'UFL_2018', 'par_jour', 'animal', null, null, 0.88],
            [20, 'CHEVRE', 'lactation', 'UFL_2018', 'par_jour', 'par_kg_lait', null, null, 0.389],
            [22, 'CHEVRE', 'lactation', 'CU', 'par_kg_ms', 'animal', 20, null, null],
            [10, 'VL', 'lactation', 'CA', 'par_kg_ms', 'animal', 6.4, null, null],
            [11, 'VL', 'lactation', 'P', 'par_kg_ms', 'animal', 3.9, null, null],
            [12, 'VL', 'lactation', 'MG', 'par_kg_ms', 'animal', 1.8, null, null],
            [13, 'VL', 'lactation', 'NA', 'par_kg_ms', 'animal', 2.2, null, null],
            [4, 'VL', 'lactation', 'CU', 'par_kg_ms', 'animal', 10, null, null],
            [5, 'VL', 'lactation', 'ZN', 'par_kg_ms', 'animal', 50, null, null],
            [6, 'VL', 'lactation', 'MN', 'par_kg_ms', 'animal', 50, null, null],
            [7, 'VL', 'lactation', 'SE', 'par_kg_ms', 'animal', 0.2, null, null],
            [8, 'VL', 'lactation', 'I', 'par_kg_ms', 'animal', 0.6, null, null],
            [9, 'VL', 'lactation', 'CO', 'par_kg_ms', 'animal', 0.3, null, null],
        ];

        foreach ($lignes as [$id, $categorie, $stade, $parametre, $mode, $base, $min, $maximum, $valeur]) {
            $source = $socle[$id];
            DB::table('ref.besoin_journalier')->insert([
                'categorie_code' => $categorie, 'stade' => $stade, 'poids_vif_kg' => $source->poids_vif_kg, 'parametre_code' => $parametre,
                'mode' => $mode, 'base' => $base, 'valeur_min' => $min, 'maximum' => $maximum, 'valeur' => $valeur,
                'reference_id' => $source->reference_id, 'statut_epistemique' => $statut($source),
                'commentaire' => 'Transcrit de ref.besoin_animal n° '.$id.' (« '.$source->unite.' », stade du socle : '.($source->stade ?? 'aucun').')',
                'valide' => false, 'version_creation' => '5',
            ]);
        }

        $baca = $socle[14];
        foreach ([
            ['controle.ca_p_min', 1.5, 'Rapport Ca/P : bas de la cible', 'RC-9 (cible 1,5–2,5) ; mâles ovins et caprins : NRC 2007 [V]', 'publie', null],
            ['controle.ca_p_max', 2.5, 'Rapport Ca/P : haut de la cible', 'RC-9 (cible 1,5–2,5)', 'consensus_professionnel', null],
            ['controle.ca_p_max_prepa_velage', 3.0, 'Rapport Ca/P maximal en préparation au vêlage (vigilance)', 'RC-9 [C]', 'consensus_professionnel', null],
            ['controle.k_mg_max', 15.0, 'Rapport K/Mg maximal pour les vaches à vêler (vigilance)', 'RC-9 [C]', 'consensus_professionnel', null],
            ['controle.baca_max_fin_gestation', (float) $baca->valeur, 'BACA maximale de la ration en fin de gestation (mEq/kg MS)', 'RC-9 ; Meschy 2007 [V] ; ref.besoin_animal n° 14', 'publie', $baca->reference_id],
            ['controle.pdi_ufl_min', 90.0, 'Rapport PDI/UFL : bas de l\'intervalle (information)', 'RC-9 [C]', 'consensus_professionnel', null],
            ['controle.pdi_ufl_max', 110.0, 'Rapport PDI/UFL : haut de l\'intervalle (information)', 'RC-9 [C]', 'consensus_professionnel', null],
        ] as [$cle, $valeur, $libelle, $sourceTexte, $statutControle]) {
            DB::table('app.parametre_defaut')->insert([
                'gds_id' => null, 'cle' => $cle, 'valeur' => $valeur, 'unite' => str_starts_with($cle, 'controle.baca') ? 'mEq/kg MS' : 'rapport',
                'libelle' => $libelle, 'source' => 'Spécifications § 4.3, '.$sourceTexte, 'statut_epistemique' => $statutControle, 'valide_du' => '2026-10-17',
            ]);
        }
    }

    public function down(): void
    {
        DB::table('app.parametre_defaut')->where('cle', 'like', 'controle.%')->delete();
        DB::table('ref.besoin_journalier')->where('commentaire', 'like', 'Transcrit de ref.besoin_animal%')->delete();
        DB::table('ref.besoin_journalier')->update(['reference_id' => null]);
        DB::statement('ALTER TABLE ref.besoin_journalier DROP COLUMN IF EXISTS maximum');
    }
};
