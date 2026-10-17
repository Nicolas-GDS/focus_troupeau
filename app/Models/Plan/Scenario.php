<?php

namespace App\Models\Plan;

use App\Models\Concerns\CloisonneParElevage;
use App\Models\Concerns\TraceAuteur;
use App\Models\Stock\Inventaire;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Scénario d'approvisionnement d'un inventaire (plan.scenario, § 5.5, EF-M7-01 à EF-M7-05) : proposition initiale ou
 * scénario de l'utilisateur ; un seul retenu à la fois (T-23).
 *
 * @property int $id
 * @property int $inventaire_id
 * @property string $libelle
 * @property string $origine
 * @property string $statut
 * @property Carbon|null $retenu_le
 * @property int|null $cree_par
 */
class Scenario extends Model
{
    use CloisonneParElevage, TraceAuteur;

    public const COLONNE_ELEVAGE = 'inventaire_id';

    public const CHEMIN_ELEVAGE = [['stock.inventaire', 'id', 'elevage_id']];

    public const STATUT_BROUILLON = 'brouillon';

    public const STATUT_RETENU = 'retenu';

    public const STATUT_ARCHIVE = 'archive';

    public const CREATED_AT = 'cree_le';

    public const UPDATED_AT = 'maj_le';

    protected $table = 'plan.scenario';

    protected $fillable = ['inventaire_id', 'libelle', 'origine', 'statut', 'retenu_le'];

    protected $attributes = ['statut' => self::STATUT_BROUILLON];

    protected function casts(): array
    {
        return ['retenu_le' => 'datetime'];
    }

    /** @return BelongsTo<Inventaire, $this> */
    public function inventaire(): BelongsTo
    {
        return $this->belongsTo(Inventaire::class);
    }

    /** @return HasMany<LevierLigne, $this> */
    public function lignes(): HasMany
    {
        return $this->hasMany(LevierLigne::class)->orderBy('ordre');
    }
}
