<?php

namespace Database\Seeders\Ref;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Référentiels du dossier (lot L3, § 5.3, RC-1.5, EF-M1-04, EF-M1-05, EF-M1-07, EF-M2-05) chargés depuis les CSV
 * versionnés de database/seeders/ref/dossier, transcrits de la présentation « L'approche globale de la santé du
 * troupeau — exemple EARL Forgeneuf » (GDS 24, 2026) : statut [C] (consensus professionnel), non validés
 * (« repère à valider » à l'affichage, § 9.5).
 *
 * Format des CSV : celui de TroublesSanitairesSeeder (UTF-8, « ; », champ vide = NULL, listes « A|B »).
 * Conditions des lectures combinées : « MESURE:lecture » (bas, normal, haut) ou « MESURE<valeur » (<, <=, >, >=),
 * séparées par « | ». Rejouable : les lignes existantes sont mises à jour, jamais dupliquées. Exécuté par la
 * migration 2026_10_16_200400 : le référentiel existe dans toute installation, y compris en production.
 */
class ReferentielsDossierSeeder extends Seeder
{
    public const VERSION = '4';

    private const STATUT = 'consensus_professionnel';

    private const FAMILLES_CAUSE = ['immunite_oligo', 'metabolisme_mineral', 'metabolisme_energetique', 'confort', 'infectieux'];

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->references();
            $this->mesuresTerrain();
            $this->grilleLecture();
            $this->profilsSanguins();
            $this->bilanSanitaire();
            $this->croisement();
            $this->eau();
        });
    }

    private function references(): void
    {
        foreach (self::lire('reference_biblio.csv') as $l) {
            DB::table('ref.reference_biblio')->updateOrInsert(['cle' => $l['cle']], [
                'auteurs' => $l['auteurs'], 'annee' => $l['annee'], 'titre' => $l['titre'], 'source' => $l['source'],
                'url' => $l['url'], 'type_source' => $l['type_source'], 'statut_verif' => $l['statut_verif'],
                'date_consultation' => $l['date_consultation'], 'commentaire' => $l['commentaire'],
            ]);
        }
    }

    private function mesuresTerrain(): void
    {
        foreach (self::lire('mesure_terrain.csv') as $l) {
            DB::table('ref.mesure_terrain')->updateOrInsert(['code' => $l['code']], [
                'libelle' => $l['libelle'], 'unite' => $l['unite'], 'decimales' => (int) $l['decimales'],
                'ordre' => (int) $l['ordre'], 'description' => $l['description'],
            ]);
        }

        foreach (self::lire('repere_mesure_terrain.csv') as $l) {
            DB::table('ref.repere_mesure_terrain')->updateOrInsert(
                ['mesure_code' => $l['mesure_code'], 'espece_code' => $l['espece_code'], 'stade' => $l['stade'], 'version_creation' => self::VERSION],
                [
                    'borne_basse' => $l['borne_basse'], 'borne_haute' => $l['borne_haute'],
                    'reference_id' => self::reference($l['reference_cle']), 'statut_epistemique' => self::STATUT,
                    'commentaire' => $l['commentaire'],
                ],
            );
        }
    }

    private function grilleLecture(): void
    {
        foreach (self::lire('grille_lecture_mesure.csv') as $l) {
            DB::table('ref.grille_lecture_mesure')->updateOrInsert(['code' => $l['code'], 'version_creation' => self::VERSION], [
                'ordre' => (int) $l['ordre'],
                'conditions' => json_encode(self::conditions((string) $l['conditions']), JSON_THROW_ON_ERROR),
                'libelle_propose' => $l['libelle_propose'], 'espece_code' => 'BOV',
                'reference_id' => self::reference($l['reference_cle']), 'statut_epistemique' => self::STATUT,
                'commentaire' => $l['commentaire'],
            ]);
        }
    }

    private function profilsSanguins(): void
    {
        foreach (self::lire('repere_analyse_animale.csv') as $l) {
            DB::table('ref.repere_analyse_animale')->updateOrInsert(
                ['element' => $l['element'], 'matrice' => $l['matrice'], 'espece_code' => $l['espece_code'], 'version_creation' => self::VERSION],
                [
                    'borne_basse' => $l['borne_basse'], 'borne_haute' => $l['borne_haute'], 'unite' => $l['unite'],
                    'reference_id' => self::reference($l['reference_cle']), 'statut_epistemique' => self::STATUT,
                    'commentaire' => $l['commentaire'],
                ],
            );
        }
    }

    private function bilanSanitaire(): void
    {
        foreach (self::lire('indicateur_sanitaire.csv') as $l) {
            DB::table('ref.indicateur_sanitaire')->updateOrInsert(['code' => $l['code']], [
                'libelle' => $l['libelle'], 'unite' => $l['unite'], 'mode' => $l['mode'], 'ordre' => (int) $l['ordre'],
                'description' => $l['description'],
            ]);
        }

        foreach (self::lire('seuil_bilan_sanitaire.csv') as $l) {
            DB::table('ref.seuil_bilan_sanitaire')->updateOrInsert(
                ['indicateur_code' => $l['indicateur_code'], 'espece_code' => $l['espece_code'], 'systeme' => $l['systeme'], 'version_creation' => self::VERSION],
                [
                    's1' => $l['s1'], 's2' => $l['s2'], 'sens' => 'haut',
                    'reference_id' => self::reference($l['reference_cle']), 'statut_epistemique' => self::STATUT,
                    'commentaire' => $l['commentaire'],
                ],
            );
        }
    }

    private function croisement(): void
    {
        foreach (self::lire('trouble_croisement.csv') as $l) {
            DB::table('ref.trouble_croisement')->updateOrInsert(['code' => $l['code']], [
                'libelle' => $l['libelle'], 'ordre' => (int) $l['ordre'],
                'indicateurs_sanitaires' => self::tableau($l['indicateurs_sanitaires']),
                'troubles_sanitaires' => self::tableau($l['troubles_sanitaires']),
                'version_creation' => self::VERSION,
            ]);
        }

        $reference = self::reference('NBVC_BSE');

        foreach (self::lire('grille_trouble_cause.csv') as $l) {
            foreach (self::FAMILLES_CAUSE as $famille) {
                DB::table('ref.grille_trouble_cause')->updateOrInsert(
                    ['trouble_code' => $l['trouble_code'], 'famille_cause' => $famille, 'version_creation' => self::VERSION],
                    ['force' => (int) $l[$famille], 'reference_id' => $reference, 'statut_epistemique' => self::STATUT],
                );
            }
        }
    }

    private function eau(): void
    {
        foreach (self::lire('parametre_eau.csv') as $l) {
            DB::table('ref.parametre')->updateOrInsert(['code' => $l['code']], [
                'libelle' => $l['libelle'], 'famille_code' => 'EAU', 'unite' => $l['unite'], 'base' => 'sans_objet',
                'decimales' => (int) $l['decimales'], 'definition' => $l['definition'], 'ordre' => (int) $l['ordre'],
            ]);
        }

        foreach (self::lire('seuil_eau.csv') as $l) {
            DB::table('ref.seuil_interpretation')->updateOrInsert(
                ['parametre_code' => $l['parametre_code'], 'classe' => $l['classe'], 'nature' => 'eau', 'version_creation' => self::VERSION],
                [
                    'borne_min' => $l['borne_min'], 'borne_max' => $l['borne_max'], 'gravite' => (int) $l['gravite'],
                    'sens' => $l['sens'], 'message' => $l['message'], 'reference_id' => self::reference($l['reference_cle']),
                    'statut_epistemique' => $l['statut_epistemique'], 'commentaire' => $l['commentaire'],
                    'actif' => true, 'valide' => false,
                ],
            );
        }
    }

    /**
     * « GLYCEMIE<45|BOH:haut » → [{mesure: GLYCEMIE, op: <, valeur: 45}, {mesure: BOH, lecture: haut}].
     *
     * @return list<array<string, string|float>>
     */
    public static function conditions(string $texte): array
    {
        $conditions = [];

        foreach (explode('|', $texte) as $morceau) {
            if (preg_match('/^([A-Z0-9_]+):(bas|normal|haut)$/', trim($morceau), $m) === 1) {
                $conditions[] = ['mesure' => $m[1], 'lecture' => $m[2]];
            } elseif (preg_match('/^([A-Z0-9_]+)(<=|>=|<|>)(-?\d+(?:\.\d+)?)$/', trim($morceau), $m) === 1) {
                $conditions[] = ['mesure' => $m[1], 'op' => $m[2], 'valeur' => (float) $m[3]];
            } else {
                throw new RuntimeException("Condition de lecture illisible : « {$morceau} »");
            }
        }

        return $conditions;
    }

    /**
     * @return list<array<string, string|null>>
     */
    private static function lire(string $fichier): array
    {
        return TroublesSanitairesSeeder::lire('dossier/'.$fichier);
    }

    /** Liste « A|B » en tableau PostgreSQL littéral ; vide = tableau vide. */
    private static function tableau(?string $liste): string
    {
        return $liste === null ? '{}' : '{'.implode(',', array_map('trim', explode('|', $liste))).'}';
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
