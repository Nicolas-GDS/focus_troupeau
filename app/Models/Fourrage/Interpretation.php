<?php

namespace App\Models\Fourrage;

use App\Models\Concerns\CloisonneParElevage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Interprétation d'un bulletin (fourrage.interpretation, § 5.3, EF-M2-03, EF-M2-04).
 * Circuit : brouillon → valide → (visee si niveau 3) → publie → archive.
 *
 * @property int $id
 * @property int $analyse_id
 * @property int $version
 * @property Carbon $date_interpretation
 * @property int|null $technicien_id
 * @property string|null $categorie_cible_code
 * @property int|null $niveau_global
 * @property int|null $niveau_propose
 * @property string|null $synthese
 * @property string|null $lecture_nutritionnelle
 * @property string|null $lecture_sanitaire
 * @property string|null $recommandations
 * @property bool $orientation_veterinaire
 * @property string $statut
 * @property string|null $version_grille
 * @property string|null $utilisation_conseillee
 * @property int|null $vise_par
 * @property Carbon|null $vise_le
 */
class Interpretation extends Model
{
    use CloisonneParElevage;

    public const COLONNE_ELEVAGE = 'analyse_id';

    public const CHEMIN_ELEVAGE = [['fourrage.analyse', 'id', 'prelevement_id'], ['fourrage.prelevement', 'id', 'elevage_id']];

    public const STATUT_BROUILLON = 'brouillon';

    public const STATUT_VALIDE = 'valide';

    public const STATUT_VISEE = 'visee';

    public const STATUT_PUBLIE = 'publie';

    public const STATUT_ARCHIVE = 'archive';

    public const CREATED_AT = 'cree_le';

    public const UPDATED_AT = 'maj_le';

    protected $table = 'fourrage.interpretation';

    protected $fillable = [
        'analyse_id', 'version', 'date_interpretation', 'technicien_id', 'categorie_cible_code', 'niveau_global',
        'niveau_propose', 'synthese', 'lecture_nutritionnelle', 'lecture_sanitaire', 'recommandations',
        'orientation_veterinaire', 'statut', 'version_grille', 'utilisation_conseillee', 'utilisation_deconseillee',
        'vise_par', 'vise_le',
    ];

    protected $attributes = ['version' => 1, 'statut' => self::STATUT_BROUILLON, 'orientation_veterinaire' => false];

    protected function casts(): array
    {
        return [
            'date_interpretation' => 'date',
            'vise_le' => 'datetime',
            'orientation_veterinaire' => 'boolean',
        ];
    }

    /** @return BelongsTo<Analyse, $this> */
    public function analyse(): BelongsTo
    {
        return $this->belongsTo(Analyse::class);
    }

    /** @return HasMany<Alerte, $this> */
    public function alertes(): HasMany
    {
        return $this->hasMany(Alerte::class);
    }
}
