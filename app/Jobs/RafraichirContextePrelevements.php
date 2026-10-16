<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Rafraîchit le contexte pédo-géologique des prélèvements (valo.mv_prelevement_contexte, socle) dont dépend la
 * population lithologique des références (RC-3) : chaque nuit et après chaque publication (§ 5.6). Le
 * rafraîchissement concurrent laisse la vue lisible pendant le calcul (index unique du socle sur prelevement_id).
 */
class RafraichirContextePrelevements implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY valo.mv_prelevement_contexte');
    }
}
