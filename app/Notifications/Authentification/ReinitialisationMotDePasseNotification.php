<?php

namespace App\Notifications\Authentification;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Lien de réinitialisation du mot de passe (Fortify), en français.
 */
class ReinitialisationMotDePasseNotification extends Notification
{
    public function __construct(public readonly string $token) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);
        $minutes = (int) config('auth.passwords.users.expire');

        return (new MailMessage)
            ->subject('Réinitialisation de votre mot de passe '.config('app.name'))
            ->greeting('Bonjour,')
            ->line('Vous avez demandé à réinitialiser votre mot de passe.')
            ->action('Choisir un nouveau mot de passe', $url)
            ->line("Ce lien est valable {$minutes} minutes.")
            ->line('Si vous n\'êtes pas à l\'origine de cette demande, ignorez cet e-mail : votre mot de passe reste inchangé.')
            ->salutation('L\'équipe '.config('app.name'));
    }
}
