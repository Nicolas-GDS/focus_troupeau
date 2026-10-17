<?php

namespace App\Models\Plan;

use App\Models\Concerns\CloisonneParElevage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

/**
 * Ration d'une phase (plan.ration, EF-M5-02) : composants par animal et par jour, mode de distribution et pertes à la
 * distribution retenues (RC-6, RC-8.3).
 *
 * @property int $id
 * @property int $phase_id
 * @property string|null $mode_distribution
 * @property string $pertes_distribution_pct
 * @property string|null $commentaire
 * @property int|null $saisie_par
 */
class Ration extends Model
{
    use CloisonneParElevage;

    public const COLONNE_ELEVAGE = 'phase_id';

    public const CHEMIN_ELEVAGE = [['plan.phase', 'id', 'lot_animaux_id'], ['plan.lot_animaux', 'id', 'elevage_id']];

    public const CREATED_AT = null;

    public const UPDATED_AT = 'maj_le';

    protected $table = 'plan.ration';

    protected $fillable = ['phase_id', 'mode_distribution', 'pertes_distribution_pct', 'commentaire'];

    protected static function booted(): void
    {
        static::saving(fn (Ration $r) => $r->setAttribute('saisie_par', Auth::id() ?? $r->getAttribute('saisie_par')));
    }

    /** @return BelongsTo<Phase, $this> */
    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class);
    }

    /** @return HasMany<RationComposant, $this> */
    public function composants(): HasMany
    {
        return $this->hasMany(RationComposant::class)->orderBy('ordre');
    }
}
