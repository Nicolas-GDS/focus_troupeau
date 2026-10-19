<?php

namespace App\Console\Commands;

use App\Models\Dossier\Visite;
use App\Models\Fourrage\Elevage;
use Database\Seeders\JeuJ1Seeder;
use Database\Seeders\JeuJ2Seeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

/**
 * Audit d'accessibilité automatisé des écrans éleveur (T-41, ENF-ACC-01) sur les jeux J1 et J2 de l'environnement
 * courant : axe-core dans Chromium, 360 px et 1280 px ; échec si une violation grave ou critique, ou une page en
 * erreur. Développement et recette uniquement.
 */
class AuditerAccessibilite extends Command
{
    protected $signature = 'accessibilite:auditer {--base=http://nginx : adresse de l\'application vue du conteneur} {--sortie=storage/app/audit-accessibilite.json}';

    protected $description = 'Audit d\'accessibilité des écrans éleveur (axe-core, WCAG 2.1 AA) sur les jeux J1 et J2';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Audit réservé au développement et à la recette (jeux d\'essai J1 et J2).');

            return self::FAILURE;
        }
        $j1 = Elevage::query()->withoutGlobalScopes()->where('code_pseudo', JeuJ1Seeder::CODE)->value('id');
        $j2 = Elevage::query()->withoutGlobalScopes()->where('code_pseudo', JeuJ2Seeder::CODE)->value('id');
        if ($j1 === null || $j2 === null) {
            $this->error('Charger d\'abord les jeux J1 et J2 (php artisan db:seed).');

            return self::FAILURE;
        }
        $visite = Visite::query()->withoutGlobalScopes()->where('elevage_id', $j2)->value('id');
        $motDePasse = (string) config('authentification.mot_de_passe_j4');
        $dossier = array_map(fn (string $v) => "/elevages/{$j2}/{$v}", ['visites/'.$visite, 'fourrages', 'mineraux-matrice', 'eau', 'animaux', 'aliments', 'sanitaire', 'historique', 'plan-action']);
        $stocks = array_map(fn (string $v) => "/elevages/{$j1}/{$v}", ['stocks', 'troupeau', 'autonomie', 'approvisionnement', 'parametres']);

        $resultat = Process::timeout(900)->path(base_path())->run([
            'node', 'tests/accessibilite/audit.cjs', (string) $this->option('base'), (string) $this->option('sortie'),
            'anonyme::/connexion,/connexion/lien',
            JeuJ2Seeder::ELEVEUR.':'.$motDePasse.':'.implode(',', ['/accueil', ...$dossier, '/compte', '/compte/consentements']),
            JeuJ1Seeder::ELEVEUR.':'.$motDePasse.':'.implode(',', $stocks),
        ]);
        $this->output->write($resultat->output());
        $this->output->write($resultat->errorOutput());

        return $resultat->successful() ? self::SUCCESS : self::FAILURE;
    }
}
