<?php

namespace App\Models\App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Journal des notifications e-mail et SMS (§ 5.2, EF-M8).
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $elevage_id
 * @property string $canal
 * @property string $type
 * @property string|null $objet
 * @property string|null $corps
 * @property string|null $reference_objet
 * @property string $statut
 * @property string|null $id_brevo
 * @property Carbon|null $envoye_le
 * @property string|null $erreur
 * @property string|null $lien
 * @property int $tentatives
 * @property Carbon $cree_le
 */
class Notification extends Model
{
    public const CREATED_AT = 'cree_le';

    public const UPDATED_AT = null;

    protected $table = 'app.notification';

    protected $fillable = [
        'user_id', 'elevage_id', 'canal', 'type', 'objet', 'corps', 'reference_objet',
        'statut', 'id_brevo', 'envoye_le', 'erreur', 'lien', 'tentatives',
    ];

    protected function casts(): array
    {
        return ['envoye_le' => 'datetime', 'tentatives' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
