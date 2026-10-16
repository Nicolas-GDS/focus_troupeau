<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lot L4 — références et positionnement (RC-3, RC-4, EF-M3-01 à EF-M3-05 ; § 4.1 et § 5.6, décisions du 16/10/2026) :
 *
 * - ref.parametre.suivi_references : liste configurable des paramètres positionnés (EF-M3-01) ;
 * - app.parametre_defaut references.n_min = 5 (RC-3) ;
 * - valo.v_reference_population : une ligne par résultat entrant dans les références — bulletin « valide », non
 *   exclu, résultat sans flag_anomalie, élevage au consentement « references » actif ; classe lithologique du
 *   prélèvement lue dans valo.mv_prelevement_contexte (socle, à rafraîchir après import SIG) ;
 * - valo.v_reference_gds (campagne × GDS × type × paramètre), valo.v_reference_litho (idem × classe lithologique),
 *   valo.v_reference_bigds (campagne × type × paramètre, tous GDS) : n, Q1, médiane, Q3 (percentile_cont), min, max,
 *   sans filtre sur n (le seuil n_min est appliqué par le domaine, T-07) ;
 * - valo.v_historique_elevage : médiane par élevage × type × paramètre × campagne des bulletins « valide » de
 *   l'élevage, sans condition de consentement (RC-4).
 *
 * Vues simples, non matérialisées : une révocation du consentement retire les lots dès la requête suivante (T-08).
 * Les vues du socle (v_reference_locale, v_reference_par_litho) ne sont pas modifiées.
 */
