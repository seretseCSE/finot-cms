<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The generic email leg of the notification pipeline: subject = the event's
 * localized title, body = its localized body (lang/notifications.php).
 * Events with richer needs keep their bespoke mailables (receipt, invoice
 * notice…) and mark `email: false` in the catalog so this never double-sends.
 * Plain text on purpose — must render on any client over any connection.
 */
class NotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $title,
        public readonly string $body,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->title);
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.notification',
            with: ['body' => $this->body],
        );
    }
}
