<?php

namespace App\Models\Dossier;

use App\Models\Concerns\CloisonneParElevage;
use App\Models\Concerns\TraceAuteur;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Indicateur du bilan sanitaire d'une campagne (dossier.indicateur_sanitaire, § 5.3, EF-M1-07), importé de SAGEMA
 * ou saisi ; taux = nb_cas / denominateur.
 *
 * @property int $id
 * @property string $elevage_id
 * @property string $campagne_code
 * @property string $indicateur_code
 * @property string|null $valeur
 * @property int|null $nb_cas
 * @property int|null $denominateur
 * @property string|null $source
 * @property Carbon $importe_le
 */
class IndicateurSanitaire extends Model
{
    use CloisonneParElevage, TraceAuteur;

    public const CREATED_AT = 'importe_le';

    public const UPDATED_AT = 'maj_le';

    protected $table = 'dossier.indicateur_sanitaire';

    protected $fillable = ['elevage_id', 'campagne_code', 'indicateur_code', 'valeur', 'nb_cas', 'denominateur', 'source'];

    protected function casts(): array
    {
        return ['importe_le' => 'datetime', 'nb_cas' => 'integer', 'denominateur' => 'integer'];
    }
}
