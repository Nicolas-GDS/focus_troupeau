# 5. Modèle de données

## 5.1 Principes

Le socle est la base du document 02 (schémas `ref`, `prive`, `fourrage`, `sig`, `valo`, PostgreSQL 16 + PostGIS 3), conservée telle quelle : ses scripts `01_schema`, `02_referentiels` et `03_chargement_campagne` restent la source de vérité des objets existants et sont exécutés par une migration Laravel d'amorçage. Les extensions sont regroupées dans quatre schémas nouveaux :

| Schéma | Contenu | Données nominatives |
|---|---|---|
| `app` | GDS, comptes, rôles, rattachements, conventions, consentements, paramètres, notifications, journal | Oui (comptes) |
| `dossier` | Visites d'approche globale, eau, mesures terrain, étiquettes, bilan sanitaire, interprétation de visite, plan d'action | Non (pseudonymisé par `elevage_id`) |
| `stock` | Inventaires, silos, lots de stock, mouvements, périodes | Non |
| `plan` | Lots d'animaux (étendus), phases, rations, scénarios, leviers | Non |

Conventions : identifiants `bigint generated always as identity` pour les nouvelles tables (uuid pour `elevage`, comme existant) ; colonnes `cree_le`, `maj_le`, `cree_par`, `maj_par` (référence `app.user`) sur toutes les tables métier ; suppression logique par `archive_le` ; contraintes `CHECK` pour les énumérations ; toutes les tables portant `elevage_id` sont soumises au cloisonnement (chapitre 6). Les tables existantes `fourrage.lot_animaux`, `fourrage.ration`, `fourrage.ration_composant` sont **migrées** vers `plan.*` (vue de compatibilité conservée pour les scripts existants).

Le diagramme ci-dessous situe les nouveaux schémas par rapport au socle ; les tables existantes sont en gris dans la version Word.

```
ref.* ──────────────┐
                    ▼
prive.elevage_identite ◄── fourrage.elevage ◄──────────── app.elevage_acces ◄── app.user ◄── app.user_gds ── app.gds
                               │   ▲                           │                          app.convention ──┘
     fourrage.prelevement ◄────┤   └── dossier.visite ◄────────┤
     fourrage.analyse ─ resultat   │       ├── dossier.mesure_terrain, analyse_eau, etiquette_aliment, indicateur_sanitaire
     fourrage.interpretation ─ alerte      ├── dossier.interpretation_visite ─ message ─ cause_commune
                                           └── dossier.plan_action ─ action ─ controle
     stock.inventaire ─ periode_phase ─ ouvrage_silo ─ lot_stock ─ mouvement
     plan.lot_animaux ─ phase ─ ration ─ ration_composant ─► stock.lot_stock | ref.aliment_reference | dossier.etiquette_aliment
     plan.scenario ─ levier_ligne ─► stock.inventaire
```

## 5.2 Schéma `app`

