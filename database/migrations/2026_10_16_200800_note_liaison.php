<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lot L3, étape 6 — note de liaison vétérinaire (EF-M2-09 ; gabarit du document 04, § 4.1).
 *
 * - ajout : dossier.note_liaison.lot_animaux (« espèce et lot d'animaux concernés ») ;
 * - textes types : retour attendu (document 04), e-mails au vétérinaire et au technicien.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE dossier.note_liaison ADD COLUMN lot_animaux text;   -- ajout

INSERT INTO ref.texte_type (code, texte, source, version_creation) VALUES
 ('note_liaison.retour_attendu', 'Résultats des analyses animales (saisis dans la base), prescription éventuelle, date de point.', 'Document 04, § 4.1', '4'),
 ('notification.note_liaison.objet', 'Note de liaison du GDS : {elevage}', 'EF-M2-09', '4'),
 ('notification.note_liaison.ligne1', 'Le technicien du GDS vous adresse une note de liaison pour {elevage}, suite à la visite du {date}.', 'EF-M2-09', '4'),
 ('notification.note_liaison.ligne2', 'Elle présente les troubles déclarés, les alertes des fourrages et la question posée.', 'EF-M2-09', '4'),
 ('notification.note_liaison.ligne3', 'Vous pouvez la consulter et y répondre depuis votre espace.', 'EF-M2-09', '4'),
 ('notification.note_liaison_retour.objet', 'Retour du vétérinaire sur la note de liaison : {elevage}', 'EF-M2-09', '4'),
 ('notification.note_liaison_retour.ligne1', 'Le vétérinaire a répondu à la note de liaison du {date} pour {elevage}.', 'EF-M2-09', '4'),
 ('notification.note_liaison_retour.ligne2', 'Sa réponse est enregistrée dans le dossier de l''élevage.', 'EF-M2-09', '4'),
 ('notification.note_liaison_retour.ligne3', 'Pensez à saisir les résultats d''analyses animales transmis.', 'EF-M2-09', '4');
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DELETE FROM ref.texte_type WHERE code LIKE 'notification.note_liaison%' OR code = 'note_liaison.retour_attendu';
ALTER TABLE dossier.note_liaison DROP COLUMN IF EXISTS lot_animaux;
SQL);
    }
};
