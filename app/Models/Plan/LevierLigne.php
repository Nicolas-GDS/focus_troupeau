<?php

namespace App\Models\Plan;

use App\Models\Concerns\CloisonneParElevage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ligne de levier d'un scénario (plan.levier_ligne, RC-13.3) : code, famille (achetée, de base d'un concentré ou à
 * laquelle le gain est imputé), quantité et unité, paramètre (prix, taux de pertes, t MS par génisse…), date d'effet,
 * gain en t MS et coût calculés. Aucun nom de produit (EF-M7-06).
 *
 * @property int $id
 * @property int $scenario_id
 * @property int $ordre
 * @property string $levier_code
 * @property string|null $famille_code
 * @property string|null $famille_source_code
 * @property int|null $lot_animaux_id
 * @property string $quantite
 * @property string $unite
 * @property string|null $parametre
 * @property string|null $prix_unitaire
 * @property Carbon|null $date_effet
 * @property string|null $gain_t_ms
 * @property string|null $cout_eur
 * @property string|null $commentaire
 */
class LevierLigne extends Model
{
    use CloisonneParElevage;

    public const COLONNE_ELEVAGE = 'scenario_id';

    public const CHEMIN_ELEVAGE = [['plan.scenario', 'id', 'inventaire_id'], ['stock.inventaire', 'id', 'elevage_id']];

    public $timestamps = false;

    protected $table = 'plan.levier_ligne';

    protected $fillable = [
        'scenario_id', 'ordre', 'levier_code', 'famille_code', 'famille_source_code', 'lot_animaux_id', 'quantite', 'unite',
        'parametre', 'prix_unitaire', 'date_effet', 'gain_t_ms', 'cout_eur', 'commentaire',
    ];

    protected function casts(): array
    {
        return ['date_effet' => 'date'];
    }

    /** @return BelongsTo<Scenario, $this> */
    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class);
    }
}
