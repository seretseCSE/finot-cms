<?php

namespace App\Models;

use App\Enums\PaymentVerificationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One payment-proof submission (see the migration for the lifecycle). The
 * `response` snapshot preserves exactly what the verification provider said
 * at the time — never re-fetched, never rewritten.
 */
#[Fillable([
    'invoice_id', 'student_id', 'submitted_by',
    'method', 'bank_code', 'transaction_number', 'receipt_url', 'receipt_path',
    'status', 'failure_reason', 'response', 'payment_id',
    'reviewed_by', 'reviewed_at', 'review_note',
])]
class PaymentVerification extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PaymentVerificationStatus::class,
            'response' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * The bank-record facts check.et extracted, flattened for the finance
     * review card — read straight from the immutable `response` snapshot.
     *
     * @return array<string, mixed>
     */
    public function evidence(): array
    {
        $data = is_array($this->response['data'] ?? null) ? $this->response['data'] : [];
        $receipt = is_array($data['receipt'] ?? null) ? $data['receipt'] : [];

        $amount = $receipt['amount'] ?? null;

        return [
            'bank_code' => $data['bank'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
            'verification_method' => $data['verification_method'] ?? null,
            'receipt_status' => $receipt['status'] ?? null,
            'amount' => is_numeric($amount) ? number_format((float) $amount, 2, '.', '') : null,
            'currency' => $receipt['currency'] ?? null,
            'transaction_date' => $receipt['transaction_date'] ?? null,
            'payer_name' => $receipt['payer_name'] ?? null,
            'receiver_name' => $receipt['receiver_name'] ?? null,
            'receiver_account' => $receipt['receiver_account'] ?? null,
            'provider_message' => $this->response['message'] ?? null,
            'unavailable' => (bool) ($this->response['unavailable'] ?? false),
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
