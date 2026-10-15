<?php

namespace App\Models\App;

use App\Models\Fourrage\Elevage;
use App\Notifications\Authentification\ReinitialisationMotDePasseNotification;
use Database\Factories\App\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;

/**
 * Compte applicatif (chapitre 5.2) : éleveur, vétérinaire d'élevage (compte propre),
 * technicien, vétérinaire conseil, administrateur (Entra ID).
 *
 * @property int $id
 * @property string $email
 * @property string $nom
 * @property string|null $prenom
 * @property string|null $password
 * @property string|null $entra_oid
 * @property int|null $personne_id
 * @property string|null $telephone
 * @property bool $pref_email
 * @property bool $pref_sms
 * @property Carbon|null $derniere_connexion
 * @property bool $actif
 * @property bool $deux_facteurs_email
 * @property Carbon|null $notice_rgpd_acceptee_le
 * @property Carbon $cree_le
 * @property Carbon $maj_le
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    public const CREATED_AT = 'cree_le';

    public const UPDATED_AT = 'maj_le';

    protected $table = 'app.user';

    protected $fillable = [
        'email', 'nom', 'prenom', 'password', 'entra_oid', 'personne_id', 'telephone',
        'pref_email', 'pref_sms', 'actif', 'deux_facteurs_email',
    ];

    protected $hidden = ['password', 'remember_token'];

    /** Valeurs par défaut de la table, connues dès la création en mémoire. */
    protected $attributes = [
        'actif' => true,
        'pref_email' => true,
        'pref_sms' => true,
        'deux_facteurs_email' => false,
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'pref_email' => 'boolean',
            'pref_sms' => 'boolean',
            'actif' => 'boolean',
            'deux_facteurs_email' => 'boolean',
            'derniere_connexion' => 'datetime',
            'notice_rgpd_acceptee_le' => 'datetime',
        ];
    }

    /**
     * GDS de rattachement (technicien, vétérinaire conseil, administrateur) — app.user_gds.
     *
     * @return BelongsToMany<Gds, $this>
     */
    public function gds(): BelongsToMany
    {
        return $this->belongsToMany(Gds::class, 'app.user_gds', 'user_id', 'gds_id');
    }

    /**
     * Élevages rattachés au compte (éleveur ou vétérinaire d'élevage) — app.elevage_acces.
     *
     * @return BelongsToMany<Elevage, $this, ElevageAcces, 'acces'>
     */
    public function elevages(): BelongsToMany
    {
        return $this->belongsToMany(Elevage::class, 'app.elevage_acces', 'user_id', 'elevage_id')
            ->using(ElevageAcces::class)
            ->as('acces')
            ->withPivot('role', 'consentement_id');
    }

    /**
     * Compte d'agent GDS : authentifié par Entra ID, sans mot de passe local (EF-M0-04).
     */
    public function estCompteEntra(): bool
    {
        return $this->entra_oid !== null;
    }

    /**
     * Réinitialisation du mot de passe : jamais pour un compte Entra ID, qui n'a pas de mot de passe local.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        if (! $this->estCompteEntra()) {
            $this->notify(new ReinitialisationMotDePasseNotification($token));
        }
    }

    public function nomComplet(): string
    {
        return trim(($this->prenom ?? '').' '.$this->nom);
    }
}
