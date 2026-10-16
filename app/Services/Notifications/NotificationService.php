<?php

namespace App\Services\Notifications;

use App\Jobs\EnvoyerNotification;
use App\Models\App\Notification;
use App\Models\App\User;

/**
 * Notifications e-mail et SMS (EF-M8-01, EF-M8-02, EF-M8-05) : chaque notification est journalisée dans
 * app.notification (destinataire, canal, objet, statut d'envoi) puis envoyée par le job EnvoyerNotification, après
 * validation de la transaction en cours. Les préférences du compte (pref_email, pref_sms) sont respectées, sauf pour
 * les notifications obligatoires (niveau 3) ; un envoi écarté est journalisé avec son motif.
 */
class NotificationService
{
    public const EMAIL = 'email';

    public const SMS = 'sms';

    /**
     * @param  list<string>  $lignes  corps de l'e-mail (trois lignes) ou texte du SMS (une ligne)
     */
    public function envoyer(
        User $destinataire,
        string $canal,
        string $type,
        string $objet,
        array $lignes,
        ?string $lien = null,
        ?string $elevageId = null,
        ?string $referenceObjet = null,
        bool $obligatoire = false,
    ): Notification {
        $notification = Notification::query()->create([
            'user_id' => $destinataire->id,
            'elevage_id' => $elevageId,
            'canal' => $canal,
            'type' => $type,
            'objet' => $objet,
            'corps' => implode("\n", $lignes),
            'lien' => $lien,
            'reference_objet' => $referenceObjet,
        ]);

        $refus = $this->motifRefus($destinataire, $canal, $obligatoire);

        if ($refus !== null) {
            $notification->update(['statut' => 'echec', 'erreur' => $refus]);

            return $notification;
        }

        EnvoyerNotification::dispatch($notification->id)->afterCommit();

        return $notification;
    }

    /**
     * Numéro de mobile au format international attendu par Brevo (« 33612345678 ») ; null s'il est inutilisable.
     */
    public static function numeroInternational(?string $telephone): ?string
    {
        $chiffres = preg_replace('/\D/', '', (string) $telephone) ?? '';

        return match (true) {
            preg_match('/^0[1-9]\d{8}$/', $chiffres) === 1 => '33'.substr($chiffres, 1),
            preg_match('/^(33|262|590|594|596)\d{9}$/', $chiffres) === 1 => $chiffres,
            default => null,
        };
    }

    private function motifRefus(User $destinataire, string $canal, bool $obligatoire): ?string
    {
        return match (true) {
            ! $destinataire->actif => 'Compte désactivé.',
            $canal === self::EMAIL && ! $destinataire->pref_email && ! $obligatoire => 'Désinscription des e-mails (préférence du compte).',
            $canal === self::SMS && ! $destinataire->pref_sms && ! $obligatoire => 'Désinscription des SMS (préférence du compte).',
            $canal === self::SMS && self::numeroInternational($destinataire->telephone) === null => 'Numéro de mobile absent ou invalide.',
            default => null,
        };
    }
}
