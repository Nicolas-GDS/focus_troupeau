<?php

namespace App\Jobs;

use App\Models\App\ImportBulletin;
use App\Services\Import\ImportBulletinService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Extraction et import d'un bulletin déposé (EF-M9-01, § 6.4), en file d'attente.
 * Hors session : le dépôt est relu hors cloisonnement (son GDS a été contrôlé au dépôt).
 */
class ExtraireBulletinLano implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $importId) {}

    public function handle(ImportBulletinService $service): void
    {
        $service->traiter(ImportBulletin::query()->withoutGlobalScopes()->findOrFail($this->importId));
    }

    /**
     * Un échec ne remplace jamais un résultat déjà obtenu : seul un dépôt resté « reçu » ou « en extraction » passe en
     * erreur. Le détail technique va dans le journal applicatif, pas à l'écran.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('Import de bulletin interrompu', ['import_id' => $this->importId, 'exception' => $exception]);

        ImportBulletin::query()->withoutGlobalScopes()
            ->whereKey($this->importId)
            ->whereIn('statut', ['recu', 'en_extraction'])
            ->update([
                'statut' => 'erreur',
                'erreur' => 'Import interrompu par une erreur technique. Redéposez le bulletin ; si l\'erreur persiste, prévenez l\'administrateur.',
            ]);
    }
}
