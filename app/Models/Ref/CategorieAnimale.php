<?php

namespace App\Models\Ref;

use Illuminate\Database\Eloquent\Model;

/**
 * Catégorie animale (ref.categorie_animale), rattachée à une espèce.
 *
 * @property string $code
 * @property string $libelle
 * @property string $espece_code
 */
class CategorieAnimale extends Model
{
    protected $table = 'ref.categorie_animale';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['code', 'libelle', 'espece_code'];
}
