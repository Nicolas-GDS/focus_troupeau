<?php

namespace Database\Seeders\Ref;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Référentiel minéraux × troubles sanitaires (chapitre 11, § 5.3, RC-1.4) chargé depuis les CSV versionnés de
 * database/seeders/ref (§ 5.7) : complément bibliographique, troubles, liens de compatibilité.
 *
 * Format des CSV : UTF-8, « ; », en-têtes en première ligne, champ vide = NULL, listes « A|B ».
 * Rejouable : les lignes existantes sont mises à jour, jamais dupliquées ; une référence bibliographique déjà
 * présente (script 02) n'est pas modifiée. Exécuté par la migration 2026_10_15_100300 : le référentiel existe
 * dans toute installation, y compris en production.
 */
class TroublesSanitairesSeeder extends Seeder
{
    public const VERSION = '2';

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->references();
            $this->troubles();
            $this->compatibilites();
        });
    }

    private function references(): void
    {
        foreach (self::lire('reference_biblio_complement.csv') as $l) {
            if (DB::table('ref.reference_biblio')->where('cle', $l['cle'])->exists()) {
                continue;
            }

            DB::table('ref.reference_biblio')->insert([
                'cle' => $l['cle'], 'auteurs' => $l['auteurs'], 'annee' => $l['annee'], 'titre' => $l['titre'],
                'source' => $l['source'], 'url' => $l['url'], 'type_source' => $l['type_source'],
                'statut_verif' => $l['statut_verif'], 'date_consultation' => $l['date_consultation'],
                'commentaire' => $l['commentaire'],
            ]);
        }
    }

    private function troubles(): void
    {
        foreach (self::lire('trouble_sanitaire.csv') as $l) {
            DB::table('ref.trouble_sanitaire')->updateOrInsert(['code' => $l['code']], [
                'libelle' => $l['libelle'],
                'famille' => $l['famille'],
                'especes' => self::tableau($l['especes']),
                'categories_cibles' => self::tableau($l['categories_cibles']),
                'type_evenement_existant' => $l['type_evenement_existant'],
                'description' => $l['description'],
                'reference_id' => self::reference($l['reference_cle']),
                'statut_epistemique' => $l['statut_epistemique'] ?? 'publie',
                'version_creation' => self::VERSION,
            ]);
        }
    }

    private function compatibilites(): void
    {
        foreach (self::lire('compatibilite_parametre_trouble.csv') as $l) {
            DB::table('ref.compatibilite_parametre_trouble')->upsert([[
                'parametre_code' => $l['parametre_code'],
                'sens' => $l['sens'],
                'trouble_code' => $l['trouble_code'],
                'especes' => self::tableau($l['espece_code']),
                'categories' => self::tableau($l['categorie_code']),
                'force' => (int) $l['force'],
                'mecanisme' => $l['mecanisme'],
                'facteurs_modulateurs' => $l['facteurs_modulateurs'],
                'repere_quantitatif' => $l['repere_quantitatif'],
                'reference_id' => self::reference($l['reference_cle']),
                'statut_epistemique' => $l['statut_epistemique'] ?? 'publie',
                'commentaire' => $l['commentaire'],
                'version_creation' => self::VERSION,
            ]], ['parametre_code', 'sens', 'trouble_code', 'especes', 'categories'], [
                'force', 'mecanisme', 'facteurs_modulateurs', 'repere_quantitatif', 'reference_id', 'statut_epistemique', 'commentaire',
            ]);
        }
    }

    /**
     * @return list<array<string, string|null>>
     */
    public static function lire(string $fichier): array
    {
        $chemin = database_path('seeders/ref/'.$fichier);
        $poignee = fopen($chemin, 'r');

        if ($poignee === false) {
            throw new RuntimeException("Référentiel introuvable : {$chemin}");
        }

        $entetes = null;
        $lignes = [];

        while (($champs = fgetcsv($poignee, separator: ';', enclosure: "\x01", escape: '')) !== false) {
            if ($champs === [null]) {
                continue;
            }

            $champs = array_map(fn ($c) => ($c = trim((string) $c)) === '' ? null : $c, $champs);

            if ($entetes === null) {
                $entetes = array_map(fn ($e) => (string) $e, $champs);

                continue;
            }

            if (count($champs) !== count($entetes)) {
                throw new RuntimeException("{$fichier} : ligne de ".count($champs).' champs pour '.count($entetes).' en-têtes.');
            }

            $lignes[] = array_combine($entetes, $champs);
        }

        fclose($poignee);

        return $lignes;
    }

    /** Liste « A|B » en tableau PostgreSQL littéral ; vide = NULL (toutes). */
    private static function tableau(?string $liste): ?string
    {
        return $liste === null ? null : '{'.implode(',', array_map('trim', explode('|', $liste))).'}';
    }

    private static function reference(?string $cle): ?int
    {
        if ($cle === null) {
            return null;
        }

        $id = DB::table('ref.reference_biblio')->where('cle', $cle)->value('id');

        if ($id === null) {
            throw new RuntimeException("Référence bibliographique inconnue : {$cle}");
        }

        return (int) $id;
    }
}
