<?php

namespace App\Models\App;

use App\Models\Fourrage\Elevage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * GDS de l'instance (24, 47, extensible) — EF-M0-01.
 *
 * @property int $id
 * @property string $code
 * @property string $nom
 * @property string $couleur_locale
 * @property string|null $contact_email
 * @property string|null $site_web
 * @property bool $actif
 */
class Gds extends Model
{
    protected $table = 'app.gds';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'code', 'nom', 'couleur_locale', 'contact_email', 'site_web', 'actif'];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    /** @return HasMany<Elevage, $this> */
    public function elevages(): HasMany
    {
        return $this->hasMany(Elevage::class, 'gds_id');
    }

    /** @return HasMany<Convention, $this> */
    public function conventions(): HasMany
    {
        return $this->hasMany(Convention::class, 'gds_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function utilisateurs(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'app.user_gds', 'gds_id', 'user_id');
    }
}
