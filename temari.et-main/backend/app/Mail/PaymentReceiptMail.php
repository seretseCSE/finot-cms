<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Lang;

/**
 * The "payment received — here is your receipt" email, sent alongside the
 * SMS when the recipient has an email and notify_via_email on. Plain text on
 * purpose: it must render on any client over any connection.
 */
class PaymentReceiptMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $studentName,
        public readonly string $message,
        public readonly string $language = 'en',
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: Lang::get('fees.receipt_mail_subject', ['student' => $this->studentName], $this->language),
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.payment-receipt',
            with: [
                'body' => Lang::get('fees.receipt_mail_body', ['message' => $this->message], $this->language),
            ],
        );
    }
}
