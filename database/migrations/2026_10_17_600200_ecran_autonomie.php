<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lot L6 — écran du bilan (E14) et alertes : repère d'action sur la couverture (EF-M6-03, document 05, § 7.1), repères
 * de durée de la période de référence (RC-10, affichés sans trancher), textes types des notifications d'autonomie
 * (EF-M8-03) et du scénario retenu (technicien prévenu, décisions du 17/10/2026).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('app.parametre_defaut')->insert([
            'gds_id' => null, 'cle' => 'autonomie.repere_couverture', 'valeur' => 0.80, 'unite' => 'fraction',
            'libelle' => 'Repère d\'action : au-dessus, l\'ajustement de ration suffit généralement ; en dessous, l\'achat extérieur devient nécessaire',
            'source' => 'EF-M6-03 ; document 05, § 7.1 [C]', 'statut_epistemique' => 'consensus_professionnel', 'valide_du' => '2026-10-17',
        ]);

        DB::unprepared(<<<'SQL'
INSERT INTO ref.texte_type (code, texte, source, version_creation) VALUES
 ('autonomie.repere_action.au_dessus', 'Couverture au-dessus du repère : un ajustement de la ration suffit généralement.', 'EF-M6-03 ; document 05, § 7.1', '5'),
 ('autonomie.repere_action.en_dessous', 'Couverture en dessous du repère : un achat extérieur devient nécessaire.', 'EF-M6-03 ; document 05, § 7.1', '5'),
 ('autonomie.periode.reperes', 'Repères de durée (FRGDS AURA / Idele 2019) : plaine 160 jours au moins, montagne 200 ; transition de mise à l''herbe : 30 jours à demi-ration ; complémentation estivale : 30 jours en année favorable, 90 à 100 en année sèche. Le choix revient à l''éleveur.', 'RC-10 ; document 05, § 6.4 [V]', '5'),
 ('notification.autonomie.objet', 'Autonomie fourragère : rupture prévue le {date}', 'EF-M8-03', '5'),
 ('notification.autonomie.ligne1', 'Pour {elevage}, le bilan de l''inventaire en cours prévoit une rupture de stock le {date}, soit dans {jours} jours.', 'EF-M8-03', '5'),
 ('notification.autonomie.ligne2', 'Fourchette selon l''incertitude des stocks : du {date_min} au {date_max}. Marge de sécurité entamée à partir du {date_securite}.', 'EF-M8-03', '5'),
 ('notification.autonomie.ligne3', 'Consultez le bilan et les leviers d''approvisionnement dans votre espace.', 'EF-M8-03', '5'),
 ('notification.scenario_retenu.objet', 'Scénario d''approvisionnement retenu pour {elevage}', 'Décisions du 17/10/2026', '5'),
 ('notification.scenario_retenu.ligne1', 'Le scénario « {scenario} » a été retenu par {auteur} le {date}.', 'Décisions du 17/10/2026', '5'),
 ('notification.scenario_retenu.ligne2', 'Les achats prévus sont ajoutés à l''inventaire en cours ; le bilan est recalculé.', 'Décisions du 17/10/2026', '5'),
 ('notification.scenario_retenu.ligne3', 'Consultez le scénario dans l''espace de l''élevage.', 'Décisions du 17/10/2026', '5');
SQL);
    }

    public function down(): void
    {
        DB::table('app.parametre_defaut')->where('cle', 'autonomie.repere_couverture')->delete();
        DB::unprepared("DELETE FROM ref.texte_type WHERE code LIKE 'autonomie.%' OR code LIKE 'notification.autonomie.%' OR code LIKE 'notification.scenario_retenu.%';");
    }
};
