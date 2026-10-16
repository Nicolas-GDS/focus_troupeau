<?php

namespace App\Jobs;

use App\Mail\NotificationMail;
use App\Models\App\Notification;
use App\Models\App\User;
use App\Services\Notifications\EnvoiImpossible;
use App\Services\Notifications\NotificationService;
use App\Services\Notifications\PasserelleSms;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envoi d'une notification journalisée (EF-M8-05) : e-mail par le SMTP Brevo, SMS par l'API Brevo. Le statut
 * d'envoi, l'identifiant du message et l'erreur éventuelle sont reportés dans app.notification.
 */
class EnvoyerNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public readonly int $notificationId) {}

    public function handle(PasserelleSms $sms): void
    {
        $notification = Notification::query()->find($this->notificationId);

        if ($notification === null || $notification->statut !== 'a_envoyer') {
            return;
        }

        $user = User::query()->findOrFail($notification->user_id);
        $notification->increment('tentatives');

        try {
            $identifiant = $notification->canal === NotificationService::SMS
                ? $sms->envoyer((string) NotificationService::numeroInternational($user->telephone), (string) $notification->corps)
                : Mail::to($user->email)->send(new NotificationMail((string) $notification->objet, explode("\n", (string) $notification->corps), $notification->lien))?->getMessageId();

            $notification->update(['statut' => 'envoye', 'envoye_le' => now(), 'id_brevo' => $identifiant, 'erreur' => null]);
        } catch (EnvoiImpossible $e) {
            $notification->update(['statut' => 'echec', 'erreur' => $e->getMessage()]);
        } catch (Throwable $e) {
            $notification->update(['erreur' => mb_substr($e->getMessage(), 0, 500)]);

            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        Notification::query()->whereKey($this->notificationId)->update(['statut' => 'echec', 'erreur' => mb_substr($e->getMessage(), 0, 500)]);
    }
}
