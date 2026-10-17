<?php

namespace App\Jobs;

use App\Models\App\Convention;
use App\Services\JournalService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Passage automatique des conventions à « echue » le lendemain de leur date de fin (§ 2.4, EF-M0-06, D12).
 * Tâche quotidienne, hors session : elle travaille sur toutes les conventions de l'instance, explicitement
 * hors cloisonnement. Le rappel au technicien avant l'échéance : RappelConventions.
 */
class ClotureConventions implements ShouldQueue
{
    use Queueable;

    public function handle(JournalService $journal): void
    {
        Convention::query()
            ->withoutGlobalScopes()
            ->where('statut', Convention::STATUT_ACTIVE)
            ->whereDate('date_fin', '<', today())
            ->each(function (Convention $convention) use ($journal): void {
                $convention->update(['statut' => Convention::STATUT_ECHUE]);

                $journal->ecrire('convention_echue', null, 'app.convention:'.$convention->id, [
                    'date_fin' => $convention->date_fin->toDateString(),
                ], $convention->elevage_id);
            });
    }
}
