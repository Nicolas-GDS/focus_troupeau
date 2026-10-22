<?php

namespace App\Services\Cloisonnement;

use App\Models\Fourrage\Elevage;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Règle de la convention (D12, EF-M0-06, § 6.3) : l'écriture dans stock.* et plan.* n'est ouverte que si
 * l'élevage a une convention active. Une convention dont la date de fin est passée est traitée comme échue
 * dès le lendemain, sans attendre la tâche planifiée qui met son statut à jour.
 */
class RegleConvention
{
    public function estActive(Elevage|string $elevage): bool
    {
        $id = $elevage instanceof Elevage ? $elevage->getKey() : $elevage;

        return $this->conventionsActives()->where('elevage_id', $id)->exists();
    }

    /**
     * Conventions actives ce jour, selon la même règle que estActive().
     */
    public function conventionsActives(): Builder
    {
        return DB::table('app.convention')
            ->where('statut', 'active')
            ->whereDate('date_debut', '<=', today())
            ->whereDate('date_fin', '>=', today());
    }
}
