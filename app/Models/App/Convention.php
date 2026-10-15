<?php

namespace App\Models\App;

use App\Models\Concerns\TraceAuteur;
use App\Models\Fourrage\Elevage;
use Database\Factories\App\ConventionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Convention d'approche globale (§ 2.4, EF-M0-06, D12).
 *
 * @property int $id
 * @property string $elevage_id
 * @property int $gds_id
 * @property string $type
 * @property Carbon $date_debut
 * @property Carbon $date_fin
 * @property string $statut
 * @property string|null $reference_externe
 * @property string|null $commentaire
 * @property int|null $cree_par
 * @property int|null $maj_par
 * @property Carbon $cree_le
 * @property Carbon $maj_le
 */
class Convention extends Model
{
    /** @use HasFactory<ConventionFactory> */
    use HasFactory, TraceAuteur;

    public const CREATED_AT = 'cree_le';

    public const UPDATED_AT = 'maj_le';

    public const STATUT_ACTIVE = 'active';

    public const STATUT_ECHUE = 'echue';

    public const STATUT_RESILIEE = 'resiliee';

    protected $table = 'app.convention';

    protected $fillable = [
        'elevage_id', 'gds_id', 'type', 'date_debut', 'date_fin', 'statut', 'reference_externe', 'commentaire',
    ];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
        ];
    }

    /** @return BelongsTo<Elevage, $this> */
    public function elevage(): BelongsTo
    {
        return $this->belongsTo(Elevage::class);
    }

    /** @return BelongsTo<Gds, $this> */
    public function gds(): BelongsTo
    {
        return $this->belongsTo(Gds::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    /** @return BelongsTo<User, $this> */
    public function majPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'maj_par');
    }

    public function estActive(): bool
    {
        return $this->statut === self::STATUT_ACTIVE;
    }
}
