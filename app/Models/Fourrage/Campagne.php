<?php

namespace App\Models\Fourrage;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Campagne fourragère (fourrage.campagne), ex. « 2025-2026 ».
 *
 * @property string $code
 * @property string $libelle
 * @property Carbon $date_debut
 * @property Carbon $date_fin
 */
class Campagne extends Model
{
    protected $table = 'fourrage.campagne';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['code', 'libelle', 'date_debut', 'date_fin'];

    protected function casts(): array
    {
        return ['date_debut' => 'date', 'date_fin' => 'date'];
    }
}
