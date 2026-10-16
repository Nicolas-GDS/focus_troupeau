<?php

namespace App\Models\Dossier;

use App\Models\Concerns\CloisonneParElevage;
use Illuminate\Database\Eloquent\Model;

/**
 * Composition d'une étiquette, par kg de produit (dossier.etiquette_composition, § 5.3).
 *
 * @property int $etiquette_id
 * @property string $parametre_code
 * @property string $valeur
 * @property string $unite
 */
class EtiquetteComposition extends Model
{
    use CloisonneParElevage;

    public const COLONNE_ELEVAGE = 'etiquette_id';

    public const CHEMIN_ELEVAGE = [['dossier.etiquette_aliment', 'id', 'elevage_id']];

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;

    protected $table = 'dossier.etiquette_composition';

    protected $fillable = ['etiquette_id', 'parametre_code', 'valeur', 'unite'];
}
