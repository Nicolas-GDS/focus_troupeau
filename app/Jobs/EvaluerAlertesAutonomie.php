<?php

namespace App\Jobs;

use App\Services\Autonomie\AlerteAutonomie;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Évaluation nocturne des alertes d'autonomie (EF-M8-03) : la date du jour avance, une rupture peut passer sous le
 * seuil sans qu'aucune saisie ne recalcule le bilan.
 */
class EvaluerAlertesAutonomie implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function handle(AlerteAutonomie $alertes): void
    {
        $alertes->evaluerTout();
    }
}