```sql
CREATE SCHEMA app;

CREATE TABLE app.gds (
  id            smallint PRIMARY KEY,                 -- 24, 47
  code          char(2) UNIQUE NOT NULL,
  nom           text NOT NULL,                        -- 'GDS Dordogne'
  couleur_locale text NOT NULL,                       -- '#006BA0' / '#D4820A' (charte)
  contact_email text, site_web text,
  actif         boolean NOT NULL DEFAULT true
);

CREATE TABLE app."user" (
  id            bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  email         citext UNIQUE NOT NULL,
  nom text NOT NULL, prenom text,
  password      text,                                 -- NULL pour les comptes Entra ID
  entra_oid     text UNIQUE,                          -- object id Entra ID (techniciens, vétérinaires conseil, admin)
  personne_id   integer REFERENCES prive.personne(id),-- lien avec le référentiel des intervenants
  telephone     text,                                 -- SMS niveau 3
  pref_email boolean NOT NULL DEFAULT true, pref_sms boolean NOT NULL DEFAULT true,
  derniere_connexion timestamptz, actif boolean NOT NULL DEFAULT true,
  cree_le timestamptz NOT NULL DEFAULT now(), maj_le timestamptz NOT NULL DEFAULT now()
);
-- rôles et permissions : tables de spatie/laravel-permission (app.roles, app.permissions, app.model_has_roles…)

CREATE TABLE app.user_gds (                           -- rattachement technicien / vét. conseil / admin à un GDS
  user_id bigint REFERENCES app."user"(id) ON DELETE CASCADE,
  gds_id  smallint REFERENCES app.gds(id),
  PRIMARY KEY (user_id, gds_id)
);

ALTER TABLE fourrage.elevage ADD COLUMN gds_id smallint REFERENCES app.gds(id);
UPDATE fourrage.elevage SET gds_id = departement::smallint;   -- reprise
ALTER TABLE fourrage.elevage ALTER COLUMN gds_id SET NOT NULL;

CREATE TABLE app.elevage_acces (                      -- rattachement d'un compte éleveur (ou vétérinaire) à un élevage
  user_id    bigint REFERENCES app."user"(id) ON DELETE CASCADE,
  elevage_id uuid   REFERENCES fourrage.elevage(id) ON DELETE CASCADE,
  role       text NOT NULL CHECK (role IN ('eleveur','veterinaire')),
  consentement_id bigint,                             -- obligatoire si role = 'veterinaire' (FK ci-dessous)
  PRIMARY KEY (user_id, elevage_id)
);

CREATE TABLE app.convention (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  elevage_id uuid NOT NULL REFERENCES fourrage.elevage(id),
  gds_id     smallint NOT NULL REFERENCES app.gds(id),
  type       text NOT NULL DEFAULT 'approche_globale',
  date_debut date NOT NULL, date_fin date NOT NULL,
  statut     text NOT NULL DEFAULT 'active' CHECK (statut IN ('active','echue','resiliee')),
  reference_externe text,                             -- n° de contrat (SignClic / gestion)
  commentaire text,
  cree_par bigint REFERENCES app."user"(id), maj_par bigint REFERENCES app."user"(id),   -- auteur (EF-GEN-06)
  cree_le timestamptz NOT NULL DEFAULT now(), maj_le timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX ON app.convention (elevage_id, statut);

CREATE TABLE app.consentement (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  elevage_id uuid NOT NULL REFERENCES fourrage.elevage(id),
  type       text NOT NULL CHECK (type IN ('references','veterinaire')),
  veterinaire_user_id bigint REFERENCES app."user"(id),   -- si type = 'veterinaire'
  accorde_le timestamptz NOT NULL DEFAULT now(), accorde_par bigint REFERENCES app."user"(id),
  revoque_le timestamptz, revoque_par bigint REFERENCES app."user"(id),
  CHECK (type <> 'veterinaire' OR veterinaire_user_id IS NOT NULL)
);
ALTER TABLE app.elevage_acces ADD FOREIGN KEY (consentement_id) REFERENCES app.consentement(id);

CREATE TABLE app.parametre_defaut (                   -- valeurs par défaut GDS, versionnées
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  gds_id smallint REFERENCES app.gds(id),             -- NULL = instance entière
  cle    text NOT NULL,                               -- 'pertes.conservation.silo_bien_conduit', 'securite.marge', 'prix.foin_t_ms'…
  valeur numeric NOT NULL, unite text,
  libelle text NOT NULL, source text, statut_epistemique text,
  valide_du date NOT NULL DEFAULT current_date, valide_au date,
  commentaire text, cree_par bigint, cree_le timestamptz NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX ON app.parametre_defaut (coalesce(gds_id,0), cle, valide_du);

CREATE TABLE app.parametre_elevage (                  -- valeur modifiée par l'éleveur (D10)
  elevage_id uuid REFERENCES fourrage.elevage(id) ON DELETE CASCADE,
  cle text NOT NULL, valeur numeric NOT NULL,
  motif text, maj_par bigint, maj_le timestamptz NOT NULL DEFAULT now(),
  PRIMARY KEY (elevage_id, cle)
);

CREATE TABLE app.notification (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  user_id bigint REFERENCES app."user"(id), elevage_id uuid,
  canal text NOT NULL CHECK (canal IN ('email','sms')),
  type  text NOT NULL,                                -- 'fiche_publiee','niveau3','autonomie','rappel_controle','convention'
  objet text, corps text, reference_objet text,       -- ex. 'dossier.interpretation_visite:123'
  statut text NOT NULL DEFAULT 'a_envoyer' CHECK (statut IN ('a_envoyer','envoye','echec')),
  id_brevo text, envoye_le timestamptz, erreur text, cree_le timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE app.journal (                            -- audit des actions sensibles
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  user_id bigint, elevage_id uuid, action text NOT NULL, objet text, detail jsonb,
  ip inet, horodatage timestamptz NOT NULL DEFAULT now()
);
```

