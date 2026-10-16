<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lot L4, étape 3 — valo.v_historique_elevage passe d'une médiane par campagne à une ligne par lot, avec l'indicateur
 * « publie » (une interprétation publiée existe) : l'éleveur et le vétérinaire d'élevage ne lisent que leurs lots
 * publiés (§ 6.3), les agents tous les lots « valide ». La médiane par campagne (RC-4) est calculée par le domaine
 * (Domain\Interpretation\Historique) sur les lots que le lecteur peut voir.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
DROP VIEW valo.v_historique_elevage;
CREATE VIEW valo.v_historique_elevage AS
SELECT p.elevage_id, p.type_fourrage_code, r.parametre_code, r.unite, p.campagne_code, a.id AS analyse_id, r.valeur,
       EXISTS (SELECT 1 FROM fourrage.interpretation i WHERE i.analyse_id = a.id AND i.statut = 'publie') AS publie
FROM fourrage.resultat r
JOIN fourrage.analyse a ON a.id = r.analyse_id AND a.statut = 'valide'
JOIN fourrage.prelevement p ON p.id = a.prelevement_id
WHERE r.valeur IS NOT NULL AND NOT r.flag_anomalie AND p.campagne_code IS NOT NULL;
COMMENT ON VIEW valo.v_historique_elevage IS 'Lots valides d''un élevage par type, paramètre et campagne (RC-4), avec l''indicateur de publication ; médiane calculée par le domaine.';
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP VIEW valo.v_historique_elevage;
CREATE VIEW valo.v_historique_elevage AS
SELECT p.elevage_id, p.type_fourrage_code, r.parametre_code, r.unite, p.campagne_code, count(*)::int AS n,
       percentile_cont(0.5) WITHIN GROUP (ORDER BY r.valeur) AS mediane
FROM fourrage.resultat r
JOIN fourrage.analyse a ON a.id = r.analyse_id AND a.statut = 'valide'
JOIN fourrage.prelevement p ON p.id = a.prelevement_id
WHERE r.valeur IS NOT NULL AND NOT r.flag_anomalie AND p.campagne_code IS NOT NULL
GROUP BY p.elevage_id, p.type_fourrage_code, r.parametre_code, r.unite, p.campagne_code;
SQL);
    }
};
