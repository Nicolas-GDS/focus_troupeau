<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lot L3 — structures des référentiels du dossier (§ 5.3 « Référentiels ajoutés dans ref », RC-1.5, EF-M1-04,
 * EF-M1-05, EF-M1-07, EF-M10-02).
 *
 * - ref.mesure_terrain (code, libellé, unité, décimales) ; ref.repere_mesure_terrain (mesure, espèce, stade, bornes,
 *   source, statut) ; ref.repere_analyse_animale (élément, matrice, espèce, bornes, source) ;
 *   ref.indicateur_sanitaire et ref.seuil_bilan_sanitaire (indicateur, espèce, système, S1, S2, source) ;
 * - famille de paramètres EAU et nature « eau » des seuils (grille eau d'abreuvement) ;
 * - clés étrangères de dossier.mesure_terrain.mesure_code et dossier.indicateur_sanitaire.indicateur_code.
 *
 * Les repères suivent la convention des seuils : source par ref.reference_biblio, statut épistémique, versions
 * version_creation / version_fin (E24). Ajouts signalés : ordre et description des mesures et des indicateurs ;
 * unité des repères d'analyses animales (fourrage.analyse_animale porte sa propre unité) ; sens des seuils S1/S2.
 *
 * Aucune donnée ici : les valeurs (points ouverts 2 à 4 du § 9.4) sont chargées à l'étape 2 depuis les sources
 * fournies, au statut [C]. Les grilles ref.grille_lecture_mesure et ref.grille_trouble_cause, dont la forme dépend
 * de ces sources, sont créées avec elles.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE ref.mesure_terrain (
  code text PRIMARY KEY,                              -- 'PH_BOUSE','REDOX_BOUSE','TAMIS_1','PH_URINE'…
  libelle text NOT NULL,
  unite text NOT NULL,
  decimales smallint NOT NULL DEFAULT 1,
  ordre smallint,                                     -- ajout
  description text                                    -- ajout
);

CREATE TABLE ref.repere_mesure_terrain (
  id serial PRIMARY KEY,
  mesure_code text NOT NULL REFERENCES ref.mesure_terrain(code),
  espece_code text REFERENCES ref.espece_animale(code),   -- NULL = toutes espèces
  stade text,                                         -- NULL = tous stades ; 'tarie','prepa_velage','lactation','veau'
  borne_basse numeric, borne_haute numeric,           -- NULL = non borné
  reference_id integer REFERENCES ref.reference_biblio(id),
  statut_epistemique text NOT NULL DEFAULT 'consensus_professionnel'
    CHECK (statut_epistemique IN ('publie','publie_source_secondaire','consensus_professionnel','ordre_de_grandeur','hypothese_locale')),
  actif boolean NOT NULL DEFAULT true,
  commentaire text,
  version_creation text NOT NULL REFERENCES ref.version_referentiel(numero),
  version_fin text REFERENCES ref.version_referentiel(numero),
  CHECK (borne_basse IS NULL OR borne_haute IS NULL OR borne_basse <= borne_haute)
);
CREATE INDEX ON ref.repere_mesure_terrain (mesure_code, actif);

CREATE TABLE ref.repere_analyse_animale (
  id serial PRIMARY KEY,
  element text NOT NULL,                              -- = fourrage.analyse_animale.parametre ('cupremie','zincemie'…)
  matrice text NOT NULL CHECK (matrice IN ('sang','serum','plasma','foie','urine','lait','poils')),
  espece_code text REFERENCES ref.espece_animale(code),   -- NULL = toutes espèces
  borne_basse numeric, borne_haute numeric,
  unite text NOT NULL,                                -- ajout : les bornes s'entendent dans cette unité
  reference_id integer REFERENCES ref.reference_biblio(id),
  statut_epistemique text NOT NULL DEFAULT 'consensus_professionnel'
    CHECK (statut_epistemique IN ('publie','publie_source_secondaire','consensus_professionnel','ordre_de_grandeur','hypothese_locale')),
  actif boolean NOT NULL DEFAULT true,
  commentaire text,
  version_creation text NOT NULL REFERENCES ref.version_referentiel(numero),
  version_fin text REFERENCES ref.version_referentiel(numero),
  CHECK (borne_basse IS NULL OR borne_haute IS NULL OR borne_basse <= borne_haute)
);
CREATE INDEX ON ref.repere_analyse_animale (element, matrice, actif);

CREATE TABLE ref.indicateur_sanitaire (
  code text PRIMARY KEY,                              -- 'IVV','MORTALITE_VEAUX_0_12M','NON_DELIVRANCE'…
  libelle text NOT NULL,
  unite text NOT NULL,                                -- 'jours', '%'…
  mode text NOT NULL DEFAULT 'taux' CHECK (mode IN ('taux','valeur')),   -- taux = nb_cas / denominateur
  ordre smallint,                                     -- ajout
  description text                                    -- ajout
);

CREATE TABLE ref.seuil_bilan_sanitaire (
  id serial PRIMARY KEY,
  indicateur_code text NOT NULL REFERENCES ref.indicateur_sanitaire(code),
  espece_code text REFERENCES ref.espece_animale(code),
  systeme text CHECK (systeme IN ('lait','viande','mixte')),   -- NULL = tous systèmes
  s1 numeric, s2 numeric,
  sens text NOT NULL DEFAULT 'haut' CHECK (sens IN ('haut','bas')),   -- ajout : 'haut' = défavorable au-delà de S1, S2
  reference_id integer REFERENCES ref.reference_biblio(id),
  statut_epistemique text NOT NULL DEFAULT 'consensus_professionnel'
    CHECK (statut_epistemique IN ('publie','publie_source_secondaire','consensus_professionnel','ordre_de_grandeur','hypothese_locale')),
  actif boolean NOT NULL DEFAULT true,
  commentaire text,
  version_creation text NOT NULL REFERENCES ref.version_referentiel(numero),
  version_fin text REFERENCES ref.version_referentiel(numero)
);
CREATE INDEX ON ref.seuil_bilan_sanitaire (indicateur_code, actif);

ALTER TABLE dossier.mesure_terrain ADD FOREIGN KEY (mesure_code) REFERENCES ref.mesure_terrain(code);
ALTER TABLE dossier.indicateur_sanitaire ADD FOREIGN KEY (indicateur_code) REFERENCES ref.indicateur_sanitaire(code);

-- Grille eau d'abreuvement : famille de paramètres EAU et nature « eau » des seuils (§ 5.3)
INSERT INTO ref.famille_parametre (code, libelle, ordre) VALUES ('EAU', 'Eau d''abreuvement', 100);
ALTER TABLE ref.seuil_interpretation DROP CONSTRAINT seuil_interpretation_nature_check;
ALTER TABLE ref.seuil_interpretation ADD CONSTRAINT seuil_interpretation_nature_check
  CHECK (nature IN ('nutritionnel','sanitaire','reglementaire','statistique_local','eau'));
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DELETE FROM ref.seuil_interpretation WHERE nature = 'eau';
ALTER TABLE ref.seuil_interpretation DROP CONSTRAINT seuil_interpretation_nature_check;
ALTER TABLE ref.seuil_interpretation ADD CONSTRAINT seuil_interpretation_nature_check
  CHECK (nature IN ('nutritionnel','sanitaire','reglementaire','statistique_local'));
DELETE FROM ref.parametre WHERE famille_code = 'EAU';
DELETE FROM ref.famille_parametre WHERE code = 'EAU';
ALTER TABLE dossier.mesure_terrain DROP CONSTRAINT IF EXISTS mesure_terrain_mesure_code_fkey;
ALTER TABLE dossier.indicateur_sanitaire DROP CONSTRAINT IF EXISTS indicateur_sanitaire_indicateur_code_fkey;
DROP TABLE IF EXISTS ref.seuil_bilan_sanitaire, ref.indicateur_sanitaire, ref.repere_analyse_animale,
  ref.repere_mesure_terrain, ref.mesure_terrain;
SQL);
    }
};