## 5.3 Schéma `dossier`

```sql
CREATE SCHEMA dossier;

CREATE TABLE dossier.visite (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  elevage_id uuid NOT NULL REFERENCES fourrage.elevage(id),
  campagne_code text REFERENCES fourrage.campagne(code),
  date_visite date NOT NULL, technicien_id bigint REFERENCES app."user"(id),
  motif text NOT NULL CHECK (motif IN ('initiale','suivi','question_sanitaire','bilan_stocks','autre')),
  motif_detail text,
  compte_rendu text,                                  -- étape « collecter » : entretien, observations
  statut text NOT NULL DEFAULT 'en_cours' CHECK (statut IN ('en_cours','interpretee','publiee','archivee')),
  cree_le timestamptz NOT NULL DEFAULT now(), maj_le timestamptz NOT NULL DEFAULT now()
);
ALTER TABLE fourrage.prelevement ADD COLUMN visite_id bigint REFERENCES dossier.visite(id);
ALTER TABLE fourrage.analyse     ADD COLUMN exclu_references boolean NOT NULL DEFAULT false,
                                 ADD COLUMN motif_exclusion text;
ALTER TABLE fourrage.interpretation
  ADD COLUMN version_grille text,                     -- version de ref.version_referentiel utilisée
  ADD COLUMN utilisation_conseillee text,             -- catégories conseillées (texte structuré)
  ADD COLUMN utilisation_deconseillee text[] ,        -- codes de ref.categorie_animale
  ADD COLUMN niveau_propose smallint,
  ADD COLUMN vise_par bigint REFERENCES app."user"(id), ADD COLUMN vise_le timestamptz;
-- le statut existant ('brouillon','valide','envoye','archive') est étendu : 'brouillon','valide','visee','publie','archive'

CREATE TABLE dossier.analyse_eau (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  elevage_id uuid NOT NULL REFERENCES fourrage.elevage(id), visite_id bigint REFERENCES dossier.visite(id),
  point_prelevement text NOT NULL,                    -- 'source', 'forage', 'abreuvoir lot taries'…
  date_prelevement date NOT NULL, laboratoire_id integer REFERENCES ref.laboratoire(id),
  fichier_pdf text, hash_pdf text,
  statut text NOT NULL DEFAULT 'recu' CHECK (statut IN ('recu','controle','valide','publie','anomalie')),
  cree_le timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE dossier.resultat_eau (                   -- même logique que fourrage.resultat
  analyse_eau_id bigint REFERENCES dossier.analyse_eau(id) ON DELETE CASCADE,
  parametre_code text NOT NULL REFERENCES ref.parametre(code),   -- paramètres 'EAU_*' ajoutés au référentiel
  valeur numeric, censure char(1), unite text NOT NULL, flag_anomalie boolean NOT NULL DEFAULT false,
  PRIMARY KEY (analyse_eau_id, parametre_code)
);

CREATE TABLE dossier.mesure_terrain (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  elevage_id uuid NOT NULL REFERENCES fourrage.elevage(id), visite_id bigint REFERENCES dossier.visite(id),
  lot_animaux_id bigint,                              -- FK plan.lot_animaux (ajoutée après création du schéma plan)
  date_mesure date NOT NULL, identifiant_animal text, -- n° de travail, facultatif
  stade text,                                         -- 'tarie','prepa_velage','lactation','veau'
  mesure_code text NOT NULL REFERENCES ref.mesure_terrain(code),  -- 'PH_BOUSE','REDOX_BOUSE','TAMIS_1','PH_URINE','BRIX_URINE','DENSITE_URINE','GLYCEMIE','BOH','UREE','NEC','RUMEN','BRIX_COLOSTRUM','PROT_VEAU'
  valeur numeric NOT NULL, unite text NOT NULL,
  lecture_code text,                                  -- résultat de RC-1.5
  statut text NOT NULL DEFAULT 'saisie' CHECK (statut IN ('saisie','validee','publiee')),
  saisie_par bigint, cree_le timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE dossier.etiquette_aliment (              -- minéral, bolus, seau, aliment du commerce
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  elevage_id uuid NOT NULL REFERENCES fourrage.elevage(id), visite_id bigint,
  nature text NOT NULL CHECK (nature IN ('mineral','bolus','seau','sel','concentre','autre')),
  libelle_eleveur text NOT NULL,                      -- saisi tel quel, jamais repris dans une préconisation
  photo text, date_limite date, dose_g_par_animal_jour numeric, mode_distribution text,
  statut text NOT NULL DEFAULT 'saisie' CHECK (statut IN ('saisie','validee','publiee')),
  cree_par bigint, cree_le timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE dossier.etiquette_composition (
  etiquette_id bigint REFERENCES dossier.etiquette_aliment(id) ON DELETE CASCADE,
  parametre_code text REFERENCES ref.parametre(code), valeur numeric NOT NULL, unite text NOT NULL,  -- par kg de produit
  PRIMARY KEY (etiquette_id, parametre_code)
);

CREATE TABLE dossier.indicateur_sanitaire (           -- bilan sanitaire GDS (import SAGEMA ou saisie)
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  elevage_id uuid NOT NULL REFERENCES fourrage.elevage(id), campagne_code text NOT NULL,
  indicateur_code text NOT NULL REFERENCES ref.indicateur_sanitaire(code),  -- 'IVV','MORTALITE_VEAUX_0_12M','NON_DELIVRANCE','METRITE','AVORTEMENT','RETOURNEMENT','DIARRHEE_ADULTE','VELAGE_DIFFICILE'
  valeur numeric, nb_cas integer, denominateur integer,  -- taux = nb_cas / denominateur (vêlages)
  source text CHECK (source IN ('sagema','saisie')), importe_le timestamptz NOT NULL DEFAULT now(),
  UNIQUE (elevage_id, campagne_code, indicateur_code)
);

CREATE TABLE dossier.interpretation_visite (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  visite_id bigint NOT NULL REFERENCES dossier.visite(id),
  version smallint NOT NULL DEFAULT 1,
  niveau_global smallint CHECK (niveau_global BETWEEN 0 AND 3),
  titre text, synthese text, limites text,
  version_grille text NOT NULL,
  statut text NOT NULL DEFAULT 'brouillon' CHECK (statut IN ('brouillon','valide','visee','publie','archive')),
  redige_par bigint, vise_par bigint, vise_le timestamptz, publie_par bigint, publie_le timestamptz,
  fiche_html text, fiche_pdf text,                    -- version figée à la publication
  cree_le timestamptz NOT NULL DEFAULT now(), maj_le timestamptz NOT NULL DEFAULT now(),
  UNIQUE (visite_id, version)
);
CREATE TABLE dossier.message (                        -- les cinq messages hiérarchisés
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  interpretation_visite_id bigint REFERENCES dossier.interpretation_visite(id) ON DELETE CASCADE,
  ordre smallint NOT NULL, gravite smallint NOT NULL CHECK (gravite BETWEEN 0 AND 3), texte text NOT NULL,
  pilier text CHECK (pilier IN ('hydratation','nutrition','physique','environnement','bien_etre'))
);
CREATE TABLE dossier.cause_commune (                  -- étape « croiser »
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  interpretation_visite_id bigint REFERENCES dossier.interpretation_visite(id) ON DELETE CASCADE,
  famille_cause text NOT NULL,                        -- 'immunite_oligo','metabolisme_mineral','metabolisme_energetique','confort','infectieux'
  titre text NOT NULL, preuves text, consequences text
);

CREATE TABLE dossier.plan_action (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  interpretation_visite_id bigint NOT NULL REFERENCES dossier.interpretation_visite(id),
  cree_le timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE dossier.action (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  plan_action_id bigint REFERENCES dossier.plan_action(id) ON DELETE CASCADE,
  priorite smallint NOT NULL CHECK (priorite BETWEEN 1 AND 3), ordre smallint NOT NULL,
  responsable text NOT NULL CHECK (responsable IN ('eleveur','technicien','veterinaire')),
  titre text NOT NULL, comment text, quand text, echeance date,
  jalon text,                                         -- 'tarissement','6_sem_avant_velage','3_sem_avant_velage','velage','entree_hivernage','mise_a_l_herbe','campagne_suivante'
  statut text NOT NULL DEFAULT 'a_faire' CHECK (statut IN ('a_faire','faite','abandonnee')),
  fait_le date, fait_par bigint, commentaire_eleveur text
);
CREATE TABLE dossier.controle (                       -- étape « suivre »
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  action_id bigint REFERENCES dossier.action(id) ON DELETE CASCADE,
  mesure_code text, cible text NOT NULL, echeance date NOT NULL,
  resultat text, mesure_terrain_id bigint REFERENCES dossier.mesure_terrain(id), analyse_animale_id integer REFERENCES fourrage.analyse_animale(id),
  statut text NOT NULL DEFAULT 'planifie' CHECK (statut IN ('planifie','realise','echu'))
);

CREATE TABLE dossier.note_liaison (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  interpretation_id integer REFERENCES fourrage.interpretation(id), interpretation_visite_id bigint,
  veterinaire_user_id bigint REFERENCES app."user"(id), question text, retour text,
  pdf text, envoyee_le timestamptz, retour_le timestamptz
);
```

