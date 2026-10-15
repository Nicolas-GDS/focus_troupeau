<?php

namespace App\Models\Ref;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Seuil de la grille d'interprétation (ref.seuil_interpretation, RC-1), versionné (version_creation, version_fin).
 *
 * @property int $id
 * @property string $parametre_code
 * @property string|null $famille_fourrage_code
 * @property string|null $type_fourrage_code
 * @property string|null $espece_code
 * @property string|null $categorie_code
 * @property string $nature
 * @property string|null $borne_min
 * @property string|null $borne_max
 * @property string $classe
 * @property int|null $gravite
 * @property string|null $message
 * @property int|null $reference_id
 * @property string $statut_epistemique
 * @property bool $actif
 * @property string $version_creation
 * @property string|null $version_fin
 */
class SeuilInterpretation extends Model
{
    protected $table = 'ref.seuil_interpretation';

    public $timestamps = false;

    protected $fillable = [
        'parametre_code', 'famille_fourrage_code', 'type_fourrage_code', 'espece_code', 'categorie_code', 'nature',
        'borne_min', 'borne_max', 'classe', 'gravite', 'message', 'reference_id', 'statut_epistemique', 'actif',
        'commentaire', 'version_creation', 'version_fin',
    ];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    /** @return BelongsTo<ReferenceBiblio, $this> */
    public function reference(): BelongsTo
    {
        return $this->belongsTo(ReferenceBiblio::class, 'reference_id');
    }
}
