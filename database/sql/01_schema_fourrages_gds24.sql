-- =====================================================================================
--  GDS 24 — Service « Analyses de fourrages »
--  Modèle de données PostgreSQL 15+ / PostGIS 3
--  Fichier 01 : schémas, nomenclatures, données métier, données géographiques, vues
--  Auteur : GDS 24 (N. Corboz) — version 1.0 — septembre 2026
--  SRID de travail : 2154 (RGF93 / Lambert-93)
-- =====================================================================================

\set ON_ERROR_STOP on

CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS pgcrypto;      -- gen_random_uuid()
CREATE EXTENSION IF NOT EXISTS unaccent;      -- recherche insensible aux accents

CREATE SCHEMA IF NOT EXISTS ref;        -- nomenclatures et référentiels d'interprétation
CREATE SCHEMA IF NOT EXISTS prive;      -- données identifiantes (RGPD) — accès restreint
CREATE SCHEMA IF NOT EXISTS fourrage;   -- données métier : élevages, prélèvements, analyses, interprétations
CREATE SCHEMA IF NOT EXISTS sig;        -- couches géographiques externes (BRGM, RRP, RPG, BDAT, Admin Express)
CREATE SCHEMA IF NOT EXISTS valo;    -- vues analytiques et matérialisées (références locales, contexte pédo-géologique)

COMMENT ON SCHEMA ref      IS 'Nomenclatures (paramètres, types de fourrage, espèces botaniques et animales) et référentiels d''interprétation sourcés.';
COMMENT ON SCHEMA prive    IS 'Identités et coordonnées des élevages et personnes : accès restreint (RGPD). Le reste de la base ne manipule que des identifiants pseudonymes.';
COMMENT ON SCHEMA fourrage IS 'Données métier du service : élevages (pseudonymes), parcelles, prélèvements, analyses, résultats, interprétations, événements sanitaires.';
COMMENT ON SCHEMA sig      IS 'Couches géographiques externes chargées par ogr2ogr/QGIS : géologie BRGM (BD Charm-50), RRP (UCS/UTS), RPG, BDAT, Admin Express, régions naturelles.';
COMMENT ON SCHEMA valo  IS 'Vues de valorisation : résultats au format large, contexte pédo-géologique des prélèvements, références locales (quartiles), alertes.';

-- -------------------------------------------------------------------------------------
-- Fonctions utilitaires
-- -------------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION ref.f_maj_horodatage() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
  NEW.maj_le := now();
  RETURN NEW;
END $$;

-- =====================================================================================
-- 1. NOMENCLATURES (schéma ref)
-- =====================================================================================

CREATE TABLE ref.espece_animale (
  code            text PRIMARY KEY,                  -- 'BOV', 'OVI', 'CAP'
  libelle         text NOT NULL
);
INSERT INTO ref.espece_animale VALUES ('BOV','Bovin'),('OVI','Ovin'),('CAP','Caprin');

CREATE TABLE ref.categorie_animale (
  code            text PRIMARY KEY,                  -- 'VL', 'VA', 'GEN', 'JB', 'BREBIS_LAIT', ...
  espece_code     text NOT NULL REFERENCES ref.espece_animale(code),
  libelle         text NOT NULL,
  systeme         text CHECK (systeme IN ('lait','viande','mixte')),
  ordre           smallint
);
INSERT INTO ref.categorie_animale VALUES
 ('VL','BOV','Vache laitière en lactation','lait',10),
 ('VL_TARIE','BOV','Vache laitière tarie / préparation au vêlage','lait',11),
 ('VA','BOV','Vache allaitante (gestation / lactation)','viande',20),
 ('GEN','BOV','Génisse en croissance','mixte',30),
 ('JB','BOV','Bovin à l''engraissement','viande',40),
 ('VEAU','BOV','Veau < 4 mois (rumen non fonctionnel)','mixte',50),
 ('BREBIS_LAIT','OVI','Brebis laitière','lait',60),
 ('BREBIS_ALL','OVI','Brebis allaitante (fin gestation / lactation)','viande',61),
 ('BREBIS_ENT','OVI','Brebis à l''entretien / milieu de gestation','viande',62),
 ('AGNEAU','OVI','Agneau / agnelle en croissance','mixte',63),
 ('CHEVRE','CAP','Chèvre laitière','lait',70),
 ('CHEVRETTE','CAP','Chevrette en croissance','lait',71),
 ('BOUC_MALE','CAP','Mâles caprins / ovins (risque urolithiase)','mixte',72);

CREATE TABLE ref.famille_fourrage (
  code            text PRIMARY KEY,                  -- 'FOIN','ENRUB','ENSIL_HERBE','ENSIL_MAIS','ENSIL_AUTRE','VERT','PAILLE','CONCENTRE'
  libelle         text NOT NULL,
  type_bulletin_lano text                            -- correspondance avec l'intitulé du bulletin LANO
);
INSERT INTO ref.famille_fourrage VALUES
 ('FOIN','Foin','ANALYSE DE LA VALEUR ALIMENTAIRE DES FOINS'),
 ('ENRUB','Enrubannage','ANALYSE DE LA VALEUR ALIMENTAIRE ENSILAGES/ENRUBANNAGES'),
 ('ENSIL_HERBE','Ensilage d''herbe','ANALYSE DE LA VALEUR ALIMENTAIRE ENSILAGES/ENRUBANNAGES'),
 ('ENSIL_MAIS','Ensilage de maïs','RAPPORT D''ANALYSE VALEUR ALIMENTAIRE D''UN ENSILAGE DE MAÏS'),
 ('ENSIL_AUTRE','Ensilage autre (sorgho, méteil, céréale immature)','VALEUR ALIMENTAIRE DES FOURRAGES DIVERS'),
 ('VERT','Herbe verte (pâture, affouragement en vert)','VALEUR ALIMENTAIRE DES FOURRAGES VERTS'),
 ('PAILLE','Paille et coproduits fibreux','VALEUR ALIMENTAIRE DES FOURRAGES DIVERS'),
 ('CONCENTRE','Aliment concentré / matière première','ANALYSE DE LA VALEUR ALIMENTAIRE D''UN ALIMENT CONCENTRE');

CREATE TABLE ref.type_fourrage (
  code            text PRIMARY KEY,                  -- 'FOIN_PP', 'FOIN_PT', 'FOIN_LUZ', ...
  famille_code    text NOT NULL REFERENCES ref.famille_fourrage(code),
  libelle         text NOT NULL,
  base_botanique  text CHECK (base_botanique IN ('graminees','legumineuses','association','prairie_permanente','mais','sorgho','cereale_immature','melange_cereales_proteagineux','autre')),
  ordre           smallint
);
INSERT INTO ref.type_fourrage VALUES
 ('FOIN_PP','FOIN','Foin de prairie permanente','prairie_permanente',10),
 ('FOIN_PT','FOIN','Foin de prairie temporaire (graminées ± légumineuses)','graminees',11),
 ('FOIN_LUZ','FOIN','Foin de luzerne','legumineuses',12),
 ('FOIN_ASSOC','FOIN','Foin d''association graminée-légumineuse','association',13),
 ('ENRUB_HERBE','ENRUB','Enrubannage d''herbe','graminees',20),
 ('ENRUB_LUZ','ENRUB','Enrubannage de luzerne (± graminée)','legumineuses',21),
 ('ENSIL_HERBE','ENSIL_HERBE','Ensilage d''herbe','graminees',30),
 ('ENSIL_MAIS','ENSIL_MAIS','Ensilage de maïs plante entière','mais',40),
 ('ENSIL_SORGHO','ENSIL_AUTRE','Ensilage de sorgho','sorgho',41),
 ('ENSIL_METEIL','ENSIL_AUTRE','Ensilage / enrubannage de méteil','melange_cereales_proteagineux',42),
 ('CEREALE_IMM','ENSIL_AUTRE','Céréale immature ensilée','cereale_immature',43),
 ('HERBE_VERTE','VERT','Herbe verte','graminees',50),
 ('PAILLE','PAILLE','Paille de céréale','autre',60),
 ('CONCENTRE','CONCENTRE','Aliment concentré / matière première','autre',90);

