<?php

namespace App\Models\Dossier;

use App\Models\Concerns\CloisonneParElevage;
use Illuminate\Database\Eloquent\Model;

/**
 * Résultat d'une analyse d'eau (dossier.resultat_eau, § 5.3), au format long comme fourrage.resultat.
 *
 * @property int $analyse_eau_id
 * @property string $parametre_code
 * @property string|null $valeur
 * @property string|null $censure
 * @property string $unite
 * @property bool $flag_anomalie
 */
class ResultatEau extends Model
{
    use CloisonneParElevage;

    public const COLONNE_ELEVAGE = 'analyse_eau_id';

    public const CHEMIN_ELEVAGE = [['dossier.analyse_eau', 'id', 'elevage_id']];

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;

    protected $table = 'dossier.resultat_eau';

    protected $fillable = ['analyse_eau_id', 'parametre_code', 'valeur', 'censure', 'unite', 'flag_anomalie'];

    protected function casts(): array
    {
        return ['flag_anomalie' => 'boolean'];
    }
}
