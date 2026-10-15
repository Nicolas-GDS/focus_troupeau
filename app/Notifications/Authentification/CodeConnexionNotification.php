<?php

namespace App\Notifications\Authentification;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Code de double authentification (EF-M0-03, ENF-SEC-03). Envoyé immédiatement, hors file d'attente.
 */
class CodeConnexionNotification extends Notification
{
    public function __construct(public readonly string $code, public readonly int $minutes) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre code de connexion à '.config('app.name'))
            ->greeting('Bonjour,')
            ->line('Voici votre code de connexion :')
            ->line('**'.$this->code.'**')
            ->line("Ce code est valable {$this->minutes} minutes et ne sert qu'une fois.")
            ->line('Si vous n\'êtes pas à l\'origine de cette demande, changez votre mot de passe et prévenez votre GDS.')
            ->salutation('L\'équipe '.config('app.name'));
    }
}