CREATE TABLE ref.espece_botanique (
  code            text PRIMARY KEY,                  -- 'RGA','RGI','RGH','DACTYLE','FETUQUE','LUZERNE','TB','TV','PN','MAIS','SORGHO','AVOINE','VESCE','POIS'
  libelle         text NOT NULL,
  groupe          text CHECK (groupe IN ('graminee','legumineuse','prairie_naturelle','cereale','proteagineux','autre'))
);
INSERT INTO ref.espece_botanique VALUES
 ('PN','Prairie naturelle / permanente (flore diverse)','prairie_naturelle'),
 ('RGA','Ray-grass anglais','graminee'),('RGI','Ray-grass italien','graminee'),('RGH','Ray-grass hybride','graminee'),
 ('DACTYLE','Dactyle','graminee'),('FETUQUE','Fétuque élevée','graminee'),('BROME','Brome','graminee'),
 ('LUZERNE','Luzerne','legumineuse'),('TB','Trèfle blanc','legumineuse'),('TV','Trèfle violet','legumineuse'),('LOTIER','Lotier','legumineuse'),
 ('MAIS','Maïs','cereale'),('SORGHO','Sorgho','cereale'),('AVOINE','Avoine','cereale'),('TRITICALE','Triticale','cereale'),('ORGE','Orge','cereale'),('BLE','Blé','cereale'),
 ('VESCE','Vesce','proteagineux'),('POIS','Pois fourrager','proteagineux'),('FEVEROLE','Féverole','proteagineux');

CREATE TABLE ref.laboratoire (
  id              serial PRIMARY KEY,
  code            text UNIQUE NOT NULL,              -- 'LANO'
  raison_sociale  text NOT NULL,
  adresse         text,
  accreditations  text,                              -- COFRAC / BIPEA / agrément ministère
  contact         text,
  actif           boolean NOT NULL DEFAULT true
);
INSERT INTO ref.laboratoire (code, raison_sociale, adresse, accreditations)
VALUES ('LANO','LILANO — Laboratoire agronomique de Normandie','CS 15208 — 50008 Saint-Lô Cedex','Agréé Ministère de l''Agriculture ; adhérent GEMAS ; laboratoire du CRIEL Normandie Lait');

CREATE TABLE ref.famille_parametre (
  code            text PRIMARY KEY,
  libelle         text NOT NULL,
  ordre           smallint
);
INSERT INTO ref.famille_parametre VALUES
 ('CONSTITUTIF','Analyses constitutives principales',10),
 ('PAROIS','Parois cellulaires / fibres',20),
 ('MINERAL_MAJEUR','Éléments minéraux majeurs',30),
 ('OLIGO','Oligo-éléments',40),
 ('INRA2007','Valeur alimentaire calculée — INRA 2007',50),
 ('INRA2018','Valeur alimentaire calculée — INRA 2018 / Systali',60),
 ('CONSERVATION','Qualité de conservation (pH, NH3, AGV, spores…)',70),
 ('MYCOTOXINE','Mycotoxines',80),
 ('CONTAMINANT','Autres contaminants (nitrates, métaux lourds…)',90),
 ('DERIVE','Indicateur dérivé calculé par le GDS',99);

CREATE TABLE ref.parametre (
  code            text PRIMARY KEY,                  -- 'MS','MM','MAT',...,'UFL_2018','BPR','DON'
  libelle         text NOT NULL,
  famille_code    text NOT NULL REFERENCES ref.famille_parametre(code),
  unite           text NOT NULL,                     -- '% brut','g/kg MS','mg/kg MS','UFL/kg MS','mEq/kg MS','µg/kg sec'...
  base            text NOT NULL DEFAULT 'MS' CHECK (base IN ('MS','brut','sec','sans_objet')),
  decimales       smallint NOT NULL DEFAULT 1,
  systeme_inra    text CHECK (systeme_inra IN ('2007','2018')),
  definition      text,
  ordre           smallint
);
INSERT INTO ref.parametre (code, libelle, famille_code, unite, base, decimales, systeme_inra, definition, ordre) VALUES
 ('MS','Matière sèche','CONSTITUTIF','% brut','brut',1,NULL,'Teneur en matière sèche du produit brut ; base d''expression des autres valeurs.',1),
 ('MM','Matières minérales (cendres)','CONSTITUTIF','g/kg MS','MS',0,NULL,'Minéraux totaux ; un excès révèle une contamination par la terre.',2),
 ('MO','Matières organiques','CONSTITUTIF','g/kg MS','MS',0,NULL,'1000 − MM.',3),
 ('MAT','Matières azotées totales','CONSTITUTIF','g/kg MS','MS',0,NULL,'N × 6,25.',4),
 ('CB','Cellulose brute','CONSTITUTIF','g/kg MS','MS',0,NULL,'Méthode de Weende.',5),
 ('DCS','Digestibilité pepsine-cellulase','CONSTITUTIF','%','MS',1,NULL,'Solubilité enzymatique (Aufrère) prédisant la dMO.',6),
 ('SSR','Sucres solubles résiduels','CONSTITUTIF','%','MS',1,NULL,'Glucides solubles après conservation.',7),
 ('AMI','Amidon','CONSTITUTIF','g/kg MS','MS',0,NULL,'Amidon total.',8),
 ('MGT','Matières grasses totales','CONSTITUTIF','g/kg MS','MS',1,NULL,NULL,9),
 ('PH','pH','CONSERVATION','-','sans_objet',2,NULL,'pH du fourrage humide (conservation).',10),
 ('NDF','Parois totales (NDF)','PAROIS','g/kg MS','MS',0,NULL,'Van Soest.',11),
 ('ADF','Lignocellulose (ADF)','PAROIS','g/kg MS','MS',0,NULL,NULL,12),
 ('ADL','Lignine (ADL)','PAROIS','g/kg MS','MS',0,NULL,NULL,13),
 ('P','Phosphore total','MINERAL_MAJEUR','g/kg MS','MS',1,NULL,NULL,20),
 ('CA','Calcium total','MINERAL_MAJEUR','g/kg MS','MS',1,NULL,NULL,21),
 ('MG','Magnésium total','MINERAL_MAJEUR','g/kg MS','MS',1,NULL,NULL,22),
 ('K','Potassium total','MINERAL_MAJEUR','g/kg MS','MS',1,NULL,NULL,23),
 ('NA','Sodium total','MINERAL_MAJEUR','g/kg MS','MS',1,NULL,NULL,24),
 ('S','Soufre total','MINERAL_MAJEUR','g/kg MS','MS',1,NULL,'Nécessaire au calcul complet de la BACA et du rapport Cu:Mo:S.',25),
 ('CL','Chlore','MINERAL_MAJEUR','g/kg MS','MS',1,NULL,'Nécessaire au BE et à la BACA.',26),
 ('CU','Cuivre total','OLIGO','mg/kg MS','MS',1,NULL,NULL,30),
 ('ZN','Zinc total','OLIGO','mg/kg MS','MS',1,NULL,NULL,31),
 ('MN','Manganèse total','OLIGO','mg/kg MS','MS',1,NULL,NULL,32),
 ('FE','Fer total','OLIGO','mg/kg MS','MS',1,NULL,'Indicateur de contamination tellurique au-delà de 300-500 mg/kg MS.',33),
 ('AL','Aluminium total','OLIGO','mg/kg MS','MS',1,NULL,'Indicateur de contamination tellurique.',34),
 ('SE','Sélénium total','OLIGO','mg/kg MS','MS',3,NULL,'Hors forfait LANO standard — option ICP-MS à négocier.',35),
 ('I','Iode total','OLIGO','mg/kg MS','MS',2,NULL,'Hors forfait standard.',36),
 ('CO','Cobalt total','OLIGO','mg/kg MS','MS',3,NULL,'Hors forfait standard.',37),
 ('MO_OLIGO','Molybdène total','OLIGO','mg/kg MS','MS',2,NULL,'Antagoniste du cuivre (thiomolybdates) ; hors forfait standard.',38),
 ('DMO','Digestibilité de la matière organique','INRA2007','%','MS',1,'2007',NULL,40),
 ('UFL','UFL (2007)','INRA2007','UFL/kg MS','MS',2,'2007',NULL,41),
 ('UFV','UFV (2007)','INRA2007','UFV/kg MS','MS',2,'2007',NULL,42),
 ('PDIN','PDIN','INRA2007','g/kg MS','MS',0,'2007',NULL,43),
 ('PDIE','PDIE','INRA2007','g/kg MS','MS',0,'2007',NULL,44),
 ('PDIA','PDIA (2007)','INRA2007','g/kg MS','MS',0,'2007',NULL,45),
 ('UEL','Unités d''encombrement lait','INRA2007','UEL/kg MS','MS',2,'2007',NULL,46),
 ('UEB','Unités d''encombrement bovins','INRA2007','UEB/kg MS','MS',2,'2007',NULL,47),
 ('UEM','Unités d''encombrement moutons','INRA2007','UEM/kg MS','MS',2,'2007',NULL,48),
 ('DNDF','Digestibilité des parois (dNDF)','INRA2007','%','MS',1,'2007',NULL,49),
 ('DMONA','Digestibilité de la MO non amidon','INRA2007','%','MS',1,'2007','Maïs.',50),
 ('AMIDR','Amidon dégradable dans le rumen (2007/2016)','INRA2007','g/kg MS','MS',0,'2007','Maïs.',51),
 ('P_ABS','Phosphore absorbable','INRA2007','g/kg MS','MS',1,'2007',NULL,52),
 ('CA_ABS','Calcium absorbable','INRA2007','g/kg MS','MS',1,'2007',NULL,53),
 ('BACA','Balance anions-cations','INRA2007','mEq/kg MS','MS',0,NULL,'(Na+K)−(Cl+S) ; approximative si S et Cl non dosés.',54),
 ('BE','Bilan électrolytique','INRA2007','mEq/kg MS','MS',0,NULL,'Na+K−Cl.',55),
 ('UFL_2018','UFL (2018)','INRA2018','UFL/kg MS','MS',2,'2018','Valeur table au niveau d''ingestion de référence.',60),
 ('UFV_2018','UFV (2018)','INRA2018','UFV/kg MS','MS',2,'2018',NULL,61),
 ('PDI','PDI (2018)','INRA2018','g/kg MS','MS',0,'2018',NULL,62),
 ('PDIA_2018','PDIA (2018)','INRA2018','g/kg MS','MS',0,'2018',NULL,63),
 ('BPR','Balance protéique ruminale','INRA2018','g/kg MS','MS',0,'2018','Cible ration = 0.',64),
 ('DT6_N','Dégradabilité théorique de l''azote (6 %/h)','INRA2018','%','MS',1,'2018',NULL,65),
 ('DT6_AMI','Dégradabilité de l''amidon (6 %/h)','INRA2018','%','MS',1,'2018','Maïs.',66),
 ('AMIDR_2018','Amidon dégradable dans le rumen (2018)','INRA2018','g/kg MS','MS',0,'2018','Maïs.',67),
 ('AMI_BYPASS','Amidon by-pass','INRA2018','g/kg MS','MS',0,'2018','Maïs.',68),
 ('NI','Niveau d''ingestion de référence','INRA2018','% PV','sans_objet',2,'2018',NULL,69),
 ('EB','Énergie brute','INRA2018','kcal/kg MS','MS',0,'2018',NULL,70),
 ('NH3_NTOT','N-NH3 / N total','CONSERVATION','%','MS',1,NULL,'Protéolyse ; < 5 % excellent, > 15 % mauvais.',80),
 ('AC_LACT','Acide lactique','CONSERVATION','% MS','MS',2,NULL,NULL,81),
 ('AC_ACET','Acide acétique','CONSERVATION','% MS','MS',2,NULL,NULL,82),
 ('AC_BUTYR','Acide butyrique','CONSERVATION','g/kg MS','MS',2,NULL,'> 5 g/kg MS = ensilage butyrique.',83),
 ('SPORES_BUT','Spores butyriques','CONSERVATION','spores/g','brut',0,NULL,NULL,84),
 ('DON','Déoxynivalénol','MYCOTOXINE','µg/kg sec','sec',0,NULL,'ELISA Neogen Veratox (kit validé céréales).',90),
 ('ZEA','Zéaralénone','MYCOTOXINE','µg/kg sec','sec',0,NULL,NULL,91),
 ('FUM','Fumonisines B1+B2','MYCOTOXINE','µg/kg sec','sec',0,NULL,NULL,92),
 ('AFB1','Aflatoxine B1','MYCOTOXINE','µg/kg','brut',1,NULL,'Teneur maximale réglementaire (Dir. 2002/32/CE).',93),
 ('OTA','Ochratoxine A','MYCOTOXINE','µg/kg','brut',1,NULL,NULL,94),
 ('NITRATES','Nitrates (NO3)','CONTAMINANT','mg/kg MS','MS',0,NULL,NULL,95),
 ('K_CAMG_EQ','Rapport K/(Ca+Mg) en équivalents','DERIVE','ratio','sans_objet',2,NULL,'Kemp & ''t Hart 1957 : > 2,2 = risque de tétanie d''herbage.',100),
 ('CA_P','Rapport Ca/P','DERIVE','ratio','sans_objet',2,NULL,NULL,101),
 ('CU_MO','Rapport Cu/Mo','DERIVE','ratio','sans_objet',1,NULL,'Toléré 10:1, idéal 6:1 (ovins).',102),
 ('PDI_UFL','Rapport PDI/UFL','DERIVE','g/UFL','sans_objet',0,NULL,'Équilibre azote/énergie (≈ 90-100).',103);

