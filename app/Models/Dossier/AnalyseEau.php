<?php

namespace App\Models\Dossier;

use App\Models\Concerns\CloisonneParElevage;
use App\Models\Concerns\TraceAuteur;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Analyse d'eau d'abreuvement (dossier.analyse_eau, § 5.3, EF-M1-04), saisie manuellement (EF-M9-02).
 * Circuit : recu → controle (ou anomalie) → valide → publie.
 *
 * @property int $id
 * @property string $elevage_id
 * @property int|null $visite_id
 * @property string $point_prelevement
 * @property Carbon $date_prelevement
 * @property int|null $laboratoire_id
 * @property string|null $fichier_pdf
 * @property string|null $hash_pdf
 * @property string $statut
 */
class AnalyseEau extends Model
{
    use CloisonneParElevage, TraceAuteur;

    public const STATUT_RECU = 'recu';

    public const STATUT_CONTROLE = 'controle';

    public const STATUT_VALIDE = 'valide';

    public const STATUT_PUBLIE = 'publie';

    public const STATUT_ANOMALIE = 'anomalie';

    public const CREATED_AT = 'cree_le';

    public const UPDATED_AT = 'maj_le';

    protected $table = 'dossier.analyse_eau';

    protected $fillable = [
        'elevage_id', 'visite_id', 'point_prelevement', 'date_prelevement', 'laboratoire_id', 'fichier_pdf', 'hash_pdf', 'statut',
    ];

    protected $attributes = ['statut' => self::STATUT_RECU];

    protected function casts(): array
    {
        return ['date_prelevement' => 'date'];
    }

    /** @return BelongsTo<Visite, $this> */
    public function visite(): BelongsTo
    {
        return $this->belongsTo(Visite::class);
    }

    /** @return HasMany<ResultatEau, $this> */
    public function resultats(): HasMany
    {
        return $this->hasMany(ResultatEau::class);
    }
}
