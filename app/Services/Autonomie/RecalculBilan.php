<?php

namespace App\Services\Autonomie;

use App\Jobs\CalculerBilan;
use Illuminate\Support\Facades\DB;

/**
 * Recalcul du bilan mémorisé après un changement d'inventaire, de lot, de mouvement, de lot d'animaux, de phase ou de
 * ration (EF-M6-05, T-20) : un job CalculerBilan par inventaire touché, dispatché une fois, à la fin de la transaction
 * (chapitre 6.4).
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
            CalculerBilan::dispatch($id);
        }
    }
}
