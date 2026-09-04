<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Lang;

/**
 * One invoice payment notice — sent alongside the SMS when the recipient has
 * an email and notify_via_email on. Plain text on purpose: it must render on
 * any client over any connection.
 */
class InvoiceNoticeMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $feeName,
        public readonly string $studentName,
        public readonly string $message,
        public readonly string $language = 'en',
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: Lang::get('fees.mail_subject', [
                'fee' => $this->feeName,
                'student' => $this->studentName,
            ], $this->language),
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.invoice-notice',
            with: [
                'body' => Lang::get('fees.mail_body', ['message' => $this->message], $this->language),
            ],
        );
    }
}