CREATE TABLE ref.methode_analyse (
  id              serial PRIMARY KEY,
  code            text UNIQUE NOT NULL,              -- 'NIRS_GEMBLOUX','KJELDAHL_ISO5983','ICP_AES','AUFRERE','ELISA_VERATOX','INRA2007','INRA2018'
  libelle         text NOT NULL,
  norme           text,
  nature          text CHECK (nature IN ('chimie_reference','NIRS','enzymatique','immuno_enzymatique','chromatographie','calcul','microbiologie'))
);
INSERT INTO ref.methode_analyse (code, libelle, norme, nature) VALUES
 ('MS_ETUVE','Séchage 48 h à 80 °C','INRA/BIPEA EC 77/M8506','chimie_reference'),
 ('MM_INCINERATION','Incinération','NF V18-101','chimie_reference'),
 ('KJELDAHL','Azote Kjeldahl','NF ISO 5983-1','chimie_reference'),
 ('WEENDE','Cellulose brute Weende','NF V03-040','chimie_reference'),
 ('VAN_SOEST','Parois NDF/ADF/ADL','NF V18-122','chimie_reference'),
 ('NIRS_GEMBLOUX_LANO','Proche infrarouge — calibrages Université de Gembloux / LANO',NULL,'NIRS'),
 ('AUFRERE','Digestibilité pepsine-cellulase','Méthode Aufrère (INRA)','enzymatique'),
 ('AMIDON_EWERS','Amidon polarimétrique','NF ISO 6493','chimie_reference'),
 ('ICP_AES','Minéralisation + ICP-AES','NF EN 15510 ; NF EN ISO 11885','chimie_reference'),
 ('ELISA_VERATOX','ELISA Neogen Veratox',NULL,'immuno_enzymatique'),
 ('LCMSMS','Chromatographie liquide couplée spectrométrie de masse',NULL,'chromatographie'),
 ('CALC_INRA2007','Équations INRA 2007','INRA 2007','calcul'),
 ('CALC_INRA2018','Équations INRA 2018 / Systali','INRA 2018','calcul'),
 ('CALC_GDS','Indicateur dérivé calculé par le GDS',NULL,'calcul');

-- -------------------------------------------------------------------------------------
-- Références bibliographiques et seuils d'interprétation (sourcés, statut épistémique)
-- -------------------------------------------------------------------------------------
CREATE TABLE ref.reference_biblio (
  id              serial PRIMARY KEY,
  cle             text UNIQUE NOT NULL,              -- 'INRA2018', 'MESCHY2007', 'RECO2006_576', ...
  auteurs         text,
  annee           smallint,
  titre           text NOT NULL,
  source          text,                              -- revue / éditeur / institution
  url             text,
  type_source     text CHECK (type_source IN ('ouvrage','article','reglementation','note_technique','manuel','table','site','these')),
  statut_verif    text NOT NULL DEFAULT 'verifie' CHECK (statut_verif IN ('verifie','secondaire','non_verifie'))
);
COMMENT ON TABLE ref.reference_biblio IS 'Bibliographie de la base de connaissance. statut_verif : verifie = consulté en ligne ; secondaire = repris via une source secondaire ; non_verifie = référence classique inaccessible.';