return new class extends Migration
{
    private const SUIVIS = ['MS', 'MAT', 'UFL_2018', 'PDI', 'BPR', 'NDF', 'CU', 'ZN', 'MN', 'FE', 'MM', 'CA', 'P', 'MG', 'K', 'NA', 'BACA'];

    public function up(): void
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE ref.parametre ADD COLUMN suivi_references boolean NOT NULL DEFAULT false;
COMMENT ON COLUMN ref.parametre.suivi_references IS 'Paramètre positionné dans les populations de référence (EF-M3-01, liste configurable).';

CREATE VIEW valo.v_reference_population AS
SELECT r.analyse_id, p.id AS prelevement_id, p.elevage_id, e.gds_id, p.campagne_code, p.type_fourrage_code,
       ctx.classe_litho_gds, r.parametre_code, r.unite, r.valeur
FROM fourrage.resultat r
JOIN fourrage.analyse a ON a.id = r.analyse_id AND a.statut = 'valide' AND NOT a.exclu_references
JOIN fourrage.prelevement p ON p.id = a.prelevement_id
JOIN fourrage.elevage e ON e.id = p.elevage_id
LEFT JOIN valo.mv_prelevement_contexte ctx ON ctx.prelevement_id = p.id
WHERE r.valeur IS NOT NULL AND NOT r.flag_anomalie AND p.campagne_code IS NOT NULL
  AND EXISTS (SELECT 1 FROM app.consentement c
              WHERE c.elevage_id = p.elevage_id AND c.type = 'references' AND c.revoque_le IS NULL);
COMMENT ON VIEW valo.v_reference_population IS 'Résultats entrant dans les références (RC-3) : bulletin valide, non exclu, sans anomalie, élevage consentant.';

CREATE VIEW valo.v_reference_gds AS
SELECT campagne_code, gds_id, type_fourrage_code, parametre_code, unite, count(*)::int AS n,
       percentile_cont(0.25) WITHIN GROUP (ORDER BY valeur) AS q1,
       percentile_cont(0.50) WITHIN GROUP (ORDER BY valeur) AS mediane,
       percentile_cont(0.75) WITHIN GROUP (ORDER BY valeur) AS q3,
       min(valeur) AS minimum, max(valeur) AS maximum
FROM valo.v_reference_population
GROUP BY campagne_code, gds_id, type_fourrage_code, parametre_code, unite;
COMMENT ON VIEW valo.v_reference_gds IS 'Population départementale (RC-3) : campagne × GDS × type × paramètre ; n toujours donné.';

CREATE VIEW valo.v_reference_litho AS
SELECT campagne_code, gds_id, classe_litho_gds, type_fourrage_code, parametre_code, unite, count(*)::int AS n,
       percentile_cont(0.25) WITHIN GROUP (ORDER BY valeur) AS q1,
       percentile_cont(0.50) WITHIN GROUP (ORDER BY valeur) AS mediane,
       percentile_cont(0.75) WITHIN GROUP (ORDER BY valeur) AS q3,
       min(valeur) AS minimum, max(valeur) AS maximum
FROM valo.v_reference_population
WHERE classe_litho_gds IS NOT NULL
GROUP BY campagne_code, gds_id, classe_litho_gds, type_fourrage_code, parametre_code, unite;
COMMENT ON VIEW valo.v_reference_litho IS 'Population départementale restreinte à la classe lithologique du prélèvement (RC-3).';

CREATE VIEW valo.v_reference_bigds AS
SELECT campagne_code, type_fourrage_code, parametre_code, unite, count(*)::int AS n,
       percentile_cont(0.25) WITHIN GROUP (ORDER BY valeur) AS q1,
       percentile_cont(0.50) WITHIN GROUP (ORDER BY valeur) AS mediane,
       percentile_cont(0.75) WITHIN GROUP (ORDER BY valeur) AS q3,
       min(valeur) AS minimum, max(valeur) AS maximum
FROM valo.v_reference_population
GROUP BY campagne_code, type_fourrage_code, parametre_code, unite;
COMMENT ON VIEW valo.v_reference_bigds IS 'Population bi-GDS (RC-3, repli quand la départementale est insuffisante).';

CREATE VIEW valo.v_historique_elevage AS
SELECT p.elevage_id, p.type_fourrage_code, r.parametre_code, r.unite, p.campagne_code, count(*)::int AS n,
       percentile_cont(0.5) WITHIN GROUP (ORDER BY r.valeur) AS mediane
FROM fourrage.resultat r
JOIN fourrage.analyse a ON a.id = r.analyse_id AND a.statut = 'valide'
JOIN fourrage.prelevement p ON p.id = a.prelevement_id
WHERE r.valeur IS NOT NULL AND NOT r.flag_anomalie AND p.campagne_code IS NOT NULL
GROUP BY p.elevage_id, p.type_fourrage_code, r.parametre_code, r.unite, p.campagne_code;
COMMENT ON VIEW valo.v_historique_elevage IS 'Série pluriannuelle d''un élevage (RC-4) : médiane des lots valides par type, paramètre et campagne.';
SQL);

        DB::table('ref.parametre')->whereIn('code', self::SUIVIS)->update(['suivi_references' => true]);

        DB::table('app.parametre_defaut')->insert([
            'gds_id' => null,
            'cle' => 'references.n_min',
            'valeur' => 5,
            'unite' => 'lots',
            'libelle' => 'Effectif minimal d\'une population de référence (quartiles affichés à partir de ce nombre de lots)',
            'source' => 'Spécifications § 4.1, RC-3 ; seuil de diffusion du document 02 (anonymat et robustesse)',
            'statut_epistemique' => 'hypothese_locale',
            'valide_du' => '2026-10-16',
        ]);
    }

    public function down(): void
    {
        DB::table('app.parametre_defaut')->where('cle', 'references.n_min')->delete();
        DB::unprepared(<<<'SQL'
DROP VIEW IF EXISTS valo.v_historique_elevage;
DROP VIEW IF EXISTS valo.v_reference_bigds;
DROP VIEW IF EXISTS valo.v_reference_litho;
DROP VIEW IF EXISTS valo.v_reference_gds;
DROP VIEW IF EXISTS valo.v_reference_population;
ALTER TABLE ref.parametre DROP COLUMN IF EXISTS suivi_references;
SQL);
    }
};
