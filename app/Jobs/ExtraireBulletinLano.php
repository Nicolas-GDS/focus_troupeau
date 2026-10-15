<?php

namespace App\Jobs;

use App\Models\App\ImportBulletin;
use App\Services\Import\ImportBulletinService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
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

    public function failed(?Throwable $exception): void
    {
        ImportBulletin::query()->withoutGlobalScopes()->whereKey($this->importId)->update([
            'statut' => 'erreur',
            'erreur' => 'Import interrompu : '.($exception?->getMessage() ?? 'erreur inconnue'),
        ]);
    }
}