CREATE TABLE ref.seuil_interpretation (
  id              serial PRIMARY KEY,
  parametre_code  text NOT NULL REFERENCES ref.parametre(code),
  famille_fourrage_code text REFERENCES ref.famille_fourrage(code),   -- NULL = tout fourrage
  type_fourrage_code    text REFERENCES ref.type_fourrage(code),      -- NULL = toute la famille
  espece_code     text REFERENCES ref.espece_animale(code),            -- NULL = toutes espèces
  categorie_code  text REFERENCES ref.categorie_animale(code),         -- NULL = toutes catégories
  nature          text NOT NULL CHECK (nature IN ('nutritionnel','sanitaire','reglementaire','statistique_local')),
  borne_min       numeric,                             -- intervalle [borne_min ; borne_max[ définissant la classe
  borne_max       numeric,
  classe          text NOT NULL,                       -- 'faible','correct','bon','alerte','carence_probable','toxicite','max_reglementaire','Q1','Q3'...
  gravite         smallint CHECK (gravite BETWEEN 0 AND 3),   -- 0 info, 1 vigilance, 2 alerte, 3 critique
  message         text,                                -- texte proposé au technicien
  reference_id    integer REFERENCES ref.reference_biblio(id),
  statut_epistemique text NOT NULL DEFAULT 'publie' CHECK (statut_epistemique IN ('publie','publie_source_secondaire','consensus_professionnel','ordre_de_grandeur','hypothese_locale')),
  actif           boolean NOT NULL DEFAULT true,
  commentaire     text
);
COMMENT ON TABLE ref.seuil_interpretation IS 'Grille d''interprétation paramétrable. Une valeur est classée par le premier seuil actif dont l''intervalle la contient, du plus spécifique (type × espèce × catégorie) au plus général.';
CREATE INDEX ON ref.seuil_interpretation (parametre_code, actif);

CREATE TABLE ref.besoin_animal (
  id              serial PRIMARY KEY,
  categorie_code  text NOT NULL REFERENCES ref.categorie_animale(code),
  stade           text,                                -- 'entretien','fin_gestation','debut_lactation','croissance_700g'...
  poids_vif_kg    numeric,
  production      text,                                -- '30 kg lait TB40', '2 agneaux'...
  parametre_code  text NOT NULL REFERENCES ref.parametre(code),
  valeur          numeric NOT NULL,
  unite           text NOT NULL,                       -- 'par jour' ou 'par kg MS de ration'
  reference_id    integer REFERENCES ref.reference_biblio(id),
  statut_epistemique text NOT NULL DEFAULT 'publie',
  commentaire     text
);
COMMENT ON TABLE ref.besoin_animal IS 'Besoins / apports recommandés par catégorie animale (INRA 2018, NASEM 2021, NRC 2007…), utilisés pour situer un fourrage par rapport à l''animal qui le consomme.';

-- =====================================================================================
-- 2. DONNÉES IDENTIFIANTES (schéma prive — RGPD)
-- =====================================================================================
CREATE TABLE prive.elevage_identite (
  elevage_id      uuid PRIMARY KEY,                    -- = fourrage.elevage.id
  num_cheptel_ede text UNIQUE,                         -- FR + 8 chiffres (numéro EDE)
  siret           text,
  raison_sociale  text NOT NULL,
  nom_contact     text,
  adresse         text,
  code_postal     text,
  telephone       text,
  courriel        text,
  num_client_labo text,                                -- n° client LANO
  consentement_donnees boolean NOT NULL DEFAULT false, -- accord pour l''agrégation anonyme et le partage (charte Data-Agri)
  consentement_date date,
  consentement_partage_veto boolean NOT NULL DEFAULT false,
  cree_le         timestamptz NOT NULL DEFAULT now(),
  maj_le          timestamptz NOT NULL DEFAULT now()
);
COMMENT ON TABLE prive.elevage_identite IS 'Identité et coordonnées de l''élevage. Droits d''accès limités aux agents habilités ; les vues analytiques n''y accèdent jamais.';

CREATE TABLE prive.personne (
  id              serial PRIMARY KEY,
  nom             text NOT NULL,
  prenom          text,
  structure       text,                                -- 'GDS 24', 'GDS 47', 'Cabinet vétérinaire X', 'Chambre d''agriculture'
  role            text NOT NULL CHECK (role IN ('technicien_gds','veterinaire','conseiller_partenaire','administrateur','eleveur')),
  courriel        text,
  actif           boolean NOT NULL DEFAULT true
);

-- =====================================================================================
-- 3. DONNÉES MÉTIER (schéma fourrage)
-- =====================================================================================
CREATE TABLE fourrage.elevage (
  id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  code_pseudo     text UNIQUE NOT NULL,                -- ex. 'ELV-0001' : identifiant affiché dans les restitutions anonymisées
  departement     char(2) NOT NULL CHECK (departement IN ('24','47')),
  code_insee_commune char(5),                          -- commune du siège (jointure sig.commune)
  geom_siege      geometry(Point, 2154),               -- géocodage BAN du siège ; jamais diffusé en clair
  especes         text[] NOT NULL DEFAULT '{}',        -- {'BOV','OVI'}
  systeme_bovin   text CHECK (systeme_bovin IN ('lait','viande','mixte')),
  systeme_ovin    text CHECK (systeme_ovin IN ('lait','viande','mixte')),
  systeme_caprin  text CHECK (systeme_caprin IN ('lait','viande','mixte')),
  effectif_bovins integer, effectif_ovins integer, effectif_caprins integer,
  adherent_gds    boolean NOT NULL DEFAULT true,
  tiers_facture   text,                                -- 'GDS DORDOGNE' ou l''élevage
  commentaire     text,
  cree_le         timestamptz NOT NULL DEFAULT now(),
  maj_le          timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX ON fourrage.elevage USING GIST (geom_siege);
CREATE INDEX ON fourrage.elevage (code_insee_commune);
CREATE TRIGGER trg_elevage_maj BEFORE UPDATE ON fourrage.elevage FOR EACH ROW EXECUTE FUNCTION ref.f_maj_horodatage();
COMMENT ON TABLE fourrage.elevage IS 'Élevage pseudonymisé : aucune donnée nominative ici (voir prive.elevage_identite). Le siège géocodé sert aux jointures spatiales, pas à la diffusion.';

CREATE TABLE fourrage.parcelle (
  id              serial PRIMARY KEY,
  elevage_id      uuid NOT NULL REFERENCES fourrage.elevage(id) ON DELETE CASCADE,
  nom             text NOT NULL,                       -- nom d''usage ('Pré du Moulin')
  id_parcelle_rpg text,                                -- ID_PARCEL du RPG (millésime dans rpg_millesime)
  rpg_millesime   smallint,
  code_culture_rpg char(3),                            -- PPH, PTR, PRL, LUZ, MIE, MLG...
  surface_ha      numeric(8,2),
  annee_semis     smallint,
  type_prairie    text CHECK (type_prairie IN ('permanente','temporaire','luzerne','culture_annuelle','autre')),
  geom            geometry(MultiPolygon, 2154),
  centroide       geometry(Point, 2154) GENERATED ALWAYS AS (ST_Centroid(geom)) STORED,
  commentaire     text,
  cree_le         timestamptz NOT NULL DEFAULT now(),
  maj_le          timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX ON fourrage.parcelle USING GIST (geom);
CREATE INDEX ON fourrage.parcelle (elevage_id);
CREATE TRIGGER trg_parcelle_maj BEFORE UPDATE ON fourrage.parcelle FOR EACH ROW EXECUTE FUNCTION ref.f_maj_horodatage();

CREATE TABLE fourrage.campagne (
  code            text PRIMARY KEY,                    -- '2025-2026'
  libelle         text NOT NULL,
  date_debut      date NOT NULL,
  date_fin        date NOT NULL
);
INSERT INTO fourrage.campagne VALUES ('2025-2026','Campagne 2025-2026 (récoltes 2025, analyses déc. 2025 → août 2026)','2025-09-01','2026-08-31');

CREATE TABLE fourrage.prelevement (
  id              serial PRIMARY KEY,
  code            text UNIQUE,                         -- code interne GDS ('PRL-2026-0012')
  elevage_id      uuid NOT NULL REFERENCES fourrage.elevage(id),
  parcelle_id     integer REFERENCES fourrage.parcelle(id),
  campagne_code   text REFERENCES fourrage.campagne(code),
  date_prelevement date NOT NULL,
  preleveur_id    integer REFERENCES prive.personne(id),
  lieu            text CHECK (lieu IN ('parcelle','stock_batiment','silo','front_attaque','auge','autre')),
  geom            geometry(Point, 2154),               -- point GPS du prélèvement (parcelle prélevée ou lieu de stockage)
  type_fourrage_code text NOT NULL REFERENCES ref.type_fourrage(code),
  nom_lot         text,                                -- nom donné par l''éleveur ('FOIN GENISSES', 'ENSILAGE MAIS 1')
  date_recolte    date,
  cycle_coupe     text CHECK (cycle_coupe IN ('1er cycle','2e cycle','3e cycle et +','inconnu')),
  stade_recolte   text,                                -- 'début épiaison', 'floraison'...
  mode_conservation text CHECK (mode_conservation IN ('foin_sec','foin_ventile','enrubanne','ensile_prefane','ensile_direct','vert','deshydrate','grain','autre')),
  mode_sechage    text,                                -- 'Fané au sol / beau temps', '< 10 jours'...
  conservateur    text,
  date_ouverture_silo date,
  etat_conservation_visuel text CHECK (etat_conservation_visuel IN ('bon','moisissures_localisees','moisissures_etendues','echauffe','odeur_butyrique','terre_visible','autre')),
  destination_categorie_code text REFERENCES ref.categorie_animale(code),  -- animaux destinataires du lot
  motif          text NOT NULL DEFAULT 'routine' CHECK (motif IN ('routine','suivi_pluriannuel','suspicion_sanitaire','post_incident','demande_veterinaire','autre')),
  motif_detail    text,                                -- ex. 'veaux faibles, myopathie', 'non-délivrances'
  nb_points_prelevement smallint,
  conditionnement text,                                -- 'sac hermétique sous vide, +4 °C'
  commentaire     text,
  cree_le         timestamptz NOT NULL DEFAULT now(),
  maj_le          timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX ON fourrage.prelevement USING GIST (geom);
CREATE INDEX ON fourrage.prelevement (elevage_id, date_prelevement);
CREATE INDEX ON fourrage.prelevement (type_fourrage_code);
CREATE TRIGGER trg_prelevement_maj BEFORE UPDATE ON fourrage.prelevement FOR EACH ROW EXECUTE FUNCTION ref.f_maj_horodatage();
COMMENT ON TABLE fourrage.prelevement IS 'Un lot de fourrage prélevé : contexte agronomique (parcelle, récolte, conservation), contexte zootechnique (animaux destinataires) et motif de la demande (routine ou question sanitaire).';

CREATE TABLE fourrage.prelevement_espece (
  prelevement_id  integer NOT NULL REFERENCES fourrage.prelevement(id) ON DELETE CASCADE,
  espece_bot_code text NOT NULL REFERENCES ref.espece_botanique(code),
  part_pct        numeric(5,1) CHECK (part_pct BETWEEN 0 AND 100),
  PRIMARY KEY (prelevement_id, espece_bot_code)
);
COMMENT ON TABLE fourrage.prelevement_espece IS 'Composition botanique déclarée du lot (ex. Dactyle 50 % / Luzerne 50 %).';

CREATE TABLE fourrage.analyse (
  id              serial PRIMARY KEY,
  prelevement_id  integer NOT NULL REFERENCES fourrage.prelevement(id) ON DELETE CASCADE,
  laboratoire_id  integer NOT NULL REFERENCES ref.laboratoire(id),
  num_echantillon_labo text NOT NULL,                  -- 'HA25-41524'
  code_dossier_labo text,
  type_bulletin   text,                                -- intitulé exact du bulletin
  forfait         text,                                -- 'valeur alimentaire + minéraux', 'mycotoxines'...
  date_reception  date,
  date_edition    date,
  systeme_calcul  text[] DEFAULT '{2007,2018}',        -- systèmes INRA présents sur le bulletin
  fichier_pdf     text,                                -- chemin relatif dans la GED / partage
  hash_pdf        text,                                -- empreinte SHA-256 du PDF (traçabilité)
  statut          text NOT NULL DEFAULT 'recu' CHECK (statut IN ('attendu','recu','controle','valide','anomalie','annule')),
  anomalie_detail text,
  importe_le      timestamptz NOT NULL DEFAULT now(),
  UNIQUE (laboratoire_id, num_echantillon_labo)
);
CREATE INDEX ON fourrage.analyse (prelevement_id);
COMMENT ON TABLE fourrage.analyse IS 'Un bulletin de laboratoire. Plusieurs bulletins peuvent porter sur le même prélèvement (valeur alimentaire + mycotoxines).';

CREATE TABLE fourrage.resultat (
  analyse_id      integer NOT NULL REFERENCES fourrage.analyse(id) ON DELETE CASCADE,
  parametre_code  text NOT NULL REFERENCES ref.parametre(code),
  valeur          numeric,                             -- valeur numérique (NULL si non dosé)
  censure         char(1) CHECK (censure IN ('<','>')),-- '<' : inférieur à la limite de quantification
  unite           text NOT NULL,
  methode_id      integer REFERENCES ref.methode_analyse(id),
  origine         text NOT NULL DEFAULT 'labo' CHECK (origine IN ('labo','calcul_gds','saisie_manuelle')),
  flag_anomalie   boolean NOT NULL DEFAULT false,
  commentaire     text,
  PRIMARY KEY (analyse_id, parametre_code)
);
COMMENT ON TABLE fourrage.resultat IS 'Résultats au format long (une ligne par paramètre) : supporte des panels analytiques variables selon le bulletin et l''ajout de nouveaux paramètres sans modifier la structure.';

-- Interprétation et alertes
CREATE TABLE fourrage.interpretation (
  id              serial PRIMARY KEY,
  analyse_id      integer NOT NULL REFERENCES fourrage.analyse(id) ON DELETE CASCADE,
  version         smallint NOT NULL DEFAULT 1,
  date_interpretation date NOT NULL DEFAULT current_date,
  technicien_id   integer REFERENCES prive.personne(id),
  veterinaire_id  integer REFERENCES prive.personne(id),          -- relecture / co-signature éventuelle
  categorie_cible_code text REFERENCES ref.categorie_animale(code),
  niveau_global   smallint CHECK (niveau_global BETWEEN 0 AND 3),  -- 0 RAS, 1 vigilance, 2 alerte, 3 critique
  synthese        text,                                -- 3-5 lignes pour l''éleveur
  lecture_nutritionnelle text,
  lecture_sanitaire text,
  recommandations text,
  orientation_veterinaire boolean NOT NULL DEFAULT false, -- renvoi vers le vétérinaire traitant (profil métabolique, prescription)
  statut          text NOT NULL DEFAULT 'brouillon' CHECK (statut IN ('brouillon','valide','envoye','archive')),
  envoye_le       timestamptz,
  mode_restitution text[] DEFAULT '{}',                 -- {'fiche_pdf','visite','telephone','reunion_collective'}
  cree_le         timestamptz NOT NULL DEFAULT now(),
  maj_le          timestamptz NOT NULL DEFAULT now(),
  UNIQUE (analyse_id, version)
);
CREATE TRIGGER trg_interpretation_maj BEFORE UPDATE ON fourrage.interpretation FOR EACH ROW EXECUTE FUNCTION ref.f_maj_horodatage();

CREATE TABLE fourrage.alerte (
  id              serial PRIMARY KEY,
  interpretation_id integer NOT NULL REFERENCES fourrage.interpretation(id) ON DELETE CASCADE,
  parametre_code  text REFERENCES ref.parametre(code),
  seuil_id        integer REFERENCES ref.seuil_interpretation(id),
  valeur          numeric,
  classe          text NOT NULL,
  gravite         smallint NOT NULL CHECK (gravite BETWEEN 0 AND 3),
  message         text NOT NULL,
  origine         text NOT NULL DEFAULT 'automatique' CHECK (origine IN ('automatique','technicien')),
  retenue         boolean NOT NULL DEFAULT true         -- le technicien peut écarter une alerte automatique (avec motif)
, motif_ecart     text
);
CREATE INDEX ON fourrage.alerte (interpretation_id);

-- Lien avec la santé du troupeau (angle GDS)
CREATE TABLE fourrage.evenement_sanitaire (
  id              serial PRIMARY KEY,
  elevage_id      uuid NOT NULL REFERENCES fourrage.elevage(id) ON DELETE CASCADE,
  espece_code     text NOT NULL REFERENCES ref.espece_animale(code),
  categorie_code  text REFERENCES ref.categorie_animale(code),
  date_evenement  date NOT NULL,
  type_evenement  text NOT NULL,                       -- 'myopathie_jeunes','retention_placentaire','hypocalcemie','tetanie','avortement','listeriose','acidose','boiteries','diarrhee_veaux','baisse_production','urolithiase','toxicite_cuivre','autre'
  nb_animaux      integer,
  gravite         smallint CHECK (gravite BETWEEN 1 AND 3),
  source          text CHECK (source IN ('eleveur','veterinaire','gds','laboratoire')),
  description     text,
  prelevement_id  integer REFERENCES fourrage.prelevement(id),   -- lot suspecté, le cas échéant
  cree_le         timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX ON fourrage.evenement_sanitaire (elevage_id, date_evenement);
COMMENT ON TABLE fourrage.evenement_sanitaire IS 'Troubles observés dans le troupeau (déclarés par l''éleveur ou le vétérinaire) pour croiser fourrage et santé : c''est la valeur ajoutée spécifique du GDS.';

CREATE TABLE fourrage.analyse_animale (
  id              serial PRIMARY KEY,
  elevage_id      uuid NOT NULL REFERENCES fourrage.elevage(id) ON DELETE CASCADE,
  date_prelevement date NOT NULL,
  espece_code     text NOT NULL REFERENCES ref.espece_animale(code),
  categorie_code  text REFERENCES ref.categorie_animale(code),
  nb_animaux      smallint,
  matrice         text NOT NULL CHECK (matrice IN ('sang','serum','plasma','foie','urine','lait','poils')),
  parametre       text NOT NULL,                       -- 'cupremie','zincemie','selenium','GSH-Px','iodurie','B12','Ca','Mg','uree','BHB'
  valeur_moyenne  numeric,
  valeur_min      numeric,
  valeur_max      numeric,
  unite           text NOT NULL,
  interpretation_labo text,                            -- 'déficient','marginal','adéquat'
  prescripteur_id integer REFERENCES prive.personne(id),
  laboratoire_id  integer REFERENCES ref.laboratoire(id),
  commentaire     text
);
COMMENT ON TABLE fourrage.analyse_animale IS 'Profils métaboliques / bilans oligo-éléments prescrits par le vétérinaire : permet de confronter apport (fourrage) et statut (animal).';

-- Rationnement (simplifié) : ce que mange le lot
CREATE TABLE fourrage.lot_animaux (
  id              serial PRIMARY KEY,
  elevage_id      uuid NOT NULL REFERENCES fourrage.elevage(id) ON DELETE CASCADE,
  libelle         text NOT NULL,                       -- 'VL en lactation', 'Génisses 1 an', 'Brebis fin gestation'
  categorie_code  text NOT NULL REFERENCES ref.categorie_animale(code),
  effectif        integer,
  poids_vif_moyen numeric,
  production      text
);
CREATE TABLE fourrage.ration (
  id              serial PRIMARY KEY,
  lot_id          integer NOT NULL REFERENCES fourrage.lot_animaux(id) ON DELETE CASCADE,
  date_debut      date NOT NULL,
  date_fin        date,
  saisie_par_id   integer REFERENCES prive.personne(id),
  commentaire     text
);
CREATE TABLE fourrage.ration_composant (
  ration_id       integer NOT NULL REFERENCES fourrage.ration(id) ON DELETE CASCADE,
  ordre           smallint NOT NULL,
  prelevement_id  integer REFERENCES fourrage.prelevement(id),   -- fourrage analysé…
  aliment_libre   text,                                          -- …ou aliment non analysé (concentré du commerce, AMV)
  kg_ms_par_animal_jour numeric NOT NULL,
  PRIMARY KEY (ration_id, ordre),
  CHECK (prelevement_id IS NOT NULL OR aliment_libre IS NOT NULL)
);

-- =====================================================================================
-- 4. COUCHES GÉOGRAPHIQUES EXTERNES (schéma sig)
--    Chargées par ogr2ogr / QGIS ; structure minimale, attributs source conservés en jsonb.
-- =====================================================================================
CREATE TABLE sig.commune (                             -- Admin Express COG (IGN)
  code_insee      char(5) PRIMARY KEY,
  nom             text NOT NULL,
  departement     char(2) NOT NULL,
  code_epci       text,
  code_canton     text,                                -- pour la jointure BDAT (statistiques cantonales)
  geom            geometry(MultiPolygon, 2154) NOT NULL
);
CREATE INDEX ON sig.commune USING GIST (geom);

CREATE TABLE sig.region_naturelle (                    -- zonage GDS : Périgord vert, blanc, noir, pourpre, Double/Landais, vallées… ; Pays de Serres, Terreforts, Boulbènes…
  id              serial PRIMARY KEY,
  departement     char(2) NOT NULL,
  code            text UNIQUE NOT NULL,
  libelle         text NOT NULL,
  substrat_dominant text,                              -- 'socle cristallin', 'calcaire jurassique', 'calcaire crétacé', 'molasse', 'sables et argiles tertiaires', 'alluvions'
  sols_dominants  text,
  risques_mineraux_attendus text,                      -- hypothèses de travail (à tester) : 'Se, Cu, Co bas ; Mo bas ; Mn, Fe élevés'
  geom            geometry(MultiPolygon, 2154) NOT NULL
);
CREATE INDEX ON sig.region_naturelle USING GIST (geom);

CREATE TABLE sig.geol_formation (                      -- BRGM BD Charm-50, couche S_FGEOL, départements 24 et 47
  gid             serial PRIMARY KEY,
  departement     char(2) NOT NULL,
  notation        text,
  descr           text,
  age_deb         text, age_fin        text,
  era             text, systeme        text,
  lithologie      text,
  type_geol       text,
  classe_litho_gds text,                               -- reclassement harmonisé 24/47 (table sig.classe_litho)
  attributs       jsonb,                               -- attributs source complets
  geom            geometry(MultiPolygon, 2154) NOT NULL
);
CREATE INDEX ON sig.geol_formation USING GIST (geom);
CREATE INDEX ON sig.geol_formation (classe_litho_gds);

CREATE TABLE sig.classe_litho (                        -- table de correspondance NOTATION → classe harmonisée
  code            text PRIMARY KEY,                    -- 'SOCLE_ACIDE','CALC_JURA','CALC_CRETACE','MOLASSE','SABLE_ARGILE_TERT','ALLUVIONS','COLLUVIONS','AUTRE'
  libelle         text NOT NULL,
  ph_attendu      text,                                -- 'acide','neutre','calcaire'
  commentaire     text
);
INSERT INTO sig.classe_litho VALUES
 ('SOCLE_ACIDE','Socle cristallin (granites, gneiss, schistes)','acide','Périgord vert / Nontronnais : Se, Cu, Co, Zn attendus bas ; Mo bas ; Mn, Fe élevés (hypothèse à tester).'),
 ('CALC_JURA','Calcaires jurassiques (causses)','calcaire','Périgord noir : Cu, Zn, Mn, Co peu disponibles ; Mo disponible.'),
 ('CALC_CRETACE','Calcaires crétacés et placages','calcaire','Périgord blanc, Ribéracois : mosaïque calcaire / placages acides.'),
 ('MOLASSE','Molasses, marnes et calcaires lacustres tertiaires','calcaire','Bergeracois, Pays de Serres, Terreforts : antagonisme Mo–Cu sur argilo-calcaires hydromorphes.'),
 ('SABLE_ARGILE_TERT','Sables et argiles tertiaires (Double, Landais, Landes)','acide','Sols acides hydromorphes : Cu, Se, Co, Zn bas ; Fe, Mn en excès.'),
 ('BOULBENE','Limons et sables quaternaires des terrasses (boulbènes)','acide','Lessivés, hydromorphes : Ca, Mg, K bas.'),
 ('ALLUVIONS','Alluvions récentes et terrasses','neutre','Vallées : K élevé possible, Mo sur palus argileux.'),
 ('AUTRE','Autre / non classé',NULL,NULL);

CREATE TABLE sig.rrp_ucs (                             -- Référentiel Régional Pédologique 1/250 000 (Bordeaux Sciences Agro / INRAE), UCS
  gid             serial PRIMARY KEY,
  departement     char(2) NOT NULL,
  no_ucs          integer NOT NULL,
  nom_ucs         text,
  pedopaysage     text,
  attributs       jsonb,
  geom            geometry(MultiPolygon, 2154) NOT NULL
);
CREATE INDEX ON sig.rrp_ucs USING GIST (geom);

CREATE TABLE sig.rrp_uts (                             -- UTS composant chaque UCS (base sémantique DoneSol, sous licence)
  id              serial PRIMARY KEY,
  departement     char(2) NOT NULL,
  no_ucs          integer NOT NULL,
  no_uts          integer NOT NULL,
  nom_uts         text,
  pct_ucs         numeric(5,1),                         -- part de l''UTS dans l''UCS
  type_sol_rp2008 text,                                 -- 'BRUNISOL DYSTRIQUE', 'CALCOSOL', 'REDOXISOL'...
  ph_eau_surface  numeric(3,1),
  texture_surface text,
  hydromorphie    text,                                 -- 'nulle','faible','moyenne','forte'
  calcaire        boolean,
  profondeur_cm   smallint,
  attributs       jsonb,
  UNIQUE (departement, no_ucs, no_uts)
);

CREATE TABLE sig.rpg_parcelle (                        -- RPG (IGN/ASP), millésime annuel, emprise 24-47
  id_parcel       text NOT NULL,
  millesime       smallint NOT NULL,
  code_cultu      char(3),
  code_group      char(2),
  surf_parc       numeric(10,2),
  geom            geometry(MultiPolygon, 2154) NOT NULL,
  PRIMARY KEY (id_parcel, millesime)
);
CREATE INDEX ON sig.rpg_parcelle USING GIST (geom);

CREATE TABLE sig.bdat_canton (                         -- BDAT / Geosol : statistiques cantonales d''analyses de terre (GIS Sol)
  code_canton     text NOT NULL,
  periode         text NOT NULL,                        -- '2010-2014', '2015-2019'
  parametre       text NOT NULL,                        -- 'ph_eau','cu_edta','zn_edta','mn_edta','mg_ech','k_ech','p_olsen','c_org'
  n_analyses      integer,
  mediane         numeric,
  q1              numeric,
  q3              numeric,
  unite           text,
  PRIMARY KEY (code_canton, periode, parametre)
);

CREATE TABLE sig.rmqs_site (                           -- RMQS : fond géochimique (Cu, Zn, Co, Mo… totaux et EDTA)
  id_site         text PRIMARY KEY,
  campagne        text,
  attributs       jsonb,
  geom            geometry(Point, 2154) NOT NULL
);
CREATE INDEX ON sig.rmqs_site USING GIST (geom);

-- =====================================================================================
-- 5. VUES DE VALORISATION (schéma analyse)
-- =====================================================================================

-- 5.1 Résultats au format large (une ligne par analyse) — pour QGIS, Excel, INRAtion
CREATE OR REPLACE VIEW valo.v_resultat_large AS
SELECT a.id AS analyse_id, a.num_echantillon_labo, a.date_edition, p.id AS prelevement_id, p.elevage_id, e.code_pseudo, e.departement,
       p.date_prelevement, p.date_recolte, p.cycle_coupe, p.type_fourrage_code, tf.libelle AS type_fourrage, tf.famille_code, p.nom_lot,
       p.destination_categorie_code, p.motif, p.geom,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='MS')   AS ms,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='MM')   AS mm,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='MAT')  AS mat,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='CB')   AS cb,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='NDF')  AS ndf,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='ADF')  AS adf,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='ADL')  AS adl,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='DCS')  AS dcs,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='SSR')  AS ssr,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='AMI')  AS ami,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='DMO')  AS dmo,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='UFL')  AS ufl_2007,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='UFV')  AS ufv_2007,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='PDIN') AS pdin,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='PDIE') AS pdie,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='UEL')  AS uel,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='UEB')  AS ueb,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='UEM')  AS uem,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='UFL_2018') AS ufl_2018,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='UFV_2018') AS ufv_2018,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='PDI')  AS pdi,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='PDIA_2018') AS pdia_2018,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='BPR')  AS bpr,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='NI')   AS ni,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='P')    AS p,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='CA')   AS ca,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='MG')   AS mg,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='K')    AS k,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='NA')   AS na,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='S')    AS s,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='CU')   AS cu,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='ZN')   AS zn,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='MN')   AS mn,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='FE')   AS fe,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='SE')   AS se,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='I')    AS iode,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='CO')   AS co,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='MO_OLIGO') AS mo,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='BACA') AS baca,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='BE')   AS be,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='DON')  AS don,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='ZEA')  AS zea,
       MAX(r.valeur) FILTER (WHERE r.parametre_code='FUM')  AS fum
