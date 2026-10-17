<?php

namespace App\Jobs;

use App\Models\Stock\Inventaire;
use App\Services\Autonomie\AlerteAutonomie;
use App\Services\Autonomie\BilanService;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Recalcul du bilan d'un inventaire (chapitre 6.4, EF-M6-05) : écrit plan.bilan puis compare la rupture au seuil
 * d'alerte (EF-M8-03). Un seul job en attente par inventaire ; un changement survenu pendant le calcul en relance un.
 */
class CalculerBilan implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $inventaireId) {}

    public function uniqueId(): string
    {
        return (string) $this->inventaireId;
    }

    public function handle(BilanService $bilans, AlerteAutonomie $alertes): void
    {
        $inventaire = Inventaire::query()->withoutGlobalScopes()->find($this->inventaireId);

        if ($inventaire !== null) {
            $bilans->enregistrer($inventaire);
            $alertes->evaluer($inventaire);
        }
    }
}
