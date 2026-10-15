<?php

namespace App\Models\App;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Rattachement d'un compte éleveur ou vétérinaire d'élevage à un élevage (chapitre 5.2, EF-M0-08).
 * Pour un vétérinaire, le rattachement porte le consentement qui l'autorise (§ 2.5).
 *
 * @property int $user_id
 * @property string $elevage_id
 * @property string $role
 * @property int|null $consentement_id
 */
class ElevageAcces extends Pivot
{
    public const ROLE_ELEVEUR = 'eleveur';

    public const ROLE_VETERINAIRE = 'veterinaire';

    protected $table = 'app.elevage_acces';

    public $timestamps = false;

    /** @return BelongsTo<Consentement, $this> */
    public function consentement(): BelongsTo
    {
        return $this->belongsTo(Consentement::class);
    }
}
