<?php

namespace App\Models\Plan;

use App\Models\Concerns\CloisonneParElevage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Dernier bilan d'autonomie calculé d'un inventaire, ou d'un scénario (plan.bilan, RC-11, EF-M6-05) : mémorisé pour
 * l'accueil et les alertes ; le détail porte la projection mensuelle, les ruptures, l'incertitude.
 *
 * @property int $id
 * @property int $inventaire_id
 * @property int|null $scenario_id
 * @property Carbon $calcule_le
 * @property string|null $stock_utile_t_ms
 * @property string|null $besoin_t_ms
 * @property string|null $securite_t_ms
 * @property string|null $bilan_t_ms
 * @property string|null $couverture
 * @property int|null $autonomie_jours
 * @property Carbon|null $date_rupture
 * @property Carbon|null $date_rupture_min
 * @property Carbon|null $date_rupture_max
 * @property Carbon|null $date_entame_securite
 * @property string|null $incertitude_rel
 * @property bool $alerte_envoyee
 * @property array<string, mixed> $detail
 */
class Bilan extends Model
{
    use CloisonneParElevage;

    public const COLONNE_ELEVAGE = 'inventaire_id';

    public const CHEMIN_ELEVAGE = [['stock.inventaire', 'id', 'elevage_id']];

    public $timestamps = false;

    protected $table = 'plan.bilan';

    protected $fillable = [
        'inventaire_id', 'scenario_id', 'calcule_le', 'stock_utile_t_ms', 'besoin_t_ms', 'securite_t_ms', 'bilan_t_ms', 'couverture',
        'autonomie_jours', 'date_rupture', 'date_rupture_min', 'date_rupture_max', 'date_entame_securite', 'incertitude_rel',
        'alerte_envoyee', 'detail',
    ];

    protected function casts(): array
    {
        return [
            'calcule_le' => 'datetime', 'date_rupture' => 'date', 'date_rupture_min' => 'date', 'date_rupture_max' => 'date',
            'date_entame_securite' => 'date', 'alerte_envoyee' => 'boolean', 'detail' => 'array', 'autonomie_jours' => 'integer',
        ];
    }
}
