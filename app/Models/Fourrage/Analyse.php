<?php

namespace App\Models\Fourrage;

use App\Models\Concerns\CloisonneParElevage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Bulletin de laboratoire (fourrage.analyse), cloisonné par l'élevage de son prélèvement.
 * Statuts : attendu, recu, controle (plausible), valide, anomalie (RC-2), annule.
 *
 * @property int $id
 * @property int $prelevement_id
 * @property int $laboratoire_id
 * @property string $num_echantillon_labo
 * @property string|null $code_dossier_labo
 * @property string|null $type_bulletin
 * @property Carbon|null $date_reception
 * @property Carbon|null $date_edition
 * @property string|null $fichier_pdf
 * @property string|null $hash_pdf
 * @property string $statut
 * @property string|null $anomalie_detail
 * @property bool $exclu_references
 * @property string|null $motif_exclusion
 * @property Carbon|null $anomalie_levee_le
 * @property int|null $anomalie_levee_par
 * @property string|null $anomalie_motif_levee
 * @property Carbon $importe_le
 */
class Analyse extends Model
{
    use CloisonneParElevage;

    public const COLONNE_ELEVAGE = 'prelevement_id';

    public const CHEMIN_ELEVAGE = [['fourrage.prelevement', 'id', 'elevage_id']];

    public const STATUT_RECU = 'recu';

    public const STATUT_CONTROLE = 'controle';

    public const STATUT_VALIDE = 'valide';

    public const STATUT_ANOMALIE = 'anomalie';

    public const STATUT_ANNULE = 'annule';

    protected $table = 'fourrage.analyse';

    public $timestamps = false;

    protected $fillable = [
        'prelevement_id', 'laboratoire_id', 'num_echantillon_labo', 'code_dossier_labo', 'type_bulletin', 'forfait',
        'date_reception', 'date_edition', 'fichier_pdf', 'hash_pdf', 'statut', 'anomalie_detail',
        'exclu_references', 'motif_exclusion', 'anomalie_levee_le', 'anomalie_levee_par', 'anomalie_motif_levee',
    ];

    protected $attributes = ['statut' => self::STATUT_RECU, 'exclu_references' => false];

    protected function casts(): array
    {
        return [
            'date_reception' => 'date',
            'date_edition' => 'date',
            'importe_le' => 'datetime',
            'anomalie_levee_le' => 'datetime',
            'exclu_references' => 'boolean',
        ];
    }

    /** @return BelongsTo<Prelevement, $this> */
    public function prelevement(): BelongsTo
    {
        return $this->belongsTo(Prelevement::class);
    }

    /** @return HasMany<Resultat, $this> */
    public function resultats(): HasMany
    {
        return $this->hasMany(Resultat::class);
    }

    /** @return HasMany<Interpretation, $this> */
    public function interpretations(): HasMany
    {
        return $this->hasMany(Interpretation::class);
    }
}