Référentiels ajoutés dans `ref` : `ref.mesure_terrain` (code, libellé, unité, décimales), `ref.repere_mesure_terrain` (mesure, espèce, stade, borne basse, borne haute, source, statut), `ref.grille_lecture_mesure` (combinaison de lectures → libellé proposé), `ref.repere_analyse_animale` (élément, matrice, espèce, bornes, source), `ref.indicateur_sanitaire` et `ref.seuil_bilan_sanitaire` (indicateur, espèce, système, S1, S2, source), `ref.grille_trouble_cause` (trouble × famille de cause, force 0-3, source NBVC [C]), `ref.trouble_sanitaire` (code, libellé, famille, espèces, catégories cibles, correspondance avec `fourrage.evenement_sanitaire.type_evenement` ; ajout du 15/10/2026, chapitre 11), `ref.compatibilite_parametre_trouble` (paramètre d'analyse × sens — carence, excès, ratio bas, ratio élevé — × trouble, avec espèces et catégories, force 1-3, mécanisme, facteurs modulateurs, repère quantitatif, référence et statut ; structure du chapitre 11), une colonne `sens` (`bas` / `haut`) sur `ref.seuil_interpretation` pour relier une alerte au sens des liens de compatibilité, `ref.plage_plausibilite` (RC-2), `ref.version_referentiel` (numéro, date, auteur, commentaire ; chaque ligne de seuil ou de besoin porte `version_creation` et `version_fin`). Les paramètres d'eau (`EAU_PH`, `EAU_NITRATES`, `EAU_FER`, `EAU_DURETE`, `EAU_SULFATES`, `EAU_COLIFORMES`, `EAU_ENTEROCOQUES`…) sont ajoutés à `ref.parametre` avec la famille `EAU` et leurs seuils dans `ref.seuil_interpretation` (nature `eau`, nouvelle valeur de l'énumération).

## 5.4 Schéma `stock`

```sql
CREATE SCHEMA stock;

CREATE TABLE stock.inventaire (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  elevage_id uuid NOT NULL REFERENCES fourrage.elevage(id), visite_id bigint REFERENCES dossier.visite(id),
  date_inventaire date NOT NULL,
  type text NOT NULL CHECK (type IN ('fin_recolte','mi_hiver','crise','autre')),
  date_debut date NOT NULL, date_fin date NOT NULL,   -- période de référence (RC-10)
  marge_securite numeric NOT NULL,                    -- 0,15 par défaut ; copie de la valeur en vigueur
  annee_seche boolean NOT NULL DEFAULT false,
  statut text NOT NULL DEFAULT 'courant' CHECK (statut IN ('courant','clos','archive')),
  commentaire text, cree_par bigint, cree_le timestamptz NOT NULL DEFAULT now(), maj_le timestamptz NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX ON stock.inventaire (elevage_id) WHERE statut = 'courant';

CREATE TABLE stock.periode_phase (                    -- voie UGB uniquement (RC-10)
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  inventaire_id bigint REFERENCES stock.inventaire(id) ON DELETE CASCADE,
  libelle text NOT NULL CHECK (libelle IN ('hivernage','transition','complementation_estivale')),
  date_debut date NOT NULL, date_fin date NOT NULL, coefficient numeric NOT NULL DEFAULT 1
);

CREATE TABLE stock.lot_stock (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  inventaire_id bigint NOT NULL REFERENCES stock.inventaire(id) ON DELETE CASCADE,
  elevage_id uuid NOT NULL REFERENCES fourrage.elevage(id),
  libelle text NOT NULL,                              -- 'Foin 1re coupe prairie du bas'
  famille_code text NOT NULL REFERENCES ref.famille_fourrage(code),
  type_fourrage_code text REFERENCES ref.type_fourrage(code),
  prelevement_id integer REFERENCES fourrage.prelevement(id),   -- lot analysé, le cas échéant
  forme text NOT NULL CHECK (forme IN ('silo','balle_ronde','balle_carree','botte','vrac')),
  date_recolte date, priorite_consommation smallint,  -- règle d'allocation RC-11
  -- silos
  ouvrage text CHECK (ouvrage IN ('couloir','taupiniere')), equation_code text,   -- 'mais_couloir', 'herbe_courts'…
  l1_m numeric, l2_m numeric, largeur_m numeric, hauteur_m numeric, nb_mesures_hauteur smallint,
  date_fermeture date, amidon_pct numeric, grain_pct numeric,
  methode_densite text CHECK (methode_densite IN ('equation','tamisage','carottage','saisie')), densite_kg_ms_m3 numeric,
  -- balles et vrac
  nombre numeric, poids_brut_kg numeric, poids_mesure boolean NOT NULL DEFAULT false, volume_m3 numeric,
  mode_stockage text,                                  -- clé de app.parametre_defaut 'pertes.stockage.*'
  -- commun
  ms_pct numeric, ms_origine text CHECK (ms_origine IN ('analyse','defaut','saisie')),
  pertes_pct numeric NOT NULL,                         -- conservation (silo) ou stockage (balles), valeur retenue
  volume_calc_m3 numeric, densite_calc numeric, stock_brut_t_ms numeric, stock_utile_t_ms numeric,  -- résultats RC-5/RC-7, recalculés à chaque enregistrement
  incertitude_rel numeric,                             -- RC-14
  commentaire text, cree_par bigint, cree_le timestamptz NOT NULL DEFAULT now(), maj_le timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX ON stock.lot_stock (elevage_id, inventaire_id);

CREATE TABLE stock.mouvement (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  lot_stock_id bigint NOT NULL REFERENCES stock.lot_stock(id) ON DELETE CASCADE,
  date_mouvement date NOT NULL,
  type text NOT NULL CHECK (type IN ('achat','vente','recomptage','ajustement')),
  quantite_t_ms numeric,                               -- signée pour achat/vente/ajustement ; valeur absolue du stock pour recomptage
  detail jsonb,                                        -- nouvelles cotes, nombre de balles, prix, fournisseur (libre, jamais repris)
  prix_eur numeric, scenario_ligne_id bigint,          -- origine : ligne de scénario retenue
  motif text, cree_par bigint, cree_le timestamptz NOT NULL DEFAULT now()
);
```

## 5.5 Schéma `plan`

```sql
CREATE SCHEMA plan;

CREATE TABLE plan.lot_animaux (                       -- remplace fourrage.lot_animaux
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  elevage_id uuid NOT NULL REFERENCES fourrage.elevage(id),
  inventaire_id bigint REFERENCES stock.inventaire(id),   -- NULL : lot « permanent » réutilisable
  libelle text NOT NULL, espece_code text NOT NULL REFERENCES ref.espece_animale(code),
  categorie_code text NOT NULL REFERENCES ref.categorie_animale(code),
  coeff_ugb numeric,                                  -- RC-8.1 (défaut selon catégorie)
  poids_vif_kg numeric, production_valeur numeric, production_unite text,   -- 'kg_lait_j', 'gmq_g_j'
  archive_le timestamptz, cree_par bigint, cree_le timestamptz NOT NULL DEFAULT now(), maj_le timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE plan.phase (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  lot_animaux_id bigint NOT NULL REFERENCES plan.lot_animaux(id) ON DELETE CASCADE,
  stade text NOT NULL CHECK (stade IN ('entretien','gestation','fin_gestation','prepa_velage','lactation','croissance','engraissement','tarissement')),
  date_debut date NOT NULL, date_fin date NOT NULL, effectif integer NOT NULL,
  EXCLUDE USING gist (lot_animaux_id WITH =, daterange(date_debut, date_fin, '[]') WITH &&)   -- pas de chevauchement
);
CREATE TABLE plan.ration (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  phase_id bigint NOT NULL UNIQUE REFERENCES plan.phase(id) ON DELETE CASCADE,
  commentaire text, saisie_par bigint, maj_le timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE plan.ration_composant (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  ration_id bigint NOT NULL REFERENCES plan.ration(id) ON DELETE CASCADE, ordre smallint NOT NULL,
  source text NOT NULL CHECK (source IN ('lot_stock','famille_stock','aliment_reference','etiquette','paturage')),
  lot_stock_id bigint REFERENCES stock.lot_stock(id), famille_code text REFERENCES ref.famille_fourrage(code),
  aliment_reference_id bigint REFERENCES ref.aliment_reference(id), etiquette_id bigint REFERENCES dossier.etiquette_aliment(id),
  quantite_brute_kg numeric, kg_ms numeric NOT NULL,   -- par animal et par jour ; kg_ms calculé depuis le brut et la MS si brut saisi
  CHECK ((source='lot_stock' AND lot_stock_id IS NOT NULL) OR (source='famille_stock' AND famille_code IS NOT NULL)
      OR (source='aliment_reference' AND aliment_reference_id IS NOT NULL) OR (source='etiquette' AND etiquette_id IS NOT NULL) OR source='paturage')
);

CREATE TABLE ref.aliment_reference (                  -- Tables INRAE-CIRAD-AFZ, chargées à l'installation
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  code text UNIQUE NOT NULL, libelle text NOT NULL,
  categorie text NOT NULL CHECK (categorie IN ('fourrage','cereale','correcteur_azote','tourteau','coproduit','mineral','autre')),
  famille_code text REFERENCES ref.famille_fourrage(code),   -- pour les fourrages de table
  ms_pct numeric NOT NULL, reference_id integer REFERENCES ref.reference_biblio(id), statut_epistemique text NOT NULL,
  actif boolean NOT NULL DEFAULT true
);
CREATE TABLE ref.aliment_reference_valeur (
  aliment_reference_id bigint REFERENCES ref.aliment_reference(id) ON DELETE CASCADE,
  parametre_code text REFERENCES ref.parametre(code), valeur numeric NOT NULL, unite text NOT NULL,   -- par kg MS
  PRIMARY KEY (aliment_reference_id, parametre_code)
);
CREATE TABLE ref.besoin_journalier (                  -- UFL, PDI, capacité d'ingestion par jour (RC-9)
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  categorie_code text NOT NULL REFERENCES ref.categorie_animale(code), stade text NOT NULL,
  poids_vif_kg numeric, production_valeur numeric, production_unite text,
  parametre_code text NOT NULL REFERENCES ref.parametre(code), mode text NOT NULL CHECK (mode IN ('par_jour','par_kg_ms')),
  valeur_min numeric, valeur_max numeric, valeur numeric,
  reference_id integer REFERENCES ref.reference_biblio(id), statut_epistemique text NOT NULL, commentaire text,
  version_creation text, version_fin text
);
CREATE TABLE ref.substituabilite (                    -- RC-13.2
  famille_source text REFERENCES ref.famille_fourrage(code), famille_cible text REFERENCES ref.famille_fourrage(code),
  ratio numeric NOT NULL DEFAULT 1, part_max_ration numeric, stades_autorises text[], source text, statut_epistemique text,
  PRIMARY KEY (famille_source, famille_cible)
);

CREATE TABLE plan.bilan (                             -- dernier bilan calculé (RC-11), mémorisé pour l'accueil et les alertes
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  inventaire_id bigint NOT NULL REFERENCES stock.inventaire(id) ON DELETE CASCADE, scenario_id bigint,
  calcule_le timestamptz NOT NULL DEFAULT now(),
  stock_utile_t_ms numeric, besoin_t_ms numeric, securite_t_ms numeric, bilan_t_ms numeric, couverture numeric,
  autonomie_jours integer, date_rupture date, date_rupture_min date, date_rupture_max date, date_entame_securite date,
  detail jsonb NOT NULL                               -- projection mensuelle par famille, ruptures par lot, couvertures par lot d'animaux
);

CREATE TABLE plan.scenario (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  inventaire_id bigint NOT NULL REFERENCES stock.inventaire(id) ON DELETE CASCADE,
  libelle text NOT NULL, origine text NOT NULL CHECK (origine IN ('proposition','utilisateur')),
  statut text NOT NULL DEFAULT 'brouillon' CHECK (statut IN ('brouillon','retenu','archive')),
  retenu_le timestamptz, cree_par bigint, cree_le timestamptz NOT NULL DEFAULT now(), maj_le timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE plan.levier_ligne (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  scenario_id bigint NOT NULL REFERENCES plan.scenario(id) ON DELETE CASCADE, ordre smallint NOT NULL,
  levier_code text NOT NULL,                          -- 'achat_fourrage','concentre_substitution','decapitalisation','genisses','paturage','ratelier','reaffectation','correction_nutritionnelle','autre'
  famille_code text, aliment_reference_id bigint, lot_stock_source_id bigint, lot_animaux_id bigint,
  quantite numeric NOT NULL, unite text NOT NULL, parametre numeric, prix_unitaire numeric,
  date_effet date, gain_t_ms numeric, cout_eur numeric, commentaire text
);
```

## 5.6 Vues analytiques ajoutées (`valo`)

| Vue | Rôle |
|---|---|
| `valo.v_reference_bigds` | Quartiles par type de fourrage et campagne sur les deux GDS (RC-3, repli) |
| `valo.v_historique_elevage` | Série par élevage × type × paramètre × campagne (RC-4) |
| `valo.v_resultat_large` | Existante ; étendue aux paramètres calculés (ratios RC-1) |
| `valo.v_couverture_lot` | Dernier calcul RC-9 par lot d'animaux et phase (matérialisé dans `plan.bilan.detail`) |
| `valo.v_pilotage` | Indicateurs EF-M10-04 |

Les vues de référence excluent les élevages sans consentement `references` actif, les bulletins `anomalie` et les analyses `exclu_references`. Les vues matérialisées sont rafraîchies chaque nuit et après chaque publication (job).

## 5.7 Correspondance Laravel

| Élément | Implémentation |
|---|---|
| Schémas et tables existants | Migration `0001_socle_document02` qui exécute les scripts SQL du dossier `sql/` (idempotents) |
| Nouvelles tables | Migrations Laravel en SQL brut (`DB::unprepared`) pour conserver les contraintes PostgreSQL (EXCLUDE, CHECK, GIST) ; une migration par schéma |
| Modèles Eloquent | `protected $table = 'app.user'` etc. ; casts `jsonb` → array ; `date` ; scopes de cloisonnement (chapitre 6) |
| Référentiels | Seeders lisant des fichiers CSV versionnés dans `database/seeders/ref/` (une ligne par valeur, avec source et statut) |
| Calculs | Classes PHP pures sous `app/Domain/` (sans dépendance Eloquent), alimentées par des DTO ; résultats persistés dans `plan.bilan` et `stock.lot_stock.*_calc` |
