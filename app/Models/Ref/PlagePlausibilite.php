<?php

namespace App\Models\Ref;

use Illuminate\Database\Eloquent\Model;

/**
 * Plage de plausibilité à l'import (ref.plage_plausibilite, RC-2), statut [CP] : à valider par le comité technique.
 *
 * @property int $id
 * @property string $parametre_code
 * @property string|null $famille_fourrage_code
 * @property string|null $borne_min
 * @property string|null $borne_max
 * @property string $statut_epistemique
 * @property string|null $source
 * @property bool $actif
 * @property string $version_creation
 * @property string|null $version_fin
 */
class PlagePlausibilite extends Model
{
    protected $table = 'ref.plage_plausibilite';

    public $timestamps = false;

    protected $fillable = [
        'parametre_code', 'famille_fourrage_code', 'borne_min', 'borne_max', 'statut_epistemique', 'source', 'actif',
        'version_creation', 'version_fin', 'commentaire',
    ];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }
}
