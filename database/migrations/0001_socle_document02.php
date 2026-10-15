<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Socle de données du document 02 (schémas ref, prive, fourrage, sig, valo) — chapitre 5.1 et 5.7.
 *
 * Les scripts database/sql/01 et 02 sont exécutés tels quels et ne sont jamais modifiés.
 * Seules les lignes de méta-commandes psql (« \set ON_ERROR_STOP on ») sont écartées à la lecture,
 * car elles ne sont pas du SQL ; l'arrêt sur erreur est assuré par la transaction de la migration.
 *
 * Les scripts n'étant pas rejouables (CREATE TABLE et INSERT sans garde), la migration l'est :
 * chaque script n'est exécuté que si son témoin est absent. Le script 03 (chargement de la
 * campagne, données nominatives) n'est pas versionné et reste hors migration.
 */
return new class extends Migration
{
    /** @var array<string, string> script => requête témoin (vraie si le script est déjà appliqué) */
    private const SCRIPTS = [
        '01_schema_fourrages_gds24.sql' => "SELECT to_regclass('fourrage.elevage') IS NOT NULL AS applique",
        '02_referentiels_interpretation.sql' => 'SELECT EXISTS (SELECT 1 FROM ref.seuil_interpretation) AS applique',
    ];

    public function up(): void
    {
        foreach (self::SCRIPTS as $fichier => $temoin) {
            if ($this->dejaApplique($temoin)) {
                continue;
            }

            DB::unprepared($this->lireScript($fichier));
        }
    }

    /**
     * Supprime le socle et toutes ses données (y compris celles du script 03) : interdit en production.
     */
    public function down(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Le socle du document 02 ne peut pas être supprimé en production.');
        }

        DB::unprepared('DROP SCHEMA IF EXISTS valo, sig, fourrage, prive, ref CASCADE;');
    }

    private function dejaApplique(string $temoin): bool
    {
        // Le témoin du script 02 interroge une table du script 01 : sans elle, rien n'est appliqué.
        if (DB::selectOne("SELECT to_regclass('ref.seuil_interpretation') IS NULL AS absent")->absent) {
            return false;
        }

        return (bool) DB::selectOne($temoin)->applique;
    }

    private function lireScript(string $fichier): string
    {
        $chemin = database_path('sql/'.$fichier);
        $contenu = file_get_contents($chemin);

        if ($contenu === false) {
            throw new RuntimeException("Script du socle introuvable : {$chemin}");
        }

        return (string) preg_replace('/^\s*\\\\.*$/m', '', $contenu);
    }
};
