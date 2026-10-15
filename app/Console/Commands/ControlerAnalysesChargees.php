<?php

namespace App\Console\Commands;

use App\Models\Fourrage\Analyse;
use App\Services\Import\ImportBulletinService;
use Illuminate\Console\Command;

/**
 * Applique les ratios (RC-1) et le contrôle de plausibilité (RC-2) aux analyses chargées directement en base
 * (script 03 de la campagne, en recette), restées au statut « recu ».
 */
class ControlerAnalysesChargees extends Command
{
    protected $signature = 'bulletins:controler-charges';

    protected $description = 'Contrôle (ratios, plausibilité) des analyses chargées en base au statut « recu »';

    public function handle(ImportBulletinService $service): int
    {
        $analyses = Analyse::query()->withoutGlobalScopes()->where('statut', Analyse::STATUT_RECU)->orderBy('id')->get();
        $statuts = [];

        foreach ($this->output->progressIterate($analyses) as $analyse) {
            $statuts[] = $service->controlerAnalyseChargee($analyse);
        }

        $this->newLine(2);
        $this->table(['Statut', 'Analyses'], collect($statuts)->countBy()->map(fn ($n, $s) => [$s, $n])->values()->all());

        return self::SUCCESS;
    }
}
