<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Lang;

/**
 * "Your child was marked absent/late today" — sent alongside the SMS when the
 * guardian has an email address and notify_via_email on. Plain text on
 * purpose: it must render on any client over any connection.
 */
class AttendanceAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $studentName,
        public readonly string $schoolName,
        public readonly string $status, // absent|late
        public readonly ?string $time,
        public readonly string $language = 'en',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: Lang::get("attendance_alerts.{$this->status}_mail_subject", [
                'student' => $this->studentName,
                'school' => $this->schoolName,
            ], $this->language),
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.attendance-alert',
            with: [
                'body' => Lang::get("attendance_alerts.{$this->status}_mail_body", [
                    'student' => $this->studentName,
                    'school' => $this->schoolName,
                    'time' => $this->time ?? '',
                ], $this->language),
            ],
        );
    }
}
