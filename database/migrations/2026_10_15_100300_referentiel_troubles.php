<?php

use Database\Seeders\Ref\TroublesSanitairesSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Référentiel minéraux × troubles sanitaires (chapitre 11 ; § 5.3 ; RC-1.4 ; décisions du 15/10/2026).
 *
 * - version 2 des référentiels : ajout de ce référentiel (les seuils de la grille restent ceux de la version 1) ;
 * - ref.reference_biblio : colonnes date_consultation et commentaire (ajout, portées par le complément
 *   bibliographique) ; REG2018_1039 relevée en « verifie » (annexe consultée, chapitre 11 § 8) ;
 * - ref.trouble_sanitaire et ref.compatibilite_parametre_trouble (structure du chapitre 11) ;
 * - ref.seuil_interpretation.sens (bas / haut), pour relier une alerte au sens des liens de compatibilité ;
 * - paramètre interpretation.force_min_rehaussement (2, [CP]).
 * Les données sont chargées par TroublesSanitairesSeeder depuis database/seeders/ref.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
INSERT INTO ref.version_referentiel (numero, date_version, commentaire)
VALUES ('2', DATE '2026-10-15', 'Référentiel minéraux × troubles sanitaires (chapitre 11) : troubles, compatibilités, complément bibliographique ; grille de seuils inchangée');

ALTER TABLE ref.reference_biblio
  ADD COLUMN date_consultation date,                  -- ajout
  ADD COLUMN commentaire text;                        -- ajout
UPDATE ref.reference_biblio SET statut_verif = 'verifie' WHERE cle = 'REG2018_1039';

CREATE TABLE ref.trouble_sanitaire (
  code                    text PRIMARY KEY,            -- = fourrage.evenement_sanitaire.type_evenement
  libelle                 text NOT NULL,
  famille                 text NOT NULL,
  especes                 text[] NOT NULL,
  categories_cibles       text[],
  type_evenement_existant text,                        -- code déjà présent dans l'énumération du document 02
  description             text,
  reference_id            integer REFERENCES ref.reference_biblio(id),
  statut_epistemique      text NOT NULL DEFAULT 'publie'
    CHECK (statut_epistemique IN ('publie','publie_source_secondaire','consensus_professionnel','ordre_de_grandeur','hypothese_locale')),
  version_creation        text NOT NULL REFERENCES ref.version_referentiel(numero),
  version_fin             text REFERENCES ref.version_referentiel(numero)
);

CREATE TABLE ref.compatibilite_parametre_trouble (
  id                   serial PRIMARY KEY,
  parametre_code       text NOT NULL REFERENCES ref.parametre(code),
  sens                 text NOT NULL CHECK (sens IN ('carence','exces','ratio_bas','ratio_eleve')),
  trouble_code         text NOT NULL REFERENCES ref.trouble_sanitaire(code),
  especes              text[],                         -- NULL = toutes
  categories           text[],                         -- NULL = toutes
  force                smallint NOT NULL CHECK (force BETWEEN 1 AND 3),
  mecanisme            text,
  facteurs_modulateurs text,
  repere_quantitatif   text,
  reference_id         integer REFERENCES ref.reference_biblio(id),
  statut_epistemique   text NOT NULL DEFAULT 'publie'
    CHECK (statut_epistemique IN ('publie','publie_source_secondaire','consensus_professionnel','ordre_de_grandeur','hypothese_locale')),
  commentaire          text,
  version_creation     text NOT NULL REFERENCES ref.version_referentiel(numero),
  version_fin          text REFERENCES ref.version_referentiel(numero),
  UNIQUE NULLS NOT DISTINCT (parametre_code, sens, trouble_code, especes, categories)
);
CREATE INDEX ON ref.compatibilite_parametre_trouble (trouble_code);

-- Sens des seuils : une borne seule le donne ; pour les seuils bornés des deux côtés, le sens est celui du
-- seuil voisin plus grave (tableau validé le 15/10/2026).
ALTER TABLE ref.seuil_interpretation ADD COLUMN sens text CHECK (sens IN ('bas','haut'));
UPDATE ref.seuil_interpretation SET sens = 'bas'  WHERE borne_min IS NULL AND borne_max IS NOT NULL;
UPDATE ref.seuil_interpretation SET sens = 'haut' WHERE borne_max IS NULL AND borne_min IS NOT NULL;
UPDATE ref.seuil_interpretation SET sens = 'bas'
 WHERE borne_min IS NOT NULL AND borne_max IS NOT NULL AND (parametre_code, classe) IN (('CU','insuffisant'), ('ZN','insuffisant'));
UPDATE ref.seuil_interpretation SET sens = 'haut'
 WHERE borne_min IS NOT NULL AND borne_max IS NOT NULL
   AND (parametre_code, classe) IN (('FE','contamination_possible'), ('DON','present'), ('ZEA','proche_indicatif'), ('NITRATES','prudence'));
ALTER TABLE ref.seuil_interpretation ALTER COLUMN sens SET NOT NULL;

INSERT INTO app.parametre_defaut (gds_id, cle, valeur, unite, libelle, source, statut_epistemique, valide_du)
VALUES (NULL, 'interpretation.force_min_rehaussement', 2, 'force (1-3)',
        'Force minimale d''un lien paramètre × trouble pour rehausser un niveau 1 en 2 (RC-1.4)',
        'Chapitre 11, § 7 ; décision du 15/10/2026', 'hypothese_locale', DATE '2026-10-15');
SQL);

        (new TroublesSanitairesSeeder)->run();
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DELETE FROM app.parametre_defaut WHERE cle = 'interpretation.force_min_rehaussement';
ALTER TABLE ref.seuil_interpretation DROP COLUMN IF EXISTS sens;
DROP TABLE IF EXISTS ref.compatibilite_parametre_trouble, ref.trouble_sanitaire;
DELETE FROM ref.reference_biblio WHERE date_consultation IS NOT NULL OR commentaire IS NOT NULL;
UPDATE ref.reference_biblio SET statut_verif = 'secondaire' WHERE cle = 'REG2018_1039';
ALTER TABLE ref.reference_biblio DROP COLUMN IF EXISTS date_consultation, DROP COLUMN IF EXISTS commentaire;
DELETE FROM ref.version_referentiel WHERE numero = '2';
SQL);
    }
};
