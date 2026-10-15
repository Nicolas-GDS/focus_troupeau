<?php

namespace App\Console\Commands;

use App\Models\App\User;
use App\Services\Import\ImportBulletinService;
use Illuminate\Console\Command;

/**
 * Import en lot d'un dossier de bulletins PDF (recette J0, T-01 ; EF-M9-01). Même circuit que l'écran E19.
 */
class ImporterBulletins extends Command
{
    protected $signature = 'bulletins:importer
        {dossier : Dossier contenant les PDF LANO}
        {--gds= : GDS du dépôt (24, 47)}
        {--technicien= : Adresse e-mail du technicien déposant}
        {--sync : Traiter immédiatement au lieu de passer par la file d\'attente}';

    protected $description = 'Importe tous les bulletins PDF d\'un dossier (circuit de l\'écran E19)';

    public function handle(ImportBulletinService $service): int
    {
        $dossier = (string) $this->argument('dossier');
        $technicien = User::query()->where('email', (string) $this->option('technicien'))->first();
        $gds = (int) $this->option('gds');

        if (! is_dir($dossier) || $technicien === null || $gds === 0) {
            $this->error('Dossier, --gds et --technicien (compte existant) sont obligatoires.');

            return self::FAILURE;
        }

        if (! $technicien->gds()->whereKey($gds)->exists()) {
            $this->error("Le technicien n'est pas rattaché au GDS {$gds}.");

            return self::FAILURE;
        }

        $fichiers = glob(rtrim($dossier, '/').'/*.{pdf,PDF}', GLOB_BRACE) ?: [];
        $statuts = [];

        foreach ($this->output->progressIterate($fichiers) as $fichier) {
            $import = $service->deposer($fichier, basename($fichier), $gds, $technicien);

            if ($this->option('sync') && $import->statut === 'recu') {
                $import = $service->traiter($import);
            }

            $statuts[] = $import->fresh()->statut ?? $import->statut;
        }

        $this->newLine(2);
        $this->table(['Statut', 'Bulletins'], collect($statuts)->countBy()->map(fn ($n, $s) => [$s, $n])->values()->all());
        $this->info(count($fichiers).' fichier(s) déposé(s).');

        return self::SUCCESS;
    }
}
