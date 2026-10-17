<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lot L5 — schémas stock et plan (§ 5.4, § 5.5 ; EF-M4, EF-M5) et référentiels associés.
 *
 * Repris du chapitre 5 : stock.inventaire, periode_phase, lot_stock, mouvement ; plan.lot_animaux, phase, ration,
 * ration_composant ; ref.aliment_reference et ref.aliment_reference_valeur (créées vides, point ouvert 5) ;
 * ref.besoin_journalier. Les tables plan.bilan, plan.scenario, plan.levier_ligne et ref.substituabilite viennent au
 * lot L6. Les tables du socle fourrage.lot_animaux, ration et ration_composant restent intactes (décision du
 * 17/10/2026 : plan.* fait foi).
 *
 * Ajouts signalés « ajout » (absents du chapitre 5) :
 * - cree_par, maj_par, maj_le et déclencheur d'horodatage sur les objets métier (EF-GEN-06) ;
 * - stock.lot_stock.pertes_defaut_pct : valeur par défaut affichée à côté de la valeur retenue (EF-M4-07, D10) ;
 * - plan.ration.mode_distribution et pertes_distribution_pct : pertes à la distribution du lot (RC-8.3, RC-6) ;
 * - ref.coefficient_ugb : coefficients UGB de RC-8.1 (Idele 2020) ; ref.categorie_animale.coefficient_ugb_code :
 *   coefficient proposé par défaut pour une catégorie du socle (aucun quand RC-8.1 n'en donne pas) ;
 * - ref.besoin_journalier.base (« animal » ou « par_kg_lait ») et valide (« repère à valider », § 9.5) ;
 * - dossier.etiquette_aliment.ms_pct : matière sèche d'un concentré décrit par étiquette (décision du 17/10/2026) ;
 * - clé étrangère dossier.mesure_terrain.lot_animaux_id (décision du 16/10/2026).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE EXTENSION IF NOT EXISTS btree_gist;      -- non-chevauchement des phases d'un lot d'animaux (§ 5.5)
CREATE SCHEMA IF NOT EXISTS stock;
CREATE SCHEMA IF NOT EXISTS plan;

CREATE TABLE stock.inventaire (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  elevage_id uuid NOT NULL REFERENCES fourrage.elevage(id), visite_id bigint REFERENCES dossier.visite(id),
  date_inventaire date NOT NULL,
  type text NOT NULL CHECK (type IN ('fin_recolte','mi_hiver','crise','autre')),
  date_debut date NOT NULL, date_fin date NOT NULL,   -- période de référence (RC-10)
  marge_securite numeric NOT NULL,                    -- copie de securite.marge en vigueur (RC-12)
  annee_seche boolean NOT NULL DEFAULT false,
  statut text NOT NULL DEFAULT 'courant' CHECK (statut IN ('courant','clos','archive')),
  commentaire text,
  cree_par bigint REFERENCES app."user"(id), maj_par bigint REFERENCES app."user"(id),   -- maj_par : ajout
  cree_le timestamptz NOT NULL DEFAULT now(), maj_le timestamptz NOT NULL DEFAULT now(),
  CHECK (date_fin >= date_debut)
);
CREATE UNIQUE INDEX ON stock.inventaire (elevage_id) WHERE statut = 'courant';
CREATE TRIGGER trg_inventaire_maj BEFORE UPDATE ON stock.inventaire FOR EACH ROW EXECUTE FUNCTION ref.f_maj_horodatage();

CREATE TABLE stock.periode_phase (                    -- voie UGB uniquement (RC-10)
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  inventaire_id bigint NOT NULL REFERENCES stock.inventaire(id) ON DELETE CASCADE,
  libelle text NOT NULL CHECK (libelle IN ('hivernage','transition','complementation_estivale')),
  date_debut date NOT NULL, date_fin date NOT NULL, coefficient numeric NOT NULL DEFAULT 1,
  CHECK (date_fin >= date_debut)
);

CREATE TABLE stock.lot_stock (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  inventaire_id bigint NOT NULL REFERENCES stock.inventaire(id) ON DELETE CASCADE,
  elevage_id uuid NOT NULL REFERENCES fourrage.elevage(id),
  libelle text NOT NULL,
  famille_code text NOT NULL REFERENCES ref.famille_fourrage(code),
  type_fourrage_code text REFERENCES ref.type_fourrage(code),
  prelevement_id integer REFERENCES fourrage.prelevement(id),
  forme text NOT NULL CHECK (forme IN ('silo','balle_ronde','balle_carree','botte','vrac')),
  date_recolte date, priorite_consommation smallint,
  ouvrage text CHECK (ouvrage IN ('couloir','taupiniere')),
  equation_code text CHECK (equation_code IN ('mais_couloir','mais_taupe','sorgho_couloir','sorgho_taupe','herbe_courts','herbe_longs','herbe_couloir','herbe_taupe')),
  l1_m numeric, l2_m numeric, largeur_m numeric, hauteur_m numeric, nb_mesures_hauteur smallint,
  date_fermeture date, amidon_pct numeric, grain_pct numeric,
  methode_densite text CHECK (methode_densite IN ('equation','tamisage','carottage','saisie')), densite_kg_ms_m3 numeric,
  nombre numeric, poids_brut_kg numeric, poids_mesure boolean NOT NULL DEFAULT false, volume_m3 numeric,
  mode_stockage text,                                 -- clé de app.parametre_defaut 'pertes.stockage.*' ou 'pertes.conservation.*'
  ms_pct numeric CHECK (ms_pct > 0 AND ms_pct <= 100), ms_origine text CHECK (ms_origine IN ('analyse','defaut','saisie')),
  pertes_pct numeric NOT NULL CHECK (pertes_pct >= 0 AND pertes_pct < 1),
  pertes_defaut_pct numeric,                          -- ajout : valeur par défaut au moment de la saisie (EF-M4-07)
  volume_calc_m3 numeric, densite_calc numeric, stock_brut_t_ms numeric, stock_utile_t_ms numeric,
  incertitude_rel numeric,
  commentaire text,
  cree_par bigint REFERENCES app."user"(id), maj_par bigint REFERENCES app."user"(id),   -- maj_par : ajout
  cree_le timestamptz NOT NULL DEFAULT now(), maj_le timestamptz NOT NULL DEFAULT now(),
  CHECK (forme <> 'silo' OR (ouvrage IS NOT NULL AND methode_densite IS NOT NULL))
);
CREATE INDEX ON stock.lot_stock (elevage_id, inventaire_id);
CREATE TRIGGER trg_lot_stock_maj BEFORE UPDATE ON stock.lot_stock FOR EACH ROW EXECUTE FUNCTION ref.f_maj_horodatage();

CREATE TABLE stock.mouvement (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  lot_stock_id bigint NOT NULL REFERENCES stock.lot_stock(id) ON DELETE CASCADE,
  date_mouvement date NOT NULL,
  type text NOT NULL CHECK (type IN ('achat','vente','recomptage','ajustement')),
  quantite_t_ms numeric,                               -- signée pour achat/vente/ajustement ; valeur absolue pour recomptage
  detail jsonb,
  prix_eur numeric, scenario_ligne_id bigint,          -- clé étrangère vers plan.levier_ligne posée au lot L6
  motif text,
  cree_par bigint REFERENCES app."user"(id),
  cree_le timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX ON stock.mouvement (lot_stock_id, date_mouvement);

CREATE TABLE plan.lot_animaux (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  elevage_id uuid NOT NULL REFERENCES fourrage.elevage(id),
  inventaire_id bigint REFERENCES stock.inventaire(id),   -- NULL : lot « permanent » réutilisable
  libelle text NOT NULL, espece_code text NOT NULL REFERENCES ref.espece_animale(code),
  categorie_code text NOT NULL REFERENCES ref.categorie_animale(code),
  coeff_ugb numeric,
  poids_vif_kg numeric, production_valeur numeric,
  production_unite text CHECK (production_unite IN ('kg_lait_j','gmq_g_j')),
  archive_le timestamptz,
  cree_par bigint REFERENCES app."user"(id), maj_par bigint REFERENCES app."user"(id),   -- maj_par : ajout
  cree_le timestamptz NOT NULL DEFAULT now(), maj_le timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX ON plan.lot_animaux (elevage_id);
CREATE TRIGGER trg_lot_animaux_maj BEFORE UPDATE ON plan.lot_animaux FOR EACH ROW EXECUTE FUNCTION ref.f_maj_horodatage();

CREATE TABLE plan.phase (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  lot_animaux_id bigint NOT NULL REFERENCES plan.lot_animaux(id) ON DELETE CASCADE,
  stade text NOT NULL CHECK (stade IN ('entretien','gestation','fin_gestation','prepa_velage','lactation','croissance','engraissement','tarissement')),
  date_debut date NOT NULL, date_fin date NOT NULL, effectif integer NOT NULL CHECK (effectif >= 0),
  CHECK (date_fin >= date_debut),
  EXCLUDE USING gist (lot_animaux_id WITH =, daterange(date_debut, date_fin, '[]') WITH &&)
);

CREATE TABLE plan.ration (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  phase_id bigint NOT NULL UNIQUE REFERENCES plan.phase(id) ON DELETE CASCADE,
  mode_distribution text,                              -- ajout : clé 'pertes.distribution.*' (RC-6, RC-8.3)
  pertes_distribution_pct numeric NOT NULL CHECK (pertes_distribution_pct >= 0 AND pertes_distribution_pct < 1),   -- ajout
  commentaire text, saisie_par bigint REFERENCES app."user"(id), maj_le timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE ref.aliment_reference (                  -- tables INRAE-CIRAD-AFZ : créée vide (point ouvert 5)
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  code text UNIQUE NOT NULL, libelle text NOT NULL,
  categorie text NOT NULL CHECK (categorie IN ('fourrage','cereale','correcteur_azote','tourteau','coproduit','mineral','autre')),
  famille_code text REFERENCES ref.famille_fourrage(code),
  ms_pct numeric NOT NULL, reference_id integer REFERENCES ref.reference_biblio(id), statut_epistemique text NOT NULL,
  actif boolean NOT NULL DEFAULT true
);
CREATE TABLE ref.aliment_reference_valeur (
  aliment_reference_id bigint REFERENCES ref.aliment_reference(id) ON DELETE CASCADE,
  parametre_code text REFERENCES ref.parametre(code), valeur numeric NOT NULL, unite text NOT NULL,
  PRIMARY KEY (aliment_reference_id, parametre_code)
);

CREATE TABLE plan.ration_composant (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  ration_id bigint NOT NULL REFERENCES plan.ration(id) ON DELETE CASCADE, ordre smallint NOT NULL,
  source text NOT NULL CHECK (source IN ('lot_stock','famille_stock','aliment_reference','etiquette','paturage')),
  lot_stock_id bigint REFERENCES stock.lot_stock(id), famille_code text REFERENCES ref.famille_fourrage(code),
  aliment_reference_id bigint REFERENCES ref.aliment_reference(id), etiquette_id bigint REFERENCES dossier.etiquette_aliment(id),
  quantite_brute_kg numeric, kg_ms numeric NOT NULL CHECK (kg_ms >= 0),
  CHECK ((source='lot_stock' AND lot_stock_id IS NOT NULL) OR (source='famille_stock' AND famille_code IS NOT NULL)
      OR (source='aliment_reference' AND aliment_reference_id IS NOT NULL) OR (source='etiquette' AND etiquette_id IS NOT NULL) OR source='paturage')
);
CREATE INDEX ON plan.ration_composant (ration_id);

CREATE TABLE ref.coefficient_ugb (                    -- ajout : coefficients de RC-8.1 (Idele 2020)
  code text PRIMARY KEY, libelle text NOT NULL, coefficient numeric NOT NULL,
  espece_code text REFERENCES ref.espece_animale(code),
  source text, statut_epistemique text NOT NULL, ordre smallint
);
ALTER TABLE ref.categorie_animale ADD COLUMN coefficient_ugb_code text REFERENCES ref.coefficient_ugb(code);   -- ajout : coefficient proposé par défaut

CREATE TABLE ref.besoin_journalier (                  -- UFL, PDI, concentrations par stade (RC-9)
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  categorie_code text NOT NULL REFERENCES ref.categorie_animale(code), stade text NOT NULL,
  poids_vif_kg numeric, production_valeur numeric, production_unite text,
  parametre_code text NOT NULL REFERENCES ref.parametre(code), mode text NOT NULL CHECK (mode IN ('par_jour','par_kg_ms')),
  base text NOT NULL DEFAULT 'animal' CHECK (base IN ('animal','par_kg_lait')),   -- ajout
  valeur_min numeric, valeur_max numeric, valeur numeric,
  reference_id integer REFERENCES ref.reference_biblio(id), statut_epistemique text NOT NULL, commentaire text,
  valide boolean NOT NULL DEFAULT false,              -- ajout : « repère à valider » (§ 9.5)
  version_creation text REFERENCES ref.version_referentiel(numero), version_fin text REFERENCES ref.version_referentiel(numero)
);

ALTER TABLE dossier.etiquette_aliment ADD COLUMN ms_pct numeric CHECK (ms_pct > 0 AND ms_pct <= 100);
ALTER TABLE dossier.mesure_terrain ADD FOREIGN KEY (lot_animaux_id) REFERENCES plan.lot_animaux(id);
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE dossier.mesure_terrain DROP CONSTRAINT IF EXISTS mesure_terrain_lot_animaux_id_fkey;
ALTER TABLE dossier.etiquette_aliment DROP COLUMN IF EXISTS ms_pct;
ALTER TABLE ref.categorie_animale DROP COLUMN IF EXISTS coefficient_ugb_code;
DROP TABLE IF EXISTS ref.besoin_journalier, ref.coefficient_ugb, plan.ration_composant, ref.aliment_reference_valeur,
  ref.aliment_reference, plan.ration, plan.phase, plan.lot_animaux, stock.mouvement, stock.lot_stock, stock.periode_phase,
  stock.inventaire CASCADE;
SQL);
    }
};
