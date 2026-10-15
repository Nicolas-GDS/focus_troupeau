<?php

namespace App\Notifications\Interpretation;

use App\Models\App\ImportBulletin;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Bulletin bloqué par le contrôle de plausibilité (EF-M2-01, RC-2, T-02) : le technicien du GDS est prévenu.
 * Le canal SMS et l'envoi par Brevo relèvent des notifications du lot L3.
 */
class BulletinEnAnomalieNotification extends Notification
{
    public function __construct(public readonly ImportBulletin $import) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Bulletin en anomalie : '.$this->import->nom_fichier)
            ->greeting('Bonjour,')
            ->line('Le contrôle de plausibilité a bloqué un bulletin déposé dans '.config('brand.nom').' : au moins une valeur sort de sa plage plausible.')
            ->line('Bulletin : '.$this->import->nom_fichier)
            ->line('Vérifiez auprès du laboratoire ou demandez un nouveau prélèvement, puis levez l\'anomalie avec un motif ou annulez le bulletin.')
            ->salutation('L\'équipe '.config('brand.nom'));
    }
}
