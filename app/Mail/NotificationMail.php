<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * E-mail de notification (EF-M8-01 : objet, trois lignes, lien), à la marque de l'application.
 */
class NotificationMail extends Mailable
{
    /**
     * @param  list<string>  $lignes
     */
    public function __construct(
        public readonly string $objet,
        public readonly array $lignes,
        public readonly ?string $lien = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->objet);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.notification');
    }
}
