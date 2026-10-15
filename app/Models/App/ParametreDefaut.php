<?php

namespace App\Models\App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Valeur par défaut GDS, datée et versionnée (§ 5.2, D10, ENF-EXP-04).
 * La valeur reste en chaîne (numeric PostgreSQL) : aucune perte de précision avant le domaine.
 *
 * @property int $id
 * @property int|null $gds_id
 * @property string $cle
 * @property string $valeur
 * @property string|null $unite
 * @property string $libelle
 * @property string|null $source
 * @property string|null $statut_epistemique
 * @property Carbon $valide_du
 * @property Carbon|null $valide_au
 * @property string|null $commentaire
 * @property int|null $cree_par
 * @property Carbon $cree_le
 */
class ParametreDefaut extends Model
{
    public const CREATED_AT = 'cree_le';

    public const UPDATED_AT = null;

    protected $table = 'app.parametre_defaut';

    protected $fillable = [
        'gds_id', 'cle', 'valeur', 'unite', 'libelle', 'source', 'statut_epistemique',
        'valide_du', 'valide_au', 'commentaire', 'cree_par',
    ];

    protected function casts(): array
    {
        return [
            'valide_du' => 'date',
            'valide_au' => 'date',
        ];
    }

    /** @return BelongsTo<Gds, $this> */
    public function gds(): BelongsTo
    {
        return $this->belongsTo(Gds::class);
    }
}
