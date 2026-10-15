<?php

namespace App\Models\Ref;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Version de la grille et des référentiels (ref.version_referentiel, EF-M10-02) : numéro, date, auteur, décision
 * du comité technique. Chaque interprétation mémorise la version utilisée (version_grille).
 *
 * @property string $numero
 * @property Carbon $date_version
 * @property int|null $auteur_id
 * @property string|null $commentaire
 */
class VersionReferentiel extends Model
{
    public const CREATED_AT = 'cree_le';

    public const UPDATED_AT = null;

    protected $table = 'ref.version_referentiel';

    protected $primaryKey = 'numero';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['numero', 'date_version', 'auteur_id', 'commentaire'];

    protected function casts(): array
    {
        return ['date_version' => 'date'];
    }
}
