<?php

namespace Database\Seeders;

use App\Models\App\ElevageAcces;
use App\Models\App\User;
use App\Models\Fourrage\Elevage;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Comptes du test de charge (T-42, ENF-PERF-01) : 50 comptes éleveurs `charge.NN@charge.test`, rattachés tour à tour
 * aux élevages des jeux J1, J2 et J3, pour simuler 50 utilisateurs simultanés avec des sessions distinctes.
 * Développement et recette uniquement ; rejouable. Lancer : php artisan db:seed --class=JeuChargeSeeder.
 */
class JeuChargeSeeder extends Seeder
{
    public const NOMBRE = 50;

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Les comptes de charge ne doivent pas être créés en production.');
        }
        $elevages = Elevage::query()->withoutGlobalScopes()->whereIn('code_pseudo', [JeuJ1Seeder::CODE, JeuJ2Seeder::CODE, 'ELV-0001'])->orderBy('code_pseudo')->pluck('id')->all();
        if (count($elevages) < 3) {
            throw new RuntimeException('Charger d\'abord les jeux J1, J2 et J3 (php artisan db:seed).');
        }

        for ($i = 1; $i <= self::NOMBRE; $i++) {
            $user = User::query()->firstOrCreate(['email' => sprintf('charge.%02d@charge.test', $i)],
                ['nom' => 'Charge', 'prenom' => sprintf('%02d', $i), 'password' => (string) config('authentification.mot_de_passe_j4')]);
            $user->forceFill(['notice_rgpd_acceptee_le' => now(), 'actif' => true])->save();
            $user->syncRoles(['eleveur']);
            $user->elevages()->syncWithoutDetaching([$elevages[$i % 3] => ['role' => ElevageAcces::ROLE_ELEVEUR]]);
        }
    }
}
