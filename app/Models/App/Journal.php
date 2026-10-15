<?php

namespace App\Models\App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Journal d'audit des actions sensibles (§ 5.2, ENF-SEC-08). Ajout seul : jamais modifié ni supprimé.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $elevage_id
 * @property string $action
 * @property string|null $objet
 * @property array<string, mixed>|null $detail
 * @property string|null $ip
 * @property Carbon $horodatage
 */
class Journal extends Model
{
    protected $table = 'app.journal';

    public $timestamps = false;

    protected $fillable = ['user_id', 'elevage_id', 'action', 'objet', 'detail', 'ip'];

    protected function casts(): array
    {
        return [
            'detail' => 'array',
            'horodatage' => 'datetime',
        ];
    }
}
