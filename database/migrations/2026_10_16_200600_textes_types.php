<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lot L3, étape 5 — textes types (EF-M10-03 : « mentions de limites, gabarits de recommandations… », § 8.5 : les
 * textes générés sont stockés dans les référentiels, pas dans le code). Ajout : ref.texte_type, versionnée comme les
 * autres référentiels ; édition dans E24 au lot L7.
 *
 * Textes initiaux des mentions de limites de la fiche de visite (§ 8.3 x-limites, § 8.4), repris du document 04,
 * § 3.1 (« ce qui n'est pas dosé, ce que la fiche ne remplace pas ») et de la décision du 16/10/2026 (§ 9.5).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE ref.texte_type (
  id serial PRIMARY KEY,
  code text NOT NULL,
  texte text NOT NULL,
  source text,
  version_creation text NOT NULL REFERENCES ref.version_referentiel(numero),
  version_fin text REFERENCES ref.version_referentiel(numero)
);
CREATE UNIQUE INDEX ON ref.texte_type (code) WHERE version_fin IS NULL;

INSERT INTO ref.texte_type (code, texte, source, version_creation) VALUES
 ('fiche.limites.ne_remplace_pas',
  'Cette fiche ne remplace ni un calcul de ration ni un diagnostic vétérinaire.',
  'Document 04, § 3.1 (honnêteté sur les limites)', '4'),
 ('fiche.limites.non_doses',
  'Éléments non dosés dans les fourrages de la visite :',
  'Document 04, § 3.1 (ce qui n''est pas dosé)', '4'),
 ('fiche.limites.repere_a_valider',
  'Les valeurs marquées « repère à valider » sont lues sur des repères professionnels en cours de validation par le vétérinaire conseil du GDS.',
  'Décision du 16/10/2026 (§ 9.5)', '4');
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS ref.texte_type;');
    }
};
