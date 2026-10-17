<?php

namespace App\Models\Stock;

use App\Models\Concerns\CloisonneParElevage;
use App\Models\Concerns\TraceAuteur;
use App\Models\Fourrage\Elevage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Inventaire des stocks d'un élevage (stock.inventaire, § 5.4, EF-M4-01) : date, type, période de référence (RC-10),
 * marge de sécurité copiée de la valeur en vigueur (RC-12). Un seul inventaire « courant » par élevage.
 *
 * @property int $id
 * @property string $elevage_id
 * @property int|null $visite_id
 * @property Carbon $date_inventaire
 * @property string $type
 * @property Carbon $date_debut
 * @property Carbon $date_fin
 * @property string $marge_securite
 * @property bool $annee_seche
 * @property string $statut
 * @property string|null $commentaire
 * @property int|null $cree_par
 * @property int|null $maj_par
 */
class Inventaire extends Model
{
    use CloisonneParElevage, TraceAuteur;

    public const TYPES = ['fin_recolte', 'mi_hiver', 'crise', 'autre'];

    public const STATUT_COURANT = 'courant';

    public const STATUT_CLOS = 'clos';

    public const STATUT_ARCHIVE = 'archive';

    public const CREATED_AT = 'cree_le';

    public const UPDATED_AT = 'maj_le';

    protected $table = 'stock.inventaire';

    protected $fillable = [
        'elevage_id', 'visite_id', 'date_inventaire', 'type', 'date_debut', 'date_fin', 'marge_securite', 'annee_seche', 'statut', 'commentaire',
    ];

    protected $attributes = ['statut' => self::STATUT_COURANT, 'annee_seche' => false];

    protected function casts(): array
    {
        return ['date_inventaire' => 'date', 'date_debut' => 'date', 'date_fin' => 'date', 'annee_seche' => 'boolean'];
    }

    /** @return BelongsTo<Elevage, $this> */
    public function elevage(): BelongsTo
    {
        return $this->belongsTo(Elevage::class);
    }

    /** @return HasMany<LotStock, $this> */
    public function lots(): HasMany
    {
        return $this->hasMany(LotStock::class);
    }

    /** @return HasMany<PeriodePhase, $this> */
    public function phases(): HasMany
    {
        return $this->hasMany(PeriodePhase::class);
    }
}
