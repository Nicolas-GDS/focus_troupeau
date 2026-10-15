<?php

namespace App\Models\Ref;

use Illuminate\Database\Eloquent\Model;

/**
 * Paramètre d'analyse (ref.parametre) : code, unité, décimales d'affichage (§ 4.4).
 *
 * @property string $code
 * @property string $libelle
 * @property string $famille_code
 * @property string $unite
 * @property int $decimales
 */
class Parametre extends Model
{
    protected $table = 'ref.parametre';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['code', 'libelle', 'famille_code', 'unite', 'base', 'decimales', 'systeme_inra', 'definition', 'ordre'];
}
