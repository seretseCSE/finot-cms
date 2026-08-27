<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Lang;

/**
 * One transfer/withdrawal status update — sent alongside the SMS when the
 * recipient has an email and notify_via_email on. Plain text on purpose: it
 * must render on any client over any connection.
 */
class TransferUpdateMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $studentName,
        public readonly string $message,
        public readonly string $language = 'en',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: Lang::get('transfers.mail_subject', ['student' => $this->studentName], $this->language),
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.transfer-update',
            with: [
                'body' => Lang::get('transfers.mail_body', ['message' => $this->message], $this->language),
            ],
        );
    }
}
