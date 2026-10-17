<?php

namespace App\Observers;

use App\Models\Plan\LotAnimaux;
use App\Models\Plan\Phase;
use App\Models\Plan\Ration;
use App\Models\Plan\RationComposant;
use App\Models\Stock\Inventaire;
use App\Models\Stock\LotStock;
use App\Models\Stock\Mouvement;
use App\Services\Autonomie\RecalculBilan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Déclenche le recalcul du bilan de l'inventaire concerné quand un objet des stocks ou du troupeau change (EF-M6-05).
 */
class BilanObservateur
{
    public function saved(Model $modele): void
    {
        app(RecalculBilan::class)->planifier($this->inventaireDe($modele));
    }

    public function deleted(Model $modele): void
    {
        app(RecalculBilan::class)->planifier($this->inventaireDe($modele));
    }

    private function inventaireDe(Model $modele): ?int
    {
        $phaseVersInventaire = fn (?int $phaseId) => DB::table('plan.lot_animaux')
            ->where('id', DB::table('plan.phase')->where('id', $phaseId)->value('lot_animaux_id'))->value('inventaire_id');
        $id = match (true) {
            $modele instanceof Inventaire => $modele->id,
            $modele instanceof LotStock, $modele instanceof LotAnimaux => $modele->inventaire_id,
            $modele instanceof Mouvement => DB::table('stock.lot_stock')->where('id', $modele->lot_stock_id)->value('inventaire_id'),
            $modele instanceof Phase => DB::table('plan.lot_animaux')->where('id', $modele->lot_animaux_id)->value('inventaire_id'),
            $modele instanceof Ration => $phaseVersInventaire($modele->phase_id),
            $modele instanceof RationComposant => $phaseVersInventaire((int) DB::table('plan.ration')->where('id', $modele->ration_id)->value('phase_id')),
            default => null,
        };

        return $id === null ? null : (int) $id;
    }
}
