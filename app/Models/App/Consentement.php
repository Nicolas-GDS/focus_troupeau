<?php

namespace App\Models\App;

use App\Models\Concerns\CloisonneParElevage;
use App\Models\Fourrage\Elevage;
use Database\Factories\App\ConsentementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Consentements « références » et « vétérinaire » (§ 2.5, EF-M0-07, ENF-RGPD-03).
 * Un consentement n'est jamais supprimé : la révocation est horodatée.
 *
 * @property int $id
 * @property string $elevage_id
 * @property string $type
 * @property int|null $veterinaire_user_id
 * @property Carbon $accorde_le
 * @property int|null $accorde_par
 * @property Carbon|null $revoque_le
 * @property int|null $revoque_par
 */
class Consentement extends Model
{
    /** @use HasFactory<ConsentementFactory> */
    use CloisonneParElevage, HasFactory;

    public const TYPE_REFERENCES = 'references';

    public const TYPE_VETERINAIRE = 'veterinaire';

    protected $table = 'app.consentement';

    public $timestamps = false;

    protected $fillable = [
        'elevage_id', 'type', 'veterinaire_user_id', 'accorde_le', 'accorde_par', 'revoque_le', 'revoque_par',
    ];

    protected function casts(): array
    {
        return [
            'accorde_le' => 'datetime',
            'revoque_le' => 'datetime',
        ];
    }

    /** @return BelongsTo<Elevage, $this> */
    public function elevage(): BelongsTo
    {
        return $this->belongsTo(Elevage::class);
    }

    /** @return BelongsTo<User, $this> */
    public function veterinaire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'veterinaire_user_id');
    }

    public function estActif(): bool
    {
        return $this->revoque_le === null;
    }

    /**
     * @param  Builder<Consentement>  $query
     */
    public function scopeActifs(Builder $query): void
    {
        $query->whereNull('revoque_le');
    }
}
