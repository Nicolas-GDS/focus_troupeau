<?php

namespace App\Models\Dossier;

use App\Models\Concerns\CloisonneParElevage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Mesure terrain par animal ou par lot (dossier.mesure_terrain, § 5.3, EF-M1-05), lue par RC-1.5.
 * Circuit : saisie → validee → publiee. L'auteur est saisie_par (renseigné par le service).
 *
 * @property int $id
 * @property string $elevage_id
 * @property int|null $visite_id
 * @property int|null $lot_animaux_id
 * @property Carbon $date_mesure
 * @property string|null $identifiant_animal
 * @property string|null $stade
 * @property string|null $espece_code
 * @property string $mesure_code
 * @property string $valeur
 * @property string $unite
 * @property string|null $lecture_code
 * @property string $statut
 * @property int|null $saisie_par
 * @property int|null $maj_par
 */
class MesureTerrain extends Model
{
    use CloisonneParElevage;

    public const STATUT_SAISIE = 'saisie';

    public const STATUT_VALIDEE = 'validee';

    public const STATUT_PUBLIEE = 'publiee';

    public const CREATED_AT = 'cree_le';

    public const UPDATED_AT = 'maj_le';

    protected $table = 'dossier.mesure_terrain';

    protected $fillable = [
        'elevage_id', 'visite_id', 'lot_animaux_id', 'date_mesure', 'identifiant_animal', 'stade', 'espece_code', 'mesure_code',
        'valeur', 'unite', 'lecture_code', 'statut', 'saisie_par', 'maj_par',
    ];

    protected $attributes = ['statut' => self::STATUT_SAISIE];

    protected function casts(): array
    {
        return ['date_mesure' => 'date'];
    }

    /** @return BelongsTo<Visite, $this> */
    public function visite(): BelongsTo
    {
        return $this->belongsTo(Visite::class);
    }
}
