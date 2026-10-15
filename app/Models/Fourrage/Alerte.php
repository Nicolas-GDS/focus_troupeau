<?php

namespace App\Models\Fourrage;

use App\Models\Concerns\CloisonneParElevage;
use App\Models\Ref\SeuilInterpretation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Alerte d'une interprétation (fourrage.alerte) : automatique (RC-1) ou ajoutée par le technicien ;
 * retenue ou écartée avec motif (EF-M2-03).
 *
 * @property int $id
 * @property int $interpretation_id
 * @property string|null $parametre_code
 * @property int|null $seuil_id
 * @property string|null $valeur
 * @property string|null $censure
 * @property string $classe
 * @property int $gravite
 * @property string $message
 * @property string $origine
 * @property bool $retenue
 * @property string|null $motif_ecart
 */
class Alerte extends Model
{
    use CloisonneParElevage;

    public const COLONNE_ELEVAGE = 'interpretation_id';

    public const CHEMIN_ELEVAGE = [
        ['fourrage.interpretation', 'id', 'analyse_id'],
        ['fourrage.analyse', 'id', 'prelevement_id'],
        ['fourrage.prelevement', 'id', 'elevage_id'],
    ];

    protected $table = 'fourrage.alerte';

    public $timestamps = false;

    protected $fillable = [
        'interpretation_id', 'parametre_code', 'seuil_id', 'valeur', 'censure', 'classe', 'gravite', 'message',
        'origine', 'retenue', 'motif_ecart',
    ];

    protected $attributes = ['origine' => 'automatique', 'retenue' => true];

    protected function casts(): array
    {
        return ['retenue' => 'boolean'];
    }

    /** @return BelongsTo<Interpretation, $this> */
    public function interpretation(): BelongsTo
    {
        return $this->belongsTo(Interpretation::class);
    }

    /** @return BelongsTo<SeuilInterpretation, $this> */
    public function seuil(): BelongsTo
    {
        return $this->belongsTo(SeuilInterpretation::class, 'seuil_id');
    }
}
