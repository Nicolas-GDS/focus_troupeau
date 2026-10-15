<?php

namespace App\Models\Fourrage;

use App\Models\Concerns\CloisonneParElevage;
use App\Models\Ref\TypeFourrage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Lot de fourrage prélevé (fourrage.prelevement, document 02).
 *
 * @property int $id
 * @property string|null $code
 * @property string $elevage_id
 * @property string|null $campagne_code
 * @property Carbon $date_prelevement
 * @property string $type_fourrage_code
 * @property string|null $nom_lot
 * @property Carbon|null $date_recolte
 * @property string|null $cycle_coupe
 * @property string|null $mode_conservation
 * @property string|null $mode_sechage
 * @property string|null $destination_categorie_code
 * @property string $motif
 * @property bool $a_completer
 * @property string|null $commentaire
 */
class Prelevement extends Model
{
    use CloisonneParElevage;

    public const CREATED_AT = 'cree_le';

    public const UPDATED_AT = 'maj_le';

    protected $table = 'fourrage.prelevement';

    protected $fillable = [
        'code', 'elevage_id', 'campagne_code', 'date_prelevement', 'type_fourrage_code', 'nom_lot', 'date_recolte',
        'cycle_coupe', 'mode_conservation', 'mode_sechage', 'destination_categorie_code', 'motif', 'a_completer', 'commentaire',
    ];

    protected $attributes = ['motif' => 'routine', 'a_completer' => false];

    protected function casts(): array
    {
        return [
            'date_prelevement' => 'date',
            'date_recolte' => 'date',
            'a_completer' => 'boolean',
        ];
    }

    /** @return BelongsTo<Elevage, $this> */
    public function elevage(): BelongsTo
    {
        return $this->belongsTo(Elevage::class);
    }

    /** @return BelongsTo<TypeFourrage, $this> */
    public function typeFourrage(): BelongsTo
    {
        return $this->belongsTo(TypeFourrage::class, 'type_fourrage_code', 'code');
    }

    /** @return HasMany<Analyse, $this> */
    public function analyses(): HasMany
    {
        return $this->hasMany(Analyse::class);
    }
}
