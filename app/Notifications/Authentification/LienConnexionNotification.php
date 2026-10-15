<?php

namespace App\Notifications\Authentification;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Lien de connexion valable 15 minutes, usage unique (EF-M0-03, T-30).
 */
class LienConnexionNotification extends Notification
{
    public function __construct(public readonly string $url, public readonly int $minutes) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre lien de connexion à '.config('app.name'))
            ->greeting('Bonjour,')
            ->line('Vous avez demandé à vous connecter sans mot de passe.')
            ->action('Me connecter', $this->url)
            ->line("Ce lien est valable {$this->minutes} minutes et ne sert qu'une fois.")
            ->line('Si vous n\'êtes pas à l\'origine de cette demande, ignorez cet e-mail.')
            ->salutation('L\'équipe '.config('app.name'));
    }
}
