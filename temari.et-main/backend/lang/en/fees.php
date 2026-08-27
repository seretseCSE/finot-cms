<?php

/**
 * Fee/invoice comms (SMS + email). SMS bodies must stay short — many parents
 * are on feature phones and multi-part SMS costs schools money.
 */
return [
    // Sent on demand for an issued invoice (e.g. notifications were off when
    // the fee was billed). :due is omitted upstream when the fee has none.
    'invoice_sms' => ':school: :fee of :amount ETB is due for :student:due. Pay at the school or via your Temari.et account.',
    'invoice_due_suffix' => ' by :date',

    'mail_subject' => ':fee — payment notice for :student',
    'mail_body' => ':message

Sign in to Temari.et to see the invoice and submit your payment.',

    // Automated reminder ladder (fees:send-reminders), one body per stage.
    'reminder_upcoming' => ':school: :fee of :amount ETB for :student is due by :date. Pay at the school or via your Temari.et account.',
    'reminder_due' => ':school: :fee of :amount ETB for :student is due today. Pay at the school or via your Temari.et account.',
    'reminder_overdue' => ':school: :fee of :amount ETB for :student was due on :date and is still unpaid. Please settle it to avoid late penalties.',

    // Sent the moment a payment is recorded — the link is the public
    // QR-verified receipt page (view, verify, download the PDF).
    'receipt_sms' => ':school: Payment of :amount ETB for :student received. Receipt :receipt — :link',
    'receipt_mail_subject' => 'Payment received — receipt for :student',
    'receipt_mail_body' => ':message

Keep this receipt link — it proves the payment and serves the official PDF.',
];
