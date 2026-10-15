<?php

namespace App\Services\Authentification;

use App\Models\App\User;
use App\Notifications\Authentification\InvitationNotification;
use App\Notifications\Authentification\LienConnexionNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Liens à usage unique envoyés par e-mail (app.lien_connexion) :
 * - « connexion » : lien de connexion valable 15 minutes, usage unique (EF-M0-03, ENF-SEC-02, T-30) ;
 * - « activation » : invitation à la création du compte (§ 2.4, EF-M0-02).
 *
 * Seule l'empreinte SHA-256 du jeton est stockée ; la consommation est atomique, si bien qu'un lien
 * ne peut servir qu'une fois même en cas de clics simultanés.
 */
class LienConnexionService
{
    public const TYPE_CONNEXION = 'connexion';

    public const TYPE_ACTIVATION = 'activation';

    /**
     * Envoie un lien de connexion si le compte peut en recevoir un. Ne révèle jamais si le compte existe.
     */
    public function demanderConnexion(string $email, ?string $ip): void
    {
        $user = User::query()
            ->where('email', $email)
            ->where('actif', true)
            ->whereNull('entra_oid')
            ->first();

        if ($user === null) {
            return;
        }

        $minutes = (int) config('authentification.lien_connexion_minutes');
        $expiration = now()->addMinutes($minutes);
        $jeton = $this->creer($user, self::TYPE_CONNEXION, $expiration, $ip);

        $url = URL::temporarySignedRoute('connexion.lien.confirmer', $expiration, ['jeton' => $jeton]);
        $user->notify(new LienConnexionNotification($url, $minutes));
    }

    /**
     * Invitation à activer un compte éleveur ou vétérinaire d'élevage (§ 2.4).
     */
    public function inviter(User $user, ?User $auteur = null): void
    {
        $jours = (int) config('authentification.activation_jours');
        $expiration = now()->addDays($jours);
        $jeton = $this->creer($user, self::TYPE_ACTIVATION, $expiration, null, $auteur);

        $url = URL::temporarySignedRoute('activation', $expiration, ['jeton' => $jeton]);
        $user->notify(new InvitationNotification($url, $jours));
    }

    /**
     * Le lien est-il encore utilisable (non utilisé, non expiré) ? Ne le consomme pas.
     */
    public function estValide(string $jeton, string $type): bool
    {
        return DB::table('app.lien_connexion')
            ->where('jeton_hash', $this->empreinte($jeton))
            ->where('type', $type)
            ->whereNull('utilise_le')
            ->where('expire_le', '>', now())
            ->exists();
    }

    /**
     * Consomme le lien et renvoie le compte associé, ou null si le lien est inconnu, expiré ou déjà utilisé.
     */
    public function consommer(string $jeton, string $type): ?User
    {
        $ligne = DB::selectOne(
            'UPDATE app.lien_connexion SET utilise_le = now()
              WHERE jeton_hash = ? AND type = ? AND utilise_le IS NULL AND expire_le > ?
              RETURNING user_id',
            [$this->empreinte($jeton), $type, now()],
        );

        if ($ligne === null) {
            return null;
        }

        return User::query()->where('id', $ligne->user_id)->where('actif', true)->first();
    }

    private function creer(User $user, string $type, Carbon $expiration, ?string $ip, ?User $auteur = null): string
    {
        $jeton = Str::random(64);

        DB::table('app.lien_connexion')->insert([
            'user_id' => $user->id,
            'type' => $type,
            'jeton_hash' => $this->empreinte($jeton),
            'expire_le' => $expiration,
            'ip_demande' => $ip,
            'cree_par' => $auteur?->id,
        ]);

        return $jeton;
    }

    private function empreinte(string $jeton): string
    {
        return hash('sha256', $jeton);
    }
}
