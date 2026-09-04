<?php

namespace App\Services;

use App\Mail\PaymentReceiptMail;
use App\Models\Payment;
use App\Models\User;
use App\Services\Notify\Notifier;
use Illuminate\Support\Facades\Lang;

/**
 * "Your document is ready" comms, routed through the notification pipeline
 * (ADR-018). The payment receipt is the flagship: the moment a payment is
 * recorded the family gets an in-app row + the receipt link by SMS/email
 * (the public page verifies authenticity and serves the PDF).
 */
class DocumentNotifier
{
    public function __construct(private readonly Notifier $notifier)
    {
    }

    public function paymentReceived(Payment $payment): void
    {
        $payment->loadMissing(['student', 'branch.school:id,name']);

        $student = $payment->student;

        if ($student === null) {
            return;
        }

        $vars = [
            'school' => $payment->branch?->school?->name ?? '',
            'amount' => number_format((float) $payment->amount, 2),
            'receipt' => $payment->receipt_number,
            'link' => rtrim((string) config('sms.frontend_url'), '/').'/receipts/'.$payment->receipt_token,
        ];

        $this->notifier->toFamily($student, 'finance.payment_received', $vars, [
            'link' => '/me/payments',
            'schoolId' => $payment->branch?->school_id,
            'branchId' => $payment->branch_id,
            'smsKey' => 'fees.receipt_sms',
            'mail' => fn (User $user, string $locale): PaymentReceiptMail => new PaymentReceiptMail(
                studentName: $student->full_name,
                message: Lang::get('fees.receipt_sms', [...$vars, 'student' => $student->full_name], $locale),
                language: $locale,
            ),
        ]);
    }
}
