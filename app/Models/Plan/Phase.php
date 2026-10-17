<?php

namespace App\Models\Plan;

use App\Models\Concerns\CloisonneParElevage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Phase datée d'un lot d'animaux (plan.phase, EF-M5-01) : stade physiologique, effectif ; les phases d'un lot ne se
 * chevauchent pas (contrainte d'exclusion).
 *
 * @property int $id
 * @property int $lot_animaux_id
 * @property string $stade
 * @property Carbon $date_debut
 * @property Carbon $date_fin
 * @property int $effectif
 */
class Phase extends Model
{
    use CloisonneParElevage;

    public const COLONNE_ELEVAGE = 'lot_animaux_id';

    public const CHEMIN_ELEVAGE = [['plan.lot_animaux', 'id', 'elevage_id']];

    public const STADES = ['entretien', 'gestation', 'fin_gestation', 'prepa_velage', 'lactation', 'croissance', 'engraissement', 'tarissement'];

    public $timestamps = false;

    protected $table = 'plan.phase';

    protected $fillable = ['lot_animaux_id', 'stade', 'date_debut', 'date_fin', 'effectif'];

    protected function casts(): array
    {
        return ['date_debut' => 'date', 'date_fin' => 'date', 'effectif' => 'integer'];
    }

    /** @return BelongsTo<LotAnimaux, $this> */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(LotAnimaux::class, 'lot_animaux_id');
    }

    /** @return HasOne<Ration, $this> */
    public function ration(): HasOne
    {
        return $this->hasOne(Ration::class);
    }
}
