<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One automated fee reminder sent for an invoice — the dedupe ledger and SMS
 * meter of the reminder ladder. Rows are never deleted: per-school counts
 * feed billing/metering.
 */
#[Fillable([
    'school_id', 'branch_id', 'invoice_id', 'student_id', 'user_id',
    'audience', 'stage', 'channel', 'recipient', 'result',
])]
class InvoiceReminder extends Model
{
    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
