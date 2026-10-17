<?php

namespace App\Models\Stock;

use App\Models\Concerns\CloisonneParElevage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Phase de la période de référence d'un inventaire (stock.periode_phase, RC-10, voie UGB) : hivernage, transition,
 * complémentation estivale, avec son coefficient de ration.
 *
 * @property int $id
 * @property int $inventaire_id
 * @property string $libelle
 * @property Carbon $date_debut
 * @property Carbon $date_fin
 * @property string $coefficient
 */
class PeriodePhase extends Model
{
    use CloisonneParElevage;

    public const COLONNE_ELEVAGE = 'inventaire_id';

    public const CHEMIN_ELEVAGE = [['stock.inventaire', 'id', 'elevage_id']];

    public const LIBELLES = ['hivernage', 'transition', 'complementation_estivale'];

    public $timestamps = false;

    protected $table = 'stock.periode_phase';

    protected $fillable = ['inventaire_id', 'libelle', 'date_debut', 'date_fin', 'coefficient'];

    protected function casts(): array
    {
        return ['date_debut' => 'date', 'date_fin' => 'date'];
    }
}
