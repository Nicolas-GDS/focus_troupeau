<?php

namespace App\Models\Fourrage;

use App\Models\Concerns\CloisonneParElevage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Trouble observé dans le troupeau (fourrage.evenement_sanitaire), croisé avec les analyses (RC-1.4).
 *
 * @property int $id
 * @property string $elevage_id
 * @property string $espece_code
 * @property string|null $categorie_code
 * @property Carbon $date_evenement
 * @property string $type_evenement
 * @property int|null $gravite
 */
class EvenementSanitaire extends Model
{
    use CloisonneParElevage;

    public const CREATED_AT = 'cree_le';

    public const UPDATED_AT = null;

    protected $table = 'fourrage.evenement_sanitaire';

    protected $fillable = [
        'elevage_id', 'espece_code', 'categorie_code', 'date_evenement', 'type_evenement', 'nb_animaux', 'gravite',
        'source', 'description', 'prelevement_id',
    ];

    protected function casts(): array
    {
        return ['date_evenement' => 'date'];
    }
}
