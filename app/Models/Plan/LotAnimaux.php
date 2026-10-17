<?php

namespace App\Models\Plan;

use App\Models\Concerns\CloisonneParElevage;
use App\Models\Concerns\TraceAuteur;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Lot d'animaux (plan.lot_animaux, § 5.5, EF-M5-01) : catégorie, coefficient UGB (RC-8.1), poids vif, production ;
 * ses phases datées portent l'effectif et la ration. Archivé plutôt que supprimé (EF-GEN-06).
 *
 * @property int $id
 * @property string $elevage_id
 * @property int|null $inventaire_id
 * @property string $libelle
 * @property string $espece_code
 * @property string $categorie_code
 * @property string|null $coeff_ugb
 * @property string|null $poids_vif_kg
 * @property string|null $production_valeur
 * @property string|null $production_unite
 * @property Carbon|null $archive_le
 */
class LotAnimaux extends Model
{
    use CloisonneParElevage, TraceAuteur;

    public const PRODUCTIONS = ['kg_lait_j', 'gmq_g_j'];

    public const CREATED_AT = 'cree_le';

    public const UPDATED_AT = 'maj_le';

    protected $table = 'plan.lot_animaux';

    protected $fillable = [
        'elevage_id', 'inventaire_id', 'libelle', 'espece_code', 'categorie_code', 'coeff_ugb', 'poids_vif_kg', 'production_valeur',
        'production_unite', 'archive_le',
    ];

    protected function casts(): array
    {
        return ['archive_le' => 'datetime'];
    }

    /** @return HasMany<Phase, $this> */
    public function phases(): HasMany
    {
        return $this->hasMany(Phase::class)->orderBy('date_debut');
    }
}