FROM fourrage.analyse a
JOIN fourrage.prelevement p ON p.id = a.prelevement_id
JOIN fourrage.elevage e ON e.id = p.elevage_id
JOIN ref.type_fourrage tf ON tf.code = p.type_fourrage_code
LEFT JOIN fourrage.resultat r ON r.analyse_id = a.id
GROUP BY a.id, a.num_echantillon_labo, a.date_edition, p.id, p.elevage_id, e.code_pseudo, e.departement, p.date_prelevement, p.date_recolte,
         p.cycle_coupe, p.type_fourrage_code, tf.libelle, tf.famille_code, p.nom_lot, p.destination_categorie_code, p.motif, p.geom;
COMMENT ON VIEW valo.v_resultat_large IS 'Pivot des résultats (long → large). Aucune donnée nominative.';

-- 5.2 Indicateurs dérivés (calcul à la volée)
CREATE OR REPLACE VIEW valo.v_indicateurs AS
SELECT v.analyse_id, v.num_echantillon_labo,
       CASE WHEN v.k IS NOT NULL AND v.ca IS NOT NULL AND v.mg IS NOT NULL AND (v.ca/20.04 + v.mg/12.15) > 0
            THEN round((v.k/39.10) / (v.ca/20.04 + v.mg/12.15), 2) END AS k_camg_eq,
       CASE WHEN v.p > 0 THEN round(v.ca / v.p, 2) END AS ca_p,
       CASE WHEN v.mo > 0 THEN round(v.cu / v.mo, 1) END AS cu_mo,
       CASE WHEN v.ufl_2018 > 0 THEN round(v.pdi / v.ufl_2018, 0) END AS pdi_ufl,
       -- BACA recalculée quand S et Cl sont dosés (sinon valeur du labo)
       CASE WHEN v.s IS NOT NULL THEN round(v.na*43.5 + v.k*25.6 - COALESCE(v.s,0)*62.4, 0) END AS baca_recalc_sans_cl,
       v.baca AS baca_labo
