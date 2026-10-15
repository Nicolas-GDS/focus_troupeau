<?php

namespace App\Notifications\Authentification;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invitation à activer son compte (§ 2.4, EF-M0-02).
 */
class InvitationNotification extends Notification
{
    public function __construct(public readonly string $url, public readonly int $jours) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre accès à '.config('app.name'))
            ->greeting('Bonjour,')
            ->line('Votre GDS vous a ouvert un accès à '.config('app.name').', l\'espace en ligne de l\'approche globale de la santé du troupeau.')
            ->action('Activer mon compte', $this->url)
            ->line("Ce lien est valable {$this->jours} jours et ne sert qu'une fois. Passé ce délai, demandez une nouvelle invitation à votre technicien.")
            ->salutation('L\'équipe '.config('app.name'));
    }
}
