<?php

namespace App\Models\Fourrage;

use App\Models\App\Consentement;
use App\Models\App\Convention;
use App\Models\App\ElevageAcces;
use App\Models\App\Gds;
use App\Models\App\User;
use Database\Factories\Fourrage\ElevageFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Élevage pseudonymisé du socle (fourrage.elevage, document 02), rattaché à un GDS (§ 5.2).
 * Aucune donnée nominative ici : l'identité est dans prive.elevage_identite.
 *
 * @property string $id
 * @property string $code_pseudo
 * @property string $departement
 * @property int $gds_id
 * @property string|null $code_insee_commune
 * @property string|null $systeme_bovin
 * @property string|null $systeme_ovin
 * @property string|null $systeme_caprin
 * @property int|null $effectif_bovins
 * @property int|null $effectif_ovins
 * @property int|null $effectif_caprins
 * @property bool $adherent_gds
 * @property string|null $tiers_facture
 * @property string|null $commentaire
 * @property Carbon $cree_le
 * @property Carbon $maj_le
 */
class Elevage extends Model
{
    /** @use HasFactory<ElevageFactory> */
    use HasFactory, HasUuids;

    public const CREATED_AT = 'cree_le';

    public const UPDATED_AT = 'maj_le';

    protected $table = 'fourrage.elevage';

    protected $fillable = [
        'code_pseudo', 'departement', 'gds_id', 'code_insee_commune',
        'systeme_bovin', 'systeme_ovin', 'systeme_caprin',
        'effectif_bovins', 'effectif_ovins', 'effectif_caprins',
        'adherent_gds', 'tiers_facture', 'commentaire',
    ];

    protected function casts(): array
    {
        return ['adherent_gds' => 'boolean'];
    }

    /** @return BelongsTo<Gds, $this> */
    public function gds(): BelongsTo
    {
        return $this->belongsTo(Gds::class);
    }

    /** @return HasMany<Convention, $this> */
    public function conventions(): HasMany
    {
        return $this->hasMany(Convention::class);
    }

    /** @return HasMany<Consentement, $this> */
    public function consentements(): HasMany
    {
        return $this->hasMany(Consentement::class);
    }

    /** @return BelongsToMany<User, $this, ElevageAcces, 'acces'> */
    public function utilisateurs(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'app.elevage_acces', 'elevage_id', 'user_id')
            ->using(ElevageAcces::class)
            ->as('acces')
            ->withPivot('role', 'consentement_id');
    }
}
