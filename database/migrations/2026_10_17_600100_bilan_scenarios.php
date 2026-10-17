<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lot L6 — bilan d'autonomie et scénarios (§ 5.5 ; RC-10 à RC-14 ; décisions du 17/10/2026).
 *
 * - plan.bilan (dernier bilan calculé, par inventaire et par scénario), plan.scenario, plan.levier_ligne ;
 *   ref.substituabilite (RC-13.2 : foin, enrubannage et ensilage d'herbe substituables à 1:1 en MS ; paille → foin pour
 *   les stades « entretien » et « tarissement », 30 % de la ration au plus [C]) ; clé étrangère
 *   stock.mouvement.scenario_ligne_id ;
 * - ajouts : maj_par sur plan.scenario ; stock.lot_stock.scenario_ligne_id (lot « achat » créé quand un scénario est
 *   retenu, retiré si un autre l'est) ; plan.bilan.incertitude_rel et alerte_envoyee (une seule alerte d'autonomie par
 *   franchissement, EF-M8-03) ;
 * - app.parametre_defaut : coefficients de période (RC-10), prix indicatifs datés [CP] « à valider » (point ouvert 8),
 *   taux de substitution [NV] « référence à vérifier » (point ouvert 7), décapitalisation et génisses (RC-13.3), erreurs
 *   relatives de RC-14 (et cas absents du tableau, [CP]), seuil d'alerte d'autonomie (EF-M8-03).
 */
