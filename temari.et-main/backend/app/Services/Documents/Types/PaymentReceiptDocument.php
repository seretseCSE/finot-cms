<?php

namespace App\Services\Documents\Types;

use App\Http\Controllers\Api\V1\PaymentController;
use App\Models\GeneratedDocument;
use App\Models\Payment;
use App\Models\User;
use App\Services\Documents\DocumentType;
use Illuminate\Database\Eloquent\Model;

/**
 * The official payment receipt. Pre-warmed the moment a payment is recorded;
 * its QR resolves to the existing public receipt page (receipt_token lane).
 */
class PaymentReceiptDocument extends DocumentType
{
    public function view(): string
    {
        return 'payment-receipt';
    }

    public function resolveSubject(?int $subjectId): ?Model
    {
        return Payment::find($subjectId);
    }

    public function authorize(User $user, ?Model $subject, array $params): bool
    {
        return $subject instanceof Payment
            && $user->hasPermissionForScope('fees.view', $subject->school_id, $subject->branch_id);
    }

    public function anchor(?Model $subject, array $params): array
    {
        /** @var Payment $subject */
        return ['school_id' => $subject->school_id, 'branch_id' => $subject->branch_id];
    }

    public function payload(?Model $subject, array $params): array
    {
        /** @var Payment $subject */
        return ['receipt' => PaymentController::receiptPayload($subject)];
    }

    public function publiclyDownloadable(): bool
    {
        return true;
    }

    /** Same QR target as the HTML article — the public receipt page. */
    public function qrTarget(GeneratedDocument $document): string
    {
        $payment = $document->subject;

        if ($payment instanceof Payment && $payment->receipt_token) {
            return rtrim((string) config('sms.frontend_url'), '/').'/receipts/'.$payment->receipt_token;
        }

        return parent::qrTarget($document);
    }

    public function verifySummary(GeneratedDocument $document): array
    {
        $payment = $document->subject;

        if (! $payment instanceof Payment) {
            return [];
        }

        $payment->load(['student:id,first_name,father_name,grandfather_name,public_id', 'branch.school:id,name']);

        return [
            'school' => $payment->branch?->school?->name,
            'branch' => $payment->branch?->name,
            'student' => $payment->student?->full_name,
            'reference' => $payment->receipt_number,
            'amount' => (string) $payment->amount,
            'issued_on' => $payment->paid_at?->toDateString(),
        ];
    }
}