FROM valo.v_resultat_large v;

-- 5.3 Contexte pédo-géologique de chaque prélèvement (matérialisée : à rafraîchir après import SIG)
CREATE MATERIALIZED VIEW valo.mv_prelevement_contexte AS
SELECT p.id AS prelevement_id, p.elevage_id, e.departement,
       COALESCE(p.geom, pa.centroide, e.geom_siege) AS geom_ref,
       CASE WHEN p.geom IS NOT NULL THEN 'point_prelevement' WHEN pa.centroide IS NOT NULL THEN 'centroide_parcelle' ELSE 'siege_exploitation' END AS precision_geo,
       c.code_insee, c.nom AS commune, c.code_canton,
       rn.code AS region_naturelle_code, rn.libelle AS region_naturelle,
       g.notation AS geol_notation, g.descr AS geol_descr, g.classe_litho_gds,
       u.no_ucs, u.nom_ucs, u.pedopaysage,
       rp.code_cultu AS rpg_code_culture, rp.millesime AS rpg_millesime
FROM fourrage.prelevement p
JOIN fourrage.elevage e ON e.id = p.elevage_id
LEFT JOIN fourrage.parcelle pa ON pa.id = p.parcelle_id
LEFT JOIN LATERAL (SELECT * FROM sig.commune c WHERE ST_Intersects(c.geom, COALESCE(p.geom, pa.centroide, e.geom_siege)) LIMIT 1) c ON true
LEFT JOIN LATERAL (SELECT * FROM sig.region_naturelle r WHERE ST_Intersects(r.geom, COALESCE(p.geom, pa.centroide, e.geom_siege)) LIMIT 1) rn ON true
LEFT JOIN LATERAL (SELECT * FROM sig.geol_formation g WHERE ST_Intersects(g.geom, COALESCE(p.geom, pa.centroide, e.geom_siege)) LIMIT 1) g ON true
LEFT JOIN LATERAL (SELECT * FROM sig.rrp_ucs u WHERE ST_Intersects(u.geom, COALESCE(p.geom, pa.centroide, e.geom_siege)) LIMIT 1) u ON true
LEFT JOIN LATERAL (SELECT * FROM sig.rpg_parcelle rp WHERE ST_Intersects(rp.geom, COALESCE(p.geom, pa.centroide)) ORDER BY rp.millesime DESC LIMIT 1) rp ON true;
CREATE UNIQUE INDEX ON valo.mv_prelevement_contexte (prelevement_id);
CREATE INDEX ON valo.mv_prelevement_contexte USING GIST (geom_ref);
COMMENT ON MATERIALIZED VIEW valo.mv_prelevement_contexte IS 'Enrichissement spatial : commune, région naturelle, formation géologique (BRGM), UCS (RRP), culture RPG. La précision dépend du point disponible (prélèvement > parcelle > siège).';

