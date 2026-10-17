<?php

namespace App\Models\Plan;

use App\Models\Concerns\CloisonneParElevage;
use App\Models\Dossier\EtiquetteAliment;
use App\Models\Stock\LotStock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Composant d'une ration (plan.ration_composant, EF-M5-02) : un lot de stock, une famille de stock, un aliment de
 * table, une étiquette (concentré ou minéral décrit par sa composition, décision du 17/10/2026) ou l'herbe pâturée ;
 * kg MS par animal et par jour (calculé depuis le brut et la MS quand le brut est saisi).
 *
 * @property int $id
 * @property int $ration_id
 * @property int $ordre
 * @property string $source
 * @property int|null $lot_stock_id
 * @property string|null $famille_code
 * @property int|null $aliment_reference_id
 * @property int|null $etiquette_id
 * @property string|null $quantite_brute_kg
 * @property string $kg_ms
 */
class RationComposant extends Model
{
    use CloisonneParElevage;

    public const COLONNE_ELEVAGE = 'ration_id';

    public const CHEMIN_ELEVAGE = [['plan.ration', 'id', 'phase_id'], ['plan.phase', 'id', 'lot_animaux_id'], ['plan.lot_animaux', 'id', 'elevage_id']];

    public const SOURCES = ['lot_stock', 'famille_stock', 'aliment_reference', 'etiquette', 'paturage'];

    public $timestamps = false;

    protected $table = 'plan.ration_composant';

    protected $fillable = ['ration_id', 'ordre', 'source', 'lot_stock_id', 'famille_code', 'aliment_reference_id', 'etiquette_id', 'quantite_brute_kg', 'kg_ms'];

    /** @return BelongsTo<LotStock, $this> */
    public function lotStock(): BelongsTo
    {
        return $this->belongsTo(LotStock::class);
    }

    /** @return BelongsTo<EtiquetteAliment, $this> */
    public function etiquette(): BelongsTo
    {
        return $this->belongsTo(EtiquetteAliment::class, 'etiquette_id');
    }
}
