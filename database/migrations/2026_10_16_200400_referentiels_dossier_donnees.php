<?php

use Database\Seeders\Ref\ReferentielsDossierSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lot L3, étape 2 — référentiels du dossier : version 4 des référentiels, grilles de lecture et de croisement,
 * données transcrites de la présentation Forgeneuf (GDS 24, 2026 ; décisions du 16/10/2026, § 10.4).
 *
 * Structures (§ 5.3) :
 * - ref.grille_lecture_mesure : combinaison de lectures → libellé proposé (RC-1.5) ; une règle est une liste de
 *   conditions (jsonb), sur la lecture d'une mesure ou sur sa valeur, conjointes ;
 * - ref.grille_trouble_cause : trouble × famille de cause, force 0-3 (grille NBVC) ;
 * - ajout : ref.trouble_croisement, les troubles de la grille NBVC et leur correspondance avec les indicateurs du
 *   bilan sanitaire et les troubles de ref.trouble_sanitaire (les deux listes ne se recouvrent pas : « métrite »,
 *   « veaux : mortalité, maladies » n'ont pas de code dans ref.trouble_sanitaire) ;
 * - ajout : colonne « valide » (repère validé par le vétérinaire conseil, points ouverts 2 à 4) sur les repères du
 *   dossier et sur ref.seuil_interpretation. Une valeur lue sur un repère non validé porte la mention « repère à
 *   valider » (§ 9.5, décision du 16/10/2026). Les seuils existants sont tenus pour validés (grille du comité
 *   technique) ; un seuil saisi dans E24 l'est aussi, chaque saisie portant une décision du comité (EF-M10-02) ;
 * - paramètres visite.messages_max (EF-M2-05 : cinq messages au plus), profil.part_carence_severe (0,5) et
 *   profil.part_normal_bas (0,25) (RC-1.5).
 *
 * Les données sont chargées par ReferentielsDossierSeeder depuis database/seeders/ref/dossier.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
INSERT INTO ref.version_referentiel (numero, date_version, commentaire)
VALUES ('4', DATE '2026-10-16', 'Référentiels du dossier (lot L3) : mesures terrain et repères PRITIH, lectures combinées, repères des profils sanguins, bilan sanitaire (seuils S1/S2 NBVC), grille trouble × cause, paramètres de l''eau (fer seul sourcé) ; transcrits de la présentation Forgeneuf, statut [C], à valider par le vétérinaire conseil');

ALTER TABLE ref.seuil_interpretation ADD COLUMN valide boolean NOT NULL DEFAULT true;
ALTER TABLE ref.repere_mesure_terrain ADD COLUMN valide boolean NOT NULL DEFAULT false;
ALTER TABLE ref.repere_analyse_animale ADD COLUMN valide boolean NOT NULL DEFAULT false;
ALTER TABLE ref.seuil_bilan_sanitaire ADD COLUMN valide boolean NOT NULL DEFAULT false;

CREATE TABLE ref.grille_lecture_mesure (
  id serial PRIMARY KEY,
  code text NOT NULL,
  ordre smallint NOT NULL,
  conditions jsonb NOT NULL,                          -- [{"mesure": "GLYCEMIE", "lecture": "bas"}, {"mesure": "BOH", "op": "<", "valeur": 45}…]
  libelle_propose text NOT NULL,
  espece_code text REFERENCES ref.espece_animale(code),   -- NULL = toutes espèces
  stade text,                                         -- NULL = tous stades
  reference_id integer REFERENCES ref.reference_biblio(id),
  statut_epistemique text NOT NULL DEFAULT 'consensus_professionnel'
    CHECK (statut_epistemique IN ('publie','publie_source_secondaire','consensus_professionnel','ordre_de_grandeur','hypothese_locale')),
  valide boolean NOT NULL DEFAULT false,
  actif boolean NOT NULL DEFAULT true,
  commentaire text,
  version_creation text NOT NULL REFERENCES ref.version_referentiel(numero),
  version_fin text REFERENCES ref.version_referentiel(numero),
  CHECK (jsonb_typeof(conditions) = 'array' AND jsonb_array_length(conditions) > 0)
);
CREATE UNIQUE INDEX ON ref.grille_lecture_mesure (code) WHERE actif;

CREATE TABLE ref.trouble_croisement (                 -- ajout
  code text PRIMARY KEY,
  libelle text NOT NULL,
  ordre smallint NOT NULL,
  indicateurs_sanitaires text[] NOT NULL DEFAULT '{}',   -- codes ref.indicateur_sanitaire
  troubles_sanitaires text[] NOT NULL DEFAULT '{}',      -- codes ref.trouble_sanitaire (= type_evenement)
  version_creation text NOT NULL REFERENCES ref.version_referentiel(numero),
  version_fin text REFERENCES ref.version_referentiel(numero)
);

CREATE TABLE ref.grille_trouble_cause (
  id serial PRIMARY KEY,
  trouble_code text NOT NULL REFERENCES ref.trouble_croisement(code),
  famille_cause text NOT NULL
    CHECK (famille_cause IN ('immunite_oligo','metabolisme_mineral','metabolisme_energetique','confort','infectieux')),
  force smallint NOT NULL CHECK (force BETWEEN 0 AND 3),
  reference_id integer REFERENCES ref.reference_biblio(id),
  statut_epistemique text NOT NULL DEFAULT 'consensus_professionnel'
    CHECK (statut_epistemique IN ('publie','publie_source_secondaire','consensus_professionnel','ordre_de_grandeur','hypothese_locale')),
  valide boolean NOT NULL DEFAULT false,
  actif boolean NOT NULL DEFAULT true,
  version_creation text NOT NULL REFERENCES ref.version_referentiel(numero),
  version_fin text REFERENCES ref.version_referentiel(numero)
);
CREATE UNIQUE INDEX ON ref.grille_trouble_cause (trouble_code, famille_cause) WHERE actif;

INSERT INTO app.parametre_defaut (gds_id, cle, valeur, unite, libelle, source, statut_epistemique, valide_du)
VALUES (NULL, 'visite.messages_max', 5, 'messages',
        'Nombre maximal de messages hiérarchisés d''une interprétation de visite (EF-M2-05)',
        'Spécifications, EF-M1-01 et EF-M2-05', 'consensus_professionnel', DATE '2026-10-16'),
       (NULL, 'profil.part_carence_severe', 0.5, 'fraction de la borne basse',
        'Profil sanguin : carence sévère à cette fraction de la borne basse ou en dessous (RC-1.5)',
        'Spécifications, RC-1.5 (décision du 16/10/2026)', 'consensus_professionnel', DATE '2026-10-16'),
       (NULL, 'profil.part_normal_bas', 0.25, 'fraction de l''intervalle',
        'Profil sanguin : « normal, partie basse » dans cette fraction inférieure de l''intervalle de référence (RC-1.5)',
        'Spécifications, RC-1.5', 'consensus_professionnel', DATE '2026-10-16');
SQL);

        (new ReferentielsDossierSeeder)->run();
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DELETE FROM app.parametre_defaut WHERE cle IN ('visite.messages_max', 'profil.part_carence_severe', 'profil.part_normal_bas');
DROP TABLE IF EXISTS ref.grille_trouble_cause, ref.trouble_croisement, ref.grille_lecture_mesure;
DELETE FROM ref.seuil_interpretation WHERE version_creation = '4';
DELETE FROM ref.parametre WHERE famille_code = 'EAU';
DELETE FROM ref.seuil_bilan_sanitaire; DELETE FROM ref.indicateur_sanitaire;
DELETE FROM ref.repere_analyse_animale; DELETE FROM ref.repere_mesure_terrain; DELETE FROM ref.mesure_terrain;
DELETE FROM ref.reference_biblio WHERE cle IN ('GDS24_FORGENEUF2026','PRITIH_RADIGUE','NBVC_BSE');
ALTER TABLE ref.seuil_bilan_sanitaire DROP COLUMN IF EXISTS valide;
ALTER TABLE ref.repere_analyse_animale DROP COLUMN IF EXISTS valide;
ALTER TABLE ref.repere_mesure_terrain DROP COLUMN IF EXISTS valide;
ALTER TABLE ref.seuil_interpretation DROP COLUMN IF EXISTS valide;
DELETE FROM ref.version_referentiel WHERE numero = '4';
SQL);
    }
};
