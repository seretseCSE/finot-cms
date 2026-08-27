<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A wallet withdrawal (see the migration). Account details are a snapshot;
 * the wallet debit/credit happens only through TutorLedger in PayoutService.
 */
#[Fillable([
    'tutor_profile_id', 'amount', 'method', 'bank_code', 'bank_name',
    'account_number', 'account_name', 'status', 'approved_by', 'approved_at',
    'paid_at', 'gateway_ref', 'failure_reason', 'note',
])]
class TutorPayout extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PayoutStatus::class,
            'amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TutorProfile, $this>
     */
    public function tutorProfile(): BelongsTo
    {
        return $this->belongsTo(TutorProfile::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
