<?php

namespace App\Models\Dossier;

use App\Models\Concerns\CloisonneParElevage;
use Illuminate\Database\Eloquent\Model;

/**
 * Cause commune identifiée à l'étape « croiser » (dossier.cause_commune, § 5.3, EF-M2-05).
 *
 * @property int $id
 * @property int $interpretation_visite_id
 * @property string $famille_cause
 * @property string $titre
 * @property string|null $preuves
 * @property string|null $consequences
 */
class CauseCommune extends Model
{
    use CloisonneParElevage;

    public const COLONNE_ELEVAGE = 'interpretation_visite_id';

    public const CHEMIN_ELEVAGE = [
        ['dossier.interpretation_visite', 'id', 'visite_id'],
        ['dossier.visite', 'id', 'elevage_id'],
    ];

    public const FAMILLES = ['immunite_oligo', 'metabolisme_mineral', 'metabolisme_energetique', 'confort', 'infectieux'];

    public $timestamps = false;

    protected $table = 'dossier.cause_commune';

    protected $fillable = ['interpretation_visite_id', 'famille_cause', 'titre', 'preuves', 'consequences'];
}