-- 5.4 Références locales (quartiles) par campagne × type de fourrage × paramètre — méthode LANO/REQUASUD
CREATE OR REPLACE VIEW valo.v_reference_locale AS
SELECT p.campagne_code, p.type_fourrage_code, tf.libelle AS type_fourrage, r.parametre_code, r.unite,
       count(*) AS n,
       percentile_cont(0.25) WITHIN GROUP (ORDER BY r.valeur) AS q1,
       percentile_cont(0.50) WITHIN GROUP (ORDER BY r.valeur) AS mediane,
       percentile_cont(0.75) WITHIN GROUP (ORDER BY r.valeur) AS q3,
       min(r.valeur) AS minimum, max(r.valeur) AS maximum
FROM fourrage.resultat r
JOIN fourrage.analyse a ON a.id = r.analyse_id AND a.statut IN ('valide','controle','recu')
JOIN fourrage.prelevement p ON p.id = a.prelevement_id
JOIN ref.type_fourrage tf ON tf.code = p.type_fourrage_code
WHERE r.valeur IS NOT NULL AND NOT r.flag_anomalie
GROUP BY p.campagne_code, p.type_fourrage_code, tf.libelle, r.parametre_code, r.unite
HAVING count(*) >= 5;   -- seuil de diffusion (anonymat et robustesse)
COMMENT ON VIEW valo.v_reference_locale IS 'Références « Dordogne / Lot-et-Garonne » : positionnement faible (< Q1) / normal (Q1-Q3) / élevé (> Q3), diffusé seulement si n ≥ 5.';

