<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lot L2 — extension du socle pour l'import et l'interprétation des bulletins (chapitre 5.3, RC-1, RC-2, EF-M2, EF-M9-01, E24).
 *
 * Repris du § 5.3 : fourrage.analyse.exclu_references et motif_exclusion ; fourrage.interpretation.version_grille,
 * utilisation_conseillee, utilisation_deconseillee, niveau_propose, vise_par, vise_le ; statuts « visee » et « publie » ;
 * ref.version_referentiel, version_creation / version_fin des seuils et besoins, ref.plage_plausibilite,
 * ref.compatibilite_parametre_trouble.
 *
 * Ajouts signalés « ajout » (absents du § 5.3) :
 * - levée d'anomalie tracée sur fourrage.analyse (RC-2 : « lève l'anomalie, avec motif ») ;
 * - fourrage.alerte.censure (RC-1 : « le drapeau censure est reporté dans l'alerte ») ;
 * - fourrage.prelevement.a_completer (EF-M9-01 : « sinon création d'un prélèvement à compléter ») ;
 * - app.import_bulletin : suivi de chaque dépôt de PDF (fichier, empreinte, statut, erreur, analyse créée).
 *
 * Le statut historique « envoye » des interprétations reste admis pour les données déjà chargées (script 03).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
-- Versions de la grille (E24, EF-M10-02) : la grille du document 02 est la version 1
CREATE TABLE ref.version_referentiel (
  numero text PRIMARY KEY,                            -- '1', '2', …
  date_version date NOT NULL DEFAULT current_date,
  auteur_id bigint REFERENCES app."user"(id),
  commentaire text,                                   -- décision du comité technique
  cree_le timestamptz NOT NULL DEFAULT now()
);
INSERT INTO ref.version_referentiel (numero, date_version, commentaire)
VALUES ('1', DATE '2026-09-03', 'Grille initiale du document 02 (base de connaissance fourrages)');

ALTER TABLE ref.seuil_interpretation
  ADD COLUMN version_creation text REFERENCES ref.version_referentiel(numero),
  ADD COLUMN version_fin text REFERENCES ref.version_referentiel(numero);
UPDATE ref.seuil_interpretation SET version_creation = '1';
ALTER TABLE ref.seuil_interpretation ALTER COLUMN version_creation SET NOT NULL;

ALTER TABLE ref.besoin_animal
  ADD COLUMN version_creation text REFERENCES ref.version_referentiel(numero),
  ADD COLUMN version_fin text REFERENCES ref.version_referentiel(numero);
UPDATE ref.besoin_animal SET version_creation = '1';
ALTER TABLE ref.besoin_animal ALTER COLUMN version_creation SET NOT NULL;

-- RC-2 : plages de plausibilité (statut [CP], à valider par le comité technique)
CREATE TABLE ref.plage_plausibilite (
  id serial PRIMARY KEY,
  parametre_code text NOT NULL REFERENCES ref.parametre(code),
  famille_fourrage_code text REFERENCES ref.famille_fourrage(code),   -- NULL = tout fourrage
  borne_min numeric, borne_max numeric,               -- plage acceptée [borne_min ; borne_max] ; NULL = non borné
  statut_epistemique text NOT NULL DEFAULT 'hypothese_locale'
    CHECK (statut_epistemique IN ('publie','publie_source_secondaire','consensus_professionnel','ordre_de_grandeur','hypothese_locale')),
  source text,
  actif boolean NOT NULL DEFAULT true,
  version_creation text NOT NULL REFERENCES ref.version_referentiel(numero),
  version_fin text REFERENCES ref.version_referentiel(numero),
  commentaire text,
  CHECK (borne_min IS NULL OR borne_max IS NULL OR borne_min <= borne_max)
);
CREATE INDEX ON ref.plage_plausibilite (parametre_code, actif);

INSERT INTO ref.plage_plausibilite (parametre_code, famille_fourrage_code, borne_min, borne_max, source, version_creation) VALUES
  ('MS', 'FOIN', 75, 95, 'Spécifications § 4.1 RC-2 [CP]', '1'),
  ('MS', 'PAILLE', 80, 95, 'Spécifications § 4.1 RC-2 [CP]', '1'),
  ('MS', 'ENRUB', 25, 80, 'Spécifications § 4.1 RC-2 [CP]', '1'),
  ('MS', 'ENSIL_HERBE', 18, 60, 'Spécifications § 4.1 RC-2 [CP]', '1'),
  ('MS', 'ENSIL_MAIS', 22, 45, 'Spécifications § 4.1 RC-2 [CP]', '1'),
  ('MS', 'VERT', 10, 35, 'Spécifications § 4.1 RC-2 [CP]', '1'),
  ('MM', NULL, 20, 200, 'Spécifications § 4.1 RC-2 [CP]', '1'),
  ('MAT', NULL, 25, 300, 'Spécifications § 4.1 RC-2 [CP]', '1'),
  ('UFL_2018', NULL, 0.35, 1.15, 'Spécifications § 4.1 RC-2 [CP]', '1'),
  ('PDI', NULL, 20, 200, 'Spécifications § 4.1 RC-2 [CP]', '1'),
  ('CA', NULL, 1, 25, 'Spécifications § 4.1 RC-2 [CP]', '1'),
  ('P', NULL, 0.5, 8, 'Spécifications § 4.1 RC-2 [CP]', '1'),
  ('MG', NULL, 0.5, 6, 'Spécifications § 4.1 RC-2 [CP]', '1'),
  ('K', NULL, 2, 45, 'Spécifications § 4.1 RC-2 [CP]', '1'),
  ('NA', NULL, 0, 6, 'Spécifications § 4.1 RC-2 [CP]', '1'),
  ('CU', NULL, 0, 30, 'Spécifications § 4.1 RC-2 [CP] ; au-delà, seuil valeur_anormale (Meschy 2007)', '1'),
  ('ZN', NULL, 5, 150, 'Spécifications § 4.1 RC-2 [CP]', '1'),
  ('MN', NULL, 10, 400, 'Spécifications § 4.1 RC-2 [CP]', '1'),
  ('FE', NULL, 20, 3000, 'Spécifications § 4.1 RC-2 [CP]', '1');

-- RC-1.4 : compatibilité paramètre d'analyse × trouble sanitaire (contenu fourni par le vétérinaire conseil)
CREATE TABLE ref.compatibilite_parametre_trouble (
  parametre_code text NOT NULL REFERENCES ref.parametre(code),
  type_evenement text NOT NULL,                       -- valeurs de fourrage.evenement_sanitaire.type_evenement
  source text,
  statut_epistemique text NOT NULL DEFAULT 'hypothese_locale'
    CHECK (statut_epistemique IN ('publie','publie_source_secondaire','consensus_professionnel','ordre_de_grandeur','hypothese_locale')),
  version_creation text NOT NULL REFERENCES ref.version_referentiel(numero),
  version_fin text REFERENCES ref.version_referentiel(numero),
  PRIMARY KEY (parametre_code, type_evenement)
);

-- Analyses : exclusion des références (§ 5.3) et levée d'anomalie (ajout, RC-2)
ALTER TABLE fourrage.analyse
  ADD COLUMN exclu_references boolean NOT NULL DEFAULT false,
  ADD COLUMN motif_exclusion text,
  ADD COLUMN anomalie_levee_le timestamptz,                        -- ajout
  ADD COLUMN anomalie_levee_par bigint REFERENCES app."user"(id),  -- ajout
  ADD COLUMN anomalie_motif_levee text;                            -- ajout

-- Interprétations : § 5.3 ; statuts étendus (« envoye » conservé pour les données historiques)
ALTER TABLE fourrage.interpretation
  ADD COLUMN version_grille text REFERENCES ref.version_referentiel(numero),
  ADD COLUMN utilisation_conseillee text,
  ADD COLUMN utilisation_deconseillee text[],
  ADD COLUMN niveau_propose smallint CHECK (niveau_propose BETWEEN 0 AND 3),
  ADD COLUMN vise_par bigint REFERENCES app."user"(id),
  ADD COLUMN vise_le timestamptz;
ALTER TABLE fourrage.interpretation DROP CONSTRAINT interpretation_statut_check;
ALTER TABLE fourrage.interpretation ADD CONSTRAINT interpretation_statut_check
  CHECK (statut IN ('brouillon','valide','visee','publie','archive','envoye'));

-- Alertes : drapeau de censure (ajout, RC-1)
ALTER TABLE fourrage.alerte ADD COLUMN censure char(1) CHECK (censure IN ('<','>'));

-- Prélèvement créé à l'import faute de rapprochement, à compléter par le technicien (ajout, EF-M9-01)
ALTER TABLE fourrage.prelevement ADD COLUMN a_completer boolean NOT NULL DEFAULT false;

-- Suivi des dépôts de bulletins (ajout, EF-M9-01, E19)
CREATE TABLE app.import_bulletin (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  gds_id smallint NOT NULL REFERENCES app.gds(id),
  depose_par bigint REFERENCES app."user"(id),
  nom_fichier text NOT NULL,                          -- nom d'origine, affiché seulement
  chemin text,                                        -- disque privé, hors racine web (ENF-SEC-05)
  hash_sha256 text NOT NULL,
  taille_octets integer NOT NULL,
  statut text NOT NULL DEFAULT 'recu'
    CHECK (statut IN ('recu','en_extraction','importe','anomalie','a_rapprocher','doublon','erreur')),
  erreur text,
  donnees_extraites jsonb,
  analyse_id integer REFERENCES fourrage.analyse(id),
  cree_le timestamptz NOT NULL DEFAULT now(),
  maj_le timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX ON app.import_bulletin (gds_id, statut);
CREATE INDEX ON app.import_bulletin (hash_sha256);
CREATE TRIGGER trg_import_bulletin_maj BEFORE UPDATE ON app.import_bulletin FOR EACH ROW EXECUTE FUNCTION ref.f_maj_horodatage();
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP TABLE IF EXISTS app.import_bulletin;
ALTER TABLE fourrage.prelevement DROP COLUMN IF EXISTS a_completer;
ALTER TABLE fourrage.alerte DROP COLUMN IF EXISTS censure;
ALTER TABLE fourrage.interpretation DROP CONSTRAINT interpretation_statut_check;
ALTER TABLE fourrage.interpretation ADD CONSTRAINT interpretation_statut_check
  CHECK (statut IN ('brouillon','valide','envoye','archive'));
ALTER TABLE fourrage.interpretation
  DROP COLUMN IF EXISTS version_grille, DROP COLUMN IF EXISTS utilisation_conseillee,
  DROP COLUMN IF EXISTS utilisation_deconseillee, DROP COLUMN IF EXISTS niveau_propose,
  DROP COLUMN IF EXISTS vise_par, DROP COLUMN IF EXISTS vise_le;
ALTER TABLE fourrage.analyse
  DROP COLUMN IF EXISTS exclu_references, DROP COLUMN IF EXISTS motif_exclusion,
  DROP COLUMN IF EXISTS anomalie_levee_le, DROP COLUMN IF EXISTS anomalie_levee_par,
  DROP COLUMN IF EXISTS anomalie_motif_levee;
DROP TABLE IF EXISTS ref.compatibilite_parametre_trouble, ref.plage_plausibilite;
ALTER TABLE ref.besoin_animal DROP COLUMN IF EXISTS version_creation, DROP COLUMN IF EXISTS version_fin;
ALTER TABLE ref.seuil_interpretation DROP COLUMN IF EXISTS version_creation, DROP COLUMN IF EXISTS version_fin;
DROP TABLE IF EXISTS ref.version_referentiel;
SQL);
    }
};
