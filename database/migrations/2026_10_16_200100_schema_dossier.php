<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lot L3 — schéma dossier (chapitre 5.3 ; EF-M1, EF-M2-05 à EF-M2-09, EF-GEN-06).
 *
 * Repris du § 5.3 : dossier.visite, analyse_eau, resultat_eau, mesure_terrain, etiquette_aliment,
 * etiquette_composition, indicateur_sanitaire, interpretation_visite, message, cause_commune, plan_action, action,
 * controle, note_liaison ; fourrage.prelevement.visite_id. (Les colonnes du § 5.3 sur fourrage.analyse et
 * fourrage.interpretation ont été posées au lot L2.)
 *
 * Ajouts signalés « ajout » (absents du § 5.3) :
 * - cree_par, maj_par, maj_le et déclencheur d'horodatage sur les objets métier (EF-GEN-06) ;
 * - fourrage.analyse_animale : visite_id (EF-M1-09), statut (§ 2.3 : l'éleveur ne lit que les profils publiés),
 *   lecture_code (RC-1.5, comme dossier.mesure_terrain), cree_le / maj_le ;
 * - dossier.etiquette_aliment.visite_id : clé étrangère vers dossier.visite ;
 * - dossier.note_liaison : elevage_id (une note peut naître d'une interprétation de bulletin ou de visite : le
 *   cloisonnement ne peut suivre une seule chaîne), statut, clés étrangères ;
 * - dossier.message.origine et reference_objet : provenance du message (alerte d'un lot, lecture de l'eau…) ;
 * - dossier.interpretation_visite : niveau_propose (RC-1.4 : proposé et retenu sont conservés) ; refus de visa
 *   (motif, auteur, date), comme fourrage.interpretation (décision du 15/10/2026) ; clé étrangère de version_grille ;
 * - dossier.action : valide_par / valide_le (§ 2.3 : le vétérinaire conseil valide les actions vétérinaires).
 *
 * La clé étrangère dossier.mesure_terrain.lot_animaux_id vers plan.lot_animaux est posée au lot L5 (décision du
 * 16/10/2026). Les statuts sont ceux du § 5.3 : « publie » pour l'eau et les interprétations, « publiee » pour les
 * mesures, profils sanguins et étiquettes.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE SCHEMA IF NOT EXISTS dossier;            -- les tables sont vidées par migrate:fresh (search_path), pas le schéma

CREATE TABLE dossier.visite (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  elevage_id uuid NOT NULL REFERENCES fourrage.elevage(id),
  campagne_code text REFERENCES fourrage.campagne(code),
  date_visite date NOT NULL, technicien_id bigint REFERENCES app."user"(id),
  motif text NOT NULL CHECK (motif IN ('initiale','suivi','question_sanitaire','bilan_stocks','autre')),
  motif_detail text,
  compte_rendu text,                                  -- étape « collecter » : entretien, observations
  statut text NOT NULL DEFAULT 'en_cours' CHECK (statut IN ('en_cours','interpretee','publiee','archivee')),
  cree_par bigint REFERENCES app."user"(id), maj_par bigint REFERENCES app."user"(id),   -- ajout
  cree_le timestamptz NOT NULL DEFAULT now(), maj_le timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX ON dossier.visite (elevage_id, date_visite);
CREATE TRIGGER trg_visite_maj BEFORE UPDATE ON dossier.visite FOR EACH ROW EXECUTE FUNCTION ref.f_maj_horodatage();

ALTER TABLE fourrage.prelevement ADD COLUMN visite_id bigint REFERENCES dossier.visite(id);
CREATE INDEX ON fourrage.prelevement (visite_id);

-- Profils sanguins (ajout : rattachement à la visite, statut de publication, lecture RC-1.5, horodatage)
ALTER TABLE fourrage.analyse_animale
  ADD COLUMN visite_id bigint REFERENCES dossier.visite(id),
  ADD COLUMN statut text NOT NULL DEFAULT 'saisie' CHECK (statut IN ('saisie','validee','publiee')),
  ADD COLUMN lecture_code text,
  ADD COLUMN cree_par bigint REFERENCES app."user"(id), ADD COLUMN maj_par bigint REFERENCES app."user"(id),
  ADD COLUMN cree_le timestamptz NOT NULL DEFAULT now(), ADD COLUMN maj_le timestamptz NOT NULL DEFAULT now();
CREATE INDEX ON fourrage.analyse_animale (visite_id);
CREATE TRIGGER trg_analyse_animale_maj BEFORE UPDATE ON fourrage.analyse_animale FOR EACH ROW EXECUTE FUNCTION ref.f_maj_horodatage();

CREATE TABLE dossier.analyse_eau (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  elevage_id uuid NOT NULL REFERENCES fourrage.elevage(id), visite_id bigint REFERENCES dossier.visite(id),
  point_prelevement text NOT NULL,                    -- 'source', 'forage', 'abreuvoir lot taries'…
  date_prelevement date NOT NULL, laboratoire_id integer REFERENCES ref.laboratoire(id),
  fichier_pdf text, hash_pdf text,
  statut text NOT NULL DEFAULT 'recu' CHECK (statut IN ('recu','controle','valide','publie','anomalie')),
  cree_par bigint REFERENCES app."user"(id), maj_par bigint REFERENCES app."user"(id),   -- ajout
  cree_le timestamptz NOT NULL DEFAULT now(), maj_le timestamptz NOT NULL DEFAULT now()  -- maj_le : ajout
);
CREATE INDEX ON dossier.analyse_eau (elevage_id, date_prelevement);
CREATE TRIGGER trg_analyse_eau_maj BEFORE UPDATE ON dossier.analyse_eau FOR EACH ROW EXECUTE FUNCTION ref.f_maj_horodatage();

CREATE TABLE dossier.resultat_eau (                   -- même logique que fourrage.resultat
  analyse_eau_id bigint REFERENCES dossier.analyse_eau(id) ON DELETE CASCADE,
  parametre_code text NOT NULL REFERENCES ref.parametre(code),   -- paramètres 'EAU_*' ajoutés au référentiel
  valeur numeric, censure char(1) CHECK (censure IN ('<','>')), unite text NOT NULL,
  flag_anomalie boolean NOT NULL DEFAULT false,
  PRIMARY KEY (analyse_eau_id, parametre_code)
);

CREATE TABLE dossier.mesure_terrain (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  elevage_id uuid NOT NULL REFERENCES fourrage.elevage(id), visite_id bigint REFERENCES dossier.visite(id),
  lot_animaux_id bigint,                              -- FK plan.lot_animaux posée au lot L5
  date_mesure date NOT NULL, identifiant_animal text, -- n° de travail, facultatif
  stade text,                                         -- 'tarie','prepa_velage','lactation','veau'
  mesure_code text NOT NULL,                          -- FK ref.mesure_terrain : migration des référentiels du dossier
  valeur numeric NOT NULL, unite text NOT NULL,
  lecture_code text,                                  -- résultat de RC-1.5
  statut text NOT NULL DEFAULT 'saisie' CHECK (statut IN ('saisie','validee','publiee')),
  saisie_par bigint REFERENCES app."user"(id),
  maj_par bigint REFERENCES app."user"(id),           -- ajout
  cree_le timestamptz NOT NULL DEFAULT now(), maj_le timestamptz NOT NULL DEFAULT now()   -- maj_le : ajout
);
CREATE INDEX ON dossier.mesure_terrain (elevage_id, date_mesure);
CREATE INDEX ON dossier.mesure_terrain (visite_id);
CREATE TRIGGER trg_mesure_terrain_maj BEFORE UPDATE ON dossier.mesure_terrain FOR EACH ROW EXECUTE FUNCTION ref.f_maj_horodatage();

CREATE TABLE dossier.etiquette_aliment (              -- minéral, bolus, seau, aliment du commerce
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  elevage_id uuid NOT NULL REFERENCES fourrage.elevage(id),
  visite_id bigint REFERENCES dossier.visite(id),     -- clé étrangère : ajout
  nature text NOT NULL CHECK (nature IN ('mineral','bolus','seau','sel','concentre','autre')),
  libelle_eleveur text NOT NULL,                      -- saisi tel quel, jamais repris dans une préconisation (EF-GEN-04)
  photo text, date_limite date, dose_g_par_animal_jour numeric, mode_distribution text,
  statut text NOT NULL DEFAULT 'saisie' CHECK (statut IN ('saisie','validee','publiee')),
  cree_par bigint REFERENCES app."user"(id), maj_par bigint REFERENCES app."user"(id),   -- maj_par : ajout
  cree_le timestamptz NOT NULL DEFAULT now(), maj_le timestamptz NOT NULL DEFAULT now()  -- maj_le : ajout
);
CREATE INDEX ON dossier.etiquette_aliment (elevage_id);
CREATE TRIGGER trg_etiquette_aliment_maj BEFORE UPDATE ON dossier.etiquette_aliment FOR EACH ROW EXECUTE FUNCTION ref.f_maj_horodatage();

CREATE TABLE dossier.etiquette_composition (
  etiquette_id bigint REFERENCES dossier.etiquette_aliment(id) ON DELETE CASCADE,
  parametre_code text REFERENCES ref.parametre(code), valeur numeric NOT NULL, unite text NOT NULL,  -- par kg de produit
  PRIMARY KEY (etiquette_id, parametre_code)
);

CREATE TABLE dossier.indicateur_sanitaire (           -- bilan sanitaire GDS (import SAGEMA ou saisie)
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  elevage_id uuid NOT NULL REFERENCES fourrage.elevage(id), campagne_code text NOT NULL,
  indicateur_code text NOT NULL,                      -- FK ref.indicateur_sanitaire : migration des référentiels du dossier
  valeur numeric, nb_cas integer, denominateur integer,  -- taux = nb_cas / denominateur (vêlages)
  source text CHECK (source IN ('sagema','saisie')), importe_le timestamptz NOT NULL DEFAULT now(),
  cree_par bigint REFERENCES app."user"(id), maj_par bigint REFERENCES app."user"(id),   -- ajout
  maj_le timestamptz NOT NULL DEFAULT now(),                                              -- ajout
  UNIQUE (elevage_id, campagne_code, indicateur_code)
);
CREATE TRIGGER trg_indicateur_sanitaire_maj BEFORE UPDATE ON dossier.indicateur_sanitaire FOR EACH ROW EXECUTE FUNCTION ref.f_maj_horodatage();

CREATE TABLE dossier.interpretation_visite (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  visite_id bigint NOT NULL REFERENCES dossier.visite(id),
  version smallint NOT NULL DEFAULT 1,
  niveau_global smallint CHECK (niveau_global BETWEEN 0 AND 3),
  niveau_propose smallint CHECK (niveau_propose BETWEEN 0 AND 3),   -- ajout : niveau proposé conservé (RC-1.4, EF-M2-04)
  titre text, synthese text, limites text,
  version_grille text NOT NULL REFERENCES ref.version_referentiel(numero),
  statut text NOT NULL DEFAULT 'brouillon' CHECK (statut IN ('brouillon','valide','visee','publie','archive')),
  redige_par bigint REFERENCES app."user"(id), vise_par bigint REFERENCES app."user"(id), vise_le timestamptz,
  refus_visa_motif text, refus_visa_par bigint REFERENCES app."user"(id), refus_visa_le timestamptz,   -- ajout
  publie_par bigint REFERENCES app."user"(id), publie_le timestamptz,
  fiche_html text, fiche_pdf text,                    -- version figée à la publication
  maj_par bigint REFERENCES app."user"(id),           -- ajout
  cree_le timestamptz NOT NULL DEFAULT now(), maj_le timestamptz NOT NULL DEFAULT now(),
  UNIQUE (visite_id, version)
);
CREATE TRIGGER trg_interpretation_visite_maj BEFORE UPDATE ON dossier.interpretation_visite FOR EACH ROW EXECUTE FUNCTION ref.f_maj_horodatage();

CREATE TABLE dossier.message (                        -- les cinq messages hiérarchisés
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  interpretation_visite_id bigint REFERENCES dossier.interpretation_visite(id) ON DELETE CASCADE,
  ordre smallint NOT NULL, gravite smallint NOT NULL CHECK (gravite BETWEEN 0 AND 3), texte text NOT NULL,
  pilier text CHECK (pilier IN ('hydratation','nutrition','physique','environnement','bien_etre')),
  origine text NOT NULL DEFAULT 'technicien'          -- ajout : provenance du message
    CHECK (origine IN ('lot','eau','profil_sanguin','mesure_terrain','bilan_sanitaire','technicien')),
  reference_objet text,                               -- ajout : ex. 'fourrage.interpretation:12'
  UNIQUE (interpretation_visite_id, ordre)
);

CREATE TABLE dossier.cause_commune (                  -- étape « croiser »
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  interpretation_visite_id bigint REFERENCES dossier.interpretation_visite(id) ON DELETE CASCADE,
  famille_cause text NOT NULL                         -- énumération du § 5.3
    CHECK (famille_cause IN ('immunite_oligo','metabolisme_mineral','metabolisme_energetique','confort','infectieux')),
  titre text NOT NULL, preuves text, consequences text
);

CREATE TABLE dossier.plan_action (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  interpretation_visite_id bigint NOT NULL UNIQUE REFERENCES dossier.interpretation_visite(id) ON DELETE CASCADE,
  cree_le timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE dossier.action (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  plan_action_id bigint REFERENCES dossier.plan_action(id) ON DELETE CASCADE,
  priorite smallint NOT NULL CHECK (priorite BETWEEN 1 AND 3), ordre smallint NOT NULL,
  responsable text NOT NULL CHECK (responsable IN ('eleveur','technicien','veterinaire')),
  titre text NOT NULL, comment text, quand text, echeance date,
  jalon text CHECK (jalon IN ('tarissement','6_sem_avant_velage','3_sem_avant_velage','velage','entree_hivernage','mise_a_l_herbe','campagne_suivante')),
  statut text NOT NULL DEFAULT 'a_faire' CHECK (statut IN ('a_faire','faite','abandonnee')),
  fait_le date, fait_par bigint REFERENCES app."user"(id), commentaire_eleveur text,
  valide_par bigint REFERENCES app."user"(id), valide_le timestamptz,   -- ajout : visa des actions vétérinaires (§ 2.3)
  cree_le timestamptz NOT NULL DEFAULT now(), maj_le timestamptz NOT NULL DEFAULT now(),   -- ajout
  maj_par bigint REFERENCES app."user"(id)                                                 -- ajout
);
CREATE INDEX ON dossier.action (plan_action_id);
CREATE TRIGGER trg_action_maj BEFORE UPDATE ON dossier.action FOR EACH ROW EXECUTE FUNCTION ref.f_maj_horodatage();

CREATE TABLE dossier.controle (                       -- étape « suivre »
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  action_id bigint REFERENCES dossier.action(id) ON DELETE CASCADE,
  mesure_code text, cible text NOT NULL, echeance date NOT NULL,
  resultat text, mesure_terrain_id bigint REFERENCES dossier.mesure_terrain(id), analyse_animale_id integer REFERENCES fourrage.analyse_animale(id),
  statut text NOT NULL DEFAULT 'planifie' CHECK (statut IN ('planifie','realise','echu')),
  cree_le timestamptz NOT NULL DEFAULT now(), maj_le timestamptz NOT NULL DEFAULT now(),   -- ajout
  maj_par bigint REFERENCES app."user"(id)                                                 -- ajout
);
CREATE INDEX ON dossier.controle (action_id);
CREATE INDEX ON dossier.controle (statut, echeance);
CREATE TRIGGER trg_controle_maj BEFORE UPDATE ON dossier.controle FOR EACH ROW EXECUTE FUNCTION ref.f_maj_horodatage();

CREATE TABLE dossier.note_liaison (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  elevage_id uuid NOT NULL REFERENCES fourrage.elevage(id),                      -- ajout
  interpretation_id integer REFERENCES fourrage.interpretation(id),
  interpretation_visite_id bigint REFERENCES dossier.interpretation_visite(id),  -- clé étrangère : ajout
  veterinaire_user_id bigint REFERENCES app."user"(id), question text, retour text,
  pdf text, envoyee_le timestamptz, retour_le timestamptz,
  statut text NOT NULL DEFAULT 'brouillon' CHECK (statut IN ('brouillon','envoyee','retour_recu')),   -- ajout
  cree_par bigint REFERENCES app."user"(id), maj_par bigint REFERENCES app."user"(id),               -- ajout
  cree_le timestamptz NOT NULL DEFAULT now(), maj_le timestamptz NOT NULL DEFAULT now(),             -- ajout
  CHECK (interpretation_id IS NOT NULL OR interpretation_visite_id IS NOT NULL)
);
CREATE INDEX ON dossier.note_liaison (elevage_id);
CREATE TRIGGER trg_note_liaison_maj BEFORE UPDATE ON dossier.note_liaison FOR EACH ROW EXECUTE FUNCTION ref.f_maj_horodatage();
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE fourrage.prelevement DROP COLUMN IF EXISTS visite_id;
DROP TRIGGER IF EXISTS trg_analyse_animale_maj ON fourrage.analyse_animale;
ALTER TABLE fourrage.analyse_animale
  DROP COLUMN IF EXISTS visite_id, DROP COLUMN IF EXISTS statut, DROP COLUMN IF EXISTS lecture_code,
  DROP COLUMN IF EXISTS cree_par, DROP COLUMN IF EXISTS maj_par, DROP COLUMN IF EXISTS cree_le, DROP COLUMN IF EXISTS maj_le;
DROP SCHEMA dossier CASCADE;
SQL);
    }
};
