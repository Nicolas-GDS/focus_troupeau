<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Données de développement : jeux d'essai synthétiques uniquement (§ 9.1).
     * Les référentiels du socle sont chargés par la migration 0001 ; les données réelles (script 03,
     * campagne 2025-2026) sont chargées à la main en recette, jamais par un seeder.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $this->call([JeuJ4Seeder::class, JeuJ3Seeder::class, JeuJ5Seeder::class]);
    }
}
