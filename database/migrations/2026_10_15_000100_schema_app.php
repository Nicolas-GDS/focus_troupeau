<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Schéma app : GDS, comptes, rattachements, conventions, consentements, paramètres, notifications, journal
 * (chapitre 5.2, EF-M0-01, EF-M0-06, EF-M0-07, D10, ENF-SEC-08).
 *
 * Le DDL reprend le § 5.2. Écarts, signalés par « ajout » dans le code :
 * - extension citext (le type de app.user.email, absente du script 01) ;
 * - app.user : remember_token (session « se souvenir de moi » de Laravel), deux_facteurs_email (ENF-SEC-03)
 *   et notice_rgpd_acceptee_le (acceptation horodatée de la notice, ENF-RGPD-02) ;
 * - triggers maj_le sur le modèle du socle (ref.f_maj_horodatage) ;
 * - insertion des GDS 24 et 47 avant la reprise fourrage.elevage.gds_id = departement.
 * Les tables de spatie/laravel-permission (app.roles…) sont créées avec le paquet (étape 5).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE EXTENSION IF NOT EXISTS citext;   -- ajout : type de app.user.email
CREATE SCHEMA app;

CREATE TABLE app.gds (
  id            smallint PRIMARY KEY,                 -- 24, 47
  code          char(2) UNIQUE NOT NULL,
  nom           text NOT NULL,                        -- 'GDS Dordogne'
  couleur_locale text NOT NULL,                       -- '#006BA0' / '#D4820A' (charte)
  contact_email text, site_web text,
  actif         boolean NOT NULL DEFAULT true
);
-- ajout : les deux GDS de l'instance (§ 8.2 pour les couleurs locales)
INSERT INTO app.gds (id, code, nom, couleur_locale) VALUES
  (24, '24', 'GDS Dordogne', '#006BA0'),
  (47, '47', 'GDS Lot-et-Garonne', '#D4820A');

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
  remember_token text,                                -- ajout : « se souvenir de moi » (Laravel)
  deux_facteurs_email boolean NOT NULL DEFAULT false, -- ajout : code par e-mail activé (ENF-SEC-03)
  notice_rgpd_acceptee_le timestamptz,                -- ajout : acceptation horodatée de la notice (ENF-RGPD-02)
  cree_le timestamptz NOT NULL DEFAULT now(), maj_le timestamptz NOT NULL DEFAULT now()
);
CREATE TRIGGER trg_user_maj BEFORE UPDATE ON app."user" FOR EACH ROW EXECUTE FUNCTION ref.f_maj_horodatage();

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
  cree_le timestamptz NOT NULL DEFAULT now(), maj_le timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX ON app.convention (elevage_id, statut);
CREATE TRIGGER trg_convention_maj BEFORE UPDATE ON app.convention FOR EACH ROW EXECUTE FUNCTION ref.f_maj_horodatage();

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
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE fourrage.elevage DROP COLUMN IF EXISTS gds_id;
DROP SCHEMA IF EXISTS app CASCADE;
SQL);
    }
};
