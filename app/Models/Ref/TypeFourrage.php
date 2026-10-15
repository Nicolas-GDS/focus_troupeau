<?php

namespace App\Models\Ref;

use Illuminate\Database\Eloquent\Model;

/**
 * Type de fourrage (ref.type_fourrage), rattaché à une famille.
 *
 * @property string $code
 * @property string $libelle
 * @property string $famille_code
 */
class TypeFourrage extends Model
{
    protected $table = 'ref.type_fourrage';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['code', 'libelle', 'famille_code'];
}