-- 5.5 Références locales par classe lithologique (croisement sol–fourrage)
CREATE OR REPLACE VIEW valo.v_reference_par_litho AS
SELECT ctx.classe_litho_gds, p.type_fourrage_code, r.parametre_code, r.unite,
       count(*) AS n,
       percentile_cont(0.5) WITHIN GROUP (ORDER BY r.valeur) AS mediane,
       percentile_cont(0.25) WITHIN GROUP (ORDER BY r.valeur) AS q1,
       percentile_cont(0.75) WITHIN GROUP (ORDER BY r.valeur) AS q3
FROM fourrage.resultat r
JOIN fourrage.analyse a ON a.id = r.analyse_id
JOIN fourrage.prelevement p ON p.id = a.prelevement_id
JOIN valo.mv_prelevement_contexte ctx ON ctx.prelevement_id = p.id
WHERE r.valeur IS NOT NULL AND NOT r.flag_anomalie AND r.parametre_code IN ('CU','ZN','MN','FE','SE','I','CO','MO_OLIGO','P','CA','MG','K','NA','MAT','UFL_2018')
GROUP BY ctx.classe_litho_gds, p.type_fourrage_code, r.parametre_code, r.unite
HAVING count(*) >= 5;

-- 5.6 Fonction de classement d'une valeur selon la grille de seuils (du plus spécifique au plus général)
CREATE OR REPLACE FUNCTION ref.f_classer_valeur(p_parametre text, p_valeur numeric, p_type_fourrage text, p_categorie text)
RETURNS TABLE (seuil_id integer, classe text, gravite smallint, message text, nature text) LANGUAGE sql STABLE AS $$
  SELECT s.id, s.classe, s.gravite, s.message, s.nature
  FROM ref.seuil_interpretation s
  LEFT JOIN ref.type_fourrage tf ON tf.code = p_type_fourrage
  LEFT JOIN ref.categorie_animale ca ON ca.code = p_categorie
  WHERE s.actif AND s.parametre_code = p_parametre
    AND (s.type_fourrage_code IS NULL OR s.type_fourrage_code = p_type_fourrage)
    AND (s.famille_fourrage_code IS NULL OR s.famille_fourrage_code = tf.famille_code)
    AND (s.categorie_code IS NULL OR s.categorie_code = p_categorie)
    AND (s.espece_code IS NULL OR s.espece_code = ca.espece_code)
    AND (s.borne_min IS NULL OR p_valeur >= s.borne_min)
    AND (s.borne_max IS NULL OR p_valeur <  s.borne_max)
  ORDER BY (s.type_fourrage_code IS NOT NULL) DESC, (s.categorie_code IS NOT NULL) DESC, (s.famille_fourrage_code IS NOT NULL) DESC, (s.espece_code IS NOT NULL) DESC, s.gravite DESC
$$;

-- 5.7 Vue des alertes automatiques calculables pour toute analyse (avant validation par le technicien)
CREATE OR REPLACE VIEW valo.v_alertes_auto AS
SELECT a.id AS analyse_id, a.num_echantillon_labo, p.type_fourrage_code, p.destination_categorie_code,
       r.parametre_code, r.valeur, r.unite, c.seuil_id, c.classe, c.gravite, c.message, c.nature
FROM fourrage.analyse a
JOIN fourrage.prelevement p ON p.id = a.prelevement_id
JOIN fourrage.resultat r ON r.analyse_id = a.id AND r.valeur IS NOT NULL
CROSS JOIN LATERAL ref.f_classer_valeur(r.parametre_code, r.valeur, p.type_fourrage_code, p.destination_categorie_code) c
WHERE c.gravite >= 1;

-- 5.8 Vue « fourrage ↔ santé » : événements sanitaires survenus dans les 6 mois suivant un prélèvement du même élevage
CREATE OR REPLACE VIEW valo.v_fourrage_sante AS
SELECT p.id AS prelevement_id, p.elevage_id, p.type_fourrage_code, p.date_prelevement,
       ev.id AS evenement_id, ev.type_evenement, ev.date_evenement, ev.espece_code, ev.gravite,
       ev.date_evenement - p.date_prelevement AS delai_jours
FROM fourrage.prelevement p
JOIN fourrage.evenement_sanitaire ev ON ev.elevage_id = p.elevage_id
 AND ev.date_evenement BETWEEN p.date_prelevement - 60 AND p.date_prelevement + 180;

-- =====================================================================================
-- 6. RÔLES ET DROITS (à adapter à l'annuaire GDS)
-- =====================================================================================
DO $$
BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'gds_lecture')       THEN CREATE ROLE gds_lecture NOLOGIN; END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'gds_technicien')    THEN CREATE ROLE gds_technicien NOLOGIN; END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'gds_administrateur') THEN CREATE ROLE gds_administrateur NOLOGIN; END IF;
END $$;
GRANT USAGE ON SCHEMA ref, fourrage, sig, valo TO gds_lecture, gds_technicien, gds_administrateur;
GRANT SELECT ON ALL TABLES IN SCHEMA ref, sig, valo TO gds_lecture, gds_technicien, gds_administrateur;
GRANT SELECT ON ALL TABLES IN SCHEMA fourrage TO gds_lecture;          -- lecture des données pseudonymisées seulement
GRANT SELECT, INSERT, UPDATE ON ALL TABLES IN SCHEMA fourrage TO gds_technicien;
GRANT USAGE ON ALL SEQUENCES IN SCHEMA fourrage TO gds_technicien;
GRANT USAGE ON SCHEMA prive TO gds_technicien, gds_administrateur;
GRANT SELECT, INSERT, UPDATE ON ALL TABLES IN SCHEMA prive TO gds_technicien;   -- identités : techniciens habilités uniquement
GRANT ALL ON ALL TABLES IN SCHEMA prive, fourrage, ref, sig, valo TO gds_administrateur;
REVOKE ALL ON SCHEMA prive FROM gds_lecture;

-- Fin du fichier 01
