<?php

namespace App\Services\Autonomie;

use App\Models\Stock\Inventaire;
use Illuminate\Support\Facades\DB;

/**
 * Recalcul du bilan mémorisé après un changement d'inventaire, de lot, de mouvement, de lot d'animaux, de phase ou de
 * ration (EF-M6-05, T-20) : les inventaires touchés sont recalculés une fois, à la fin de la transaction.
 */
class RecalculBilan
{
    /** @var array<int, true> */
    private array $enAttente = [];

    public function planifier(?int $inventaireId): void
    {
        if ($inventaireId === null) {
            return;
        }

        $premier = $this->enAttente === [];
        $this->enAttente[$inventaireId] = true;

        if ($premier) {
            DB::afterCommit(fn () => $this->executer());
        }
    }

    public function executer(): void
    {
        $ids = array_keys($this->enAttente);
        $this->enAttente = [];

        foreach ($ids as $id) {
            $inventaire = Inventaire::query()->withoutGlobalScopes()->find($id);
            if ($inventaire !== null) {
                app(BilanService::class)->enregistrer($inventaire);
            }
        }
    }
}
