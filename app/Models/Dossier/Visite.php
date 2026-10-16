<?php

namespace App\Models\Dossier;

use App\Models\Concerns\CloisonneParElevage;
use App\Models\Concerns\TraceAuteur;
use App\Models\Fourrage\Elevage;
use App\Models\Fourrage\Prelevement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Visite d'approche globale (dossier.visite, § 5.3, EF-M1-09) : une date, un technicien, un motif, un ensemble de
 * prélèvements, mesures et observations. Circuit : en_cours → interpretee → publiee → archivee.
 *
 * @property int $id
 * @property string $elevage_id
 * @property string|null $campagne_code
 * @property Carbon $date_visite
 * @property int|null $technicien_id
 * @property string $motif
 * @property string|null $motif_detail
 * @property string|null $compte_rendu
 * @property string $statut
 */
class Visite extends Model
{
    use CloisonneParElevage, TraceAuteur;

    public const STATUT_EN_COURS = 'en_cours';

    public const STATUT_INTERPRETEE = 'interpretee';

    public const STATUT_PUBLIEE = 'publiee';

    public const STATUT_ARCHIVEE = 'archivee';

    public const MOTIFS = ['initiale', 'suivi', 'question_sanitaire', 'bilan_stocks', 'autre'];

    public const CREATED_AT = 'cree_le';

    public const UPDATED_AT = 'maj_le';

    protected $table = 'dossier.visite';

    protected $fillable = [
        'elevage_id', 'campagne_code', 'date_visite', 'technicien_id', 'motif', 'motif_detail', 'compte_rendu', 'statut',
    ];

    protected $attributes = ['statut' => self::STATUT_EN_COURS];

    protected function casts(): array
    {
        return ['date_visite' => 'date'];
    }

    /** @return BelongsTo<Elevage, $this> */
    public function elevage(): BelongsTo
    {
        return $this->belongsTo(Elevage::class);
    }

    /** @return HasMany<Prelevement, $this> */
    public function prelevements(): HasMany
    {
        return $this->hasMany(Prelevement::class);
    }

    /** @return HasMany<InterpretationVisite, $this> */
    public function interpretations(): HasMany
    {
        return $this->hasMany(InterpretationVisite::class);
    }
}