return new class extends Migration
{
    /** @var list<array{0: string, 1: float|int, 2: string, 3: string, 4: string, 5: string}> clé, valeur, unité, libellé, source, statut */
    private const PARAMETRES = [
        ['periode.coeff_transition', 0.5, 'coefficient', 'Coefficient de ration de la transition de mise à l\'herbe', 'RC-10', 'consensus_professionnel'],
        ['periode.coeff_estivale', 0.5, 'coefficient', 'Coefficient de ration de la complémentation estivale (défaut)', 'RC-10', 'consensus_professionnel'],
        ['prix.foin_t_ms', 170, '€/t MS', 'Prix indicatif du foin — à valider', 'RC-13.3 ; calculs/ et calculateur (point ouvert 8)', 'hypothese_locale'],
        ['prix.enrubannage_t_ms', 190, '€/t MS', 'Prix indicatif de l\'enrubannage — à valider', 'RC-13.3 ; calculs/ et calculateur (point ouvert 8)', 'hypothese_locale'],
        ['prix.paille_t_ms', 75, '€/t MS', 'Prix indicatif de la paille — à valider', 'RC-13.3 ; calculs/ et calculateur (point ouvert 8)', 'hypothese_locale'],
        ['prix.concentre_t_ms', 290, '€/t MS', 'Prix indicatif d\'un concentré — à valider', 'RC-13.3 ; calculs/ et calculateur (point ouvert 8)', 'hypothese_locale'],
        ['decapitalisation.perte_eur_ugb', 900, '€/UGB', 'Perte de produit par UGB retiré — à valider', 'RC-13.3 [CP]', 'hypothese_locale'],
        ['genisse.t_ms_par_tete', 6, 't MS', 'Fourrage économisé par génisse de renouvellement supprimée', 'RC-13.3 [C]', 'consensus_professionnel'],
        ['substitution.foin', 0.44, 'taux', 'Substitution concentré / fourrage, ration à base de foin — référence à vérifier', 'RC-13.3 ; Faverdin et al. (point ouvert 7) [NV]', 'publie_source_secondaire'],
        ['substitution.ensilage_herbe', 0.53, 'taux', 'Substitution, ration à base d\'ensilage d\'herbe — référence à vérifier', 'RC-13.3 ; Faverdin et al. (point ouvert 7) [NV]', 'publie_source_secondaire'],
        ['substitution.ensilage_mais', 0.70, 'taux', 'Substitution, ration à base d\'ensilage de maïs — référence à vérifier', 'RC-13.3 ; Faverdin et al. (point ouvert 7) [NV]', 'publie_source_secondaire'],
        ['incertitude.volume_couloir', 0.05, 'relatif', 'Erreur relative du volume d\'un silo couloir', 'RC-14 ; document 05, § 2.3 [C]', 'consensus_professionnel'],
        ['incertitude.volume_taupiniere', 0.10, 'relatif', 'Erreur relative du volume d\'une taupinière', 'RC-14 [C]', 'consensus_professionnel'],
        ['incertitude.densite_equation', 0.07, 'relatif', 'Erreur relative d\'une densité par équation', 'RC-14 [M]', 'publie'],
        ['incertitude.densite_tamisage', 0.07, 'relatif', 'Erreur relative d\'une densité par tamisage', 'RC-14 [M]', 'publie'],
        ['incertitude.densite_carottage', 0.04, 'relatif', 'Erreur relative d\'une densité par carottage', 'RC-14 [M]', 'publie'],
        ['incertitude.densite_saisie', 0.15, 'relatif', 'Erreur relative d\'une densité saisie sans méthode', 'Décision du 17/10/2026 [CP]', 'hypothese_locale'],
        ['incertitude.poids_estime', 0.15, 'relatif', 'Erreur relative d\'un poids de balle estimé à vue', 'RC-14 [M] (11–20 %)', 'publie'],
        ['incertitude.poids_mesure', 0.05, 'relatif', 'Erreur relative d\'un poids de balle pesé', 'RC-14 [M]', 'publie'],
        ['incertitude.vrac_volume', 0.10, 'relatif', 'Erreur relative du volume d\'un vrac', 'Décision du 17/10/2026 [CP]', 'hypothese_locale'],
        ['incertitude.vrac_densite', 0.15, 'relatif', 'Erreur relative de la densité d\'un vrac', 'Décision du 17/10/2026 [CP]', 'hypothese_locale'],
        ['incertitude.ms_analyse_points', 2, 'points de MS', 'Erreur sur une MS analysée', 'RC-14 [C]', 'consensus_professionnel'],
        ['incertitude.ms_defaut_points', 5, 'points de MS', 'Erreur sur une MS par défaut (foin, paille)', 'RC-14 [C]', 'consensus_professionnel'],
        ['incertitude.ms_saisie_points', 5, 'points de MS', 'Erreur sur une MS saisie non analysée', 'Décision du 17/10/2026 [CP]', 'hypothese_locale'],
        ['incertitude.ms_ensilage_points', 15, 'points de MS', 'Erreur sur la MS non analysée d\'un enrubannage ou d\'un ensilage', 'RC-14 [C]', 'consensus_professionnel'],
        ['incertitude.pertes_relatif', 0.50, 'relatif', 'Erreur relative sur les pertes retenues', 'RC-14 [C]', 'consensus_professionnel'],
        ['alerte.autonomie_jours', 60, 'jours', 'Alerte quand la rupture prévisionnelle approche à moins de ce nombre de jours', 'EF-M8-03', 'hypothese_locale'],
    ];

    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE plan.scenario (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  inventaire_id bigint NOT NULL REFERENCES stock.inventaire(id) ON DELETE CASCADE,
  libelle text NOT NULL, origine text NOT NULL CHECK (origine IN ('proposition','utilisateur')),
  statut text NOT NULL DEFAULT 'brouillon' CHECK (statut IN ('brouillon','retenu','archive')),
  retenu_le timestamptz,
  cree_par bigint REFERENCES app."user"(id), maj_par bigint REFERENCES app."user"(id),   -- maj_par : ajout
  cree_le timestamptz NOT NULL DEFAULT now(), maj_le timestamptz NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX ON plan.scenario (inventaire_id) WHERE statut = 'retenu';
CREATE TRIGGER trg_scenario_maj BEFORE UPDATE ON plan.scenario FOR EACH ROW EXECUTE FUNCTION ref.f_maj_horodatage();

CREATE TABLE plan.levier_ligne (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  scenario_id bigint NOT NULL REFERENCES plan.scenario(id) ON DELETE CASCADE, ordre smallint NOT NULL,
  levier_code text NOT NULL CHECK (levier_code IN ('achat_fourrage','concentre_substitution','decapitalisation','genisses','paturage','ratelier','reaffectation','correction_nutritionnelle','autre')),
  famille_code text REFERENCES ref.famille_fourrage(code), aliment_reference_id bigint REFERENCES ref.aliment_reference(id),
  lot_stock_source_id bigint REFERENCES stock.lot_stock(id) ON DELETE SET NULL, lot_animaux_id bigint REFERENCES plan.lot_animaux(id) ON DELETE SET NULL,
  famille_source_code text REFERENCES ref.famille_fourrage(code),    -- ajout : famille cédée par une réaffectation
  quantite numeric NOT NULL, unite text NOT NULL, parametre numeric, prix_unitaire numeric,
  date_effet date, gain_t_ms numeric, cout_eur numeric, commentaire text
);
CREATE INDEX ON plan.levier_ligne (scenario_id);

CREATE TABLE plan.bilan (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  inventaire_id bigint NOT NULL REFERENCES stock.inventaire(id) ON DELETE CASCADE,
  scenario_id bigint REFERENCES plan.scenario(id) ON DELETE CASCADE,
  calcule_le timestamptz NOT NULL DEFAULT now(),
  stock_utile_t_ms numeric, besoin_t_ms numeric, securite_t_ms numeric, bilan_t_ms numeric, couverture numeric,
  autonomie_jours integer, date_rupture date, date_rupture_min date, date_rupture_max date, date_entame_securite date,
  incertitude_rel numeric,                             -- ajout : incertitude relative du stock total (RC-14)
  alerte_envoyee boolean NOT NULL DEFAULT false,       -- ajout : alerte d'autonomie déjà envoyée pour ce franchissement (EF-M8-03)
  detail jsonb NOT NULL
);
CREATE UNIQUE INDEX ON plan.bilan (inventaire_id, coalesce(scenario_id, 0));

CREATE TABLE ref.substituabilite (
  famille_source text REFERENCES ref.famille_fourrage(code), famille_cible text REFERENCES ref.famille_fourrage(code),
  ratio numeric NOT NULL DEFAULT 1, part_max_ration numeric, stades_autorises text[], source text, statut_epistemique text,
  PRIMARY KEY (famille_source, famille_cible)
);
INSERT INTO ref.substituabilite (famille_source, famille_cible, ratio, part_max_ration, stades_autorises, source, statut_epistemique) VALUES
  ('FOIN', 'ENRUB', 1, NULL, NULL, 'RC-13.2 : foin ↔ regain ↔ enrubannage ↔ ensilage d''herbe à 1:1 en MS', 'consensus_professionnel'),
  ('ENRUB', 'FOIN', 1, NULL, NULL, 'RC-13.2', 'consensus_professionnel'),
  ('FOIN', 'ENSIL_HERBE', 1, NULL, NULL, 'RC-13.2', 'consensus_professionnel'),
  ('ENSIL_HERBE', 'FOIN', 1, NULL, NULL, 'RC-13.2', 'consensus_professionnel'),
  ('ENRUB', 'ENSIL_HERBE', 1, NULL, NULL, 'RC-13.2', 'consensus_professionnel'),
  ('ENSIL_HERBE', 'ENRUB', 1, NULL, NULL, 'RC-13.2', 'consensus_professionnel'),
  ('PAILLE', 'FOIN', 1, 0.30, '{entretien,tarissement}', 'RC-13.2 : paille → foin pour les stades entretien et tarissement, 30 % de la ration au plus [C]', 'consensus_professionnel');

ALTER TABLE stock.mouvement ADD FOREIGN KEY (scenario_ligne_id) REFERENCES plan.levier_ligne(id) ON DELETE SET NULL;
ALTER TABLE stock.lot_stock ADD COLUMN scenario_ligne_id bigint REFERENCES plan.levier_ligne(id) ON DELETE SET NULL;   -- ajout
SQL);

        foreach (self::PARAMETRES as [$cle, $valeur, $unite, $libelle, $source, $statut]) {
            DB::table('app.parametre_defaut')->insert([
                'gds_id' => null, 'cle' => $cle, 'valeur' => $valeur, 'unite' => $unite, 'libelle' => $libelle,
                'source' => 'Spécifications § 4.3, '.$source, 'statut_epistemique' => $statut, 'valide_du' => '2026-10-17',
            ]);
        }
    }

    public function down(): void
    {
        DB::table('app.parametre_defaut')->whereIn('cle', array_column(self::PARAMETRES, 0))->delete();
        DB::unprepared(<<<'SQL'
ALTER TABLE stock.lot_stock DROP COLUMN IF EXISTS scenario_ligne_id;
ALTER TABLE stock.mouvement DROP CONSTRAINT IF EXISTS mouvement_scenario_ligne_id_fkey;
DROP TABLE IF EXISTS ref.substituabilite, plan.bilan, plan.levier_ligne, plan.scenario CASCADE;
SQL);
    }
};
