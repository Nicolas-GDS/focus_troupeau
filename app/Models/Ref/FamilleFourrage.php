<?php

namespace App\Models\Ref;

use Illuminate\Database\Eloquent\Model;

/**
 * Famille de fourrage (ref.famille_fourrage).
 *
 * @property string $code
 * @property string $libelle
 */
class FamilleFourrage extends Model
{
    protected $table = 'ref.famille_fourrage';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['code', 'libelle'];
}
