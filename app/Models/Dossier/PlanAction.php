<?php

namespace App\Models\Dossier;

use App\Models\Concerns\CloisonneParElevage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Plan d'action d'une interprétation de visite (dossier.plan_action, § 5.3, EF-M2-06).
 *
 * @property int $id
 * @property int $interpretation_visite_id
 */
class PlanAction extends Model
{
    use CloisonneParElevage;

    public const COLONNE_ELEVAGE = 'interpretation_visite_id';

    public const CHEMIN_ELEVAGE = [
        ['dossier.interpretation_visite', 'id', 'visite_id'],
        ['dossier.visite', 'id', 'elevage_id'],
    ];

    public const CREATED_AT = 'cree_le';

    public const UPDATED_AT = null;

    protected $table = 'dossier.plan_action';

    protected $fillable = ['interpretation_visite_id'];

    /** @return BelongsTo<InterpretationVisite, $this> */
    public function interpretationVisite(): BelongsTo
    {
        return $this->belongsTo(InterpretationVisite::class);
    }

    /** @return HasMany<Action, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(Action::class)->orderBy('priorite')->orderBy('ordre');
    }
}
