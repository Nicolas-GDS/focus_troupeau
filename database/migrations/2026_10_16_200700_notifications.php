<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lot L3, étape 6 — notifications (EF-M8-01, EF-M8-02, EF-M8-05, § 5.2 app.notification).
 *
 * - ajout : app.notification.lien (lien de l'e-mail : « objet, trois lignes, lien ») et tentatives ;
 * - textes types des notifications (§ 8.5 : textes générés stockés dans les référentiels, relus par le comité
 *   technique), avec des variables entre accolades remplacées à l'envoi.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE app.notification
  ADD COLUMN lien text,                               -- ajout
  ADD COLUMN tentatives smallint NOT NULL DEFAULT 0;  -- ajout
CREATE INDEX ON app.notification (user_id, cree_le);
CREATE INDEX ON app.notification (reference_objet);

INSERT INTO ref.texte_type (code, texte, source, version_creation) VALUES
 ('notification.fiche_publiee.objet', 'Votre fiche de visite du {date} est disponible', 'EF-M8-01', '4'),
 ('notification.fiche_publiee.ligne1', 'La synthèse de la visite du {date} pour {elevage} est publiée.', 'EF-M8-01', '4'),
 ('notification.fiche_publiee.ligne2', 'Vous y trouverez les messages principaux, votre plan d''action et les contrôles à venir.', 'EF-M8-01', '4'),
 ('notification.fiche_publiee.ligne3', 'Consultez-la dans votre espace et téléchargez la fiche en PDF.', 'EF-M8-01', '4'),
 ('notification.resultat_publie.objet', 'Un nouveau résultat d''analyse est disponible', 'EF-M8-01', '4'),
 ('notification.resultat_publie.ligne1', 'L''interprétation du bulletin {bulletin} pour {elevage} est publiée.', 'EF-M8-01', '4'),
 ('notification.resultat_publie.ligne2', 'Elle précise la lecture du lot et son utilisation conseillée.', 'EF-M8-01', '4'),
 ('notification.resultat_publie.ligne3', 'Consultez-la dans votre espace.', 'EF-M8-01', '4'),
 ('notification.copie_technicien.prefixe', 'Copie technicien — ', 'EF-M8-01', '4'),
 ('notification.niveau3.sms', '{marque} : alerte de niveau 3 pour {elevage} ({objet}). {consigne} Contact : {contact}.', 'EF-M8-02 (texte court : lot concerné, consigne immédiate, contact)', '4');
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DELETE FROM ref.texte_type WHERE code LIKE 'notification.%';
ALTER TABLE app.notification DROP COLUMN IF EXISTS lien, DROP COLUMN IF EXISTS tentatives;
SQL);
    }
};
