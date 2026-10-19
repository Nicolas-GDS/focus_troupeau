<?php

namespace App\Jobs;

use App\Services\Exploitation\ConservationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Purge nocturne des données au-delà de leur durée de conservation (ENF-RGPD-05).
 */
class PurgerConservation implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function handle(ConservationService $conservation): void
    {
        $conservation->purger();
    }
}
