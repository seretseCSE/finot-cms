<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One append-only wallet movement (see the migration). Written ONLY by
 * TutorLedger::post() — never created ad hoc, never updated or deleted.
 */
#[Fillable([
    'tutor_profile_id', 'entry_type', 'amount', 'balance_after',
    'reference_type', 'reference_id', 'memo', 'created_by',
])]
class TutorLedgerEntry extends Model
{
    public const string EARNING = 'earning';

    public const string PAYOUT = 'payout';

    public const string PAYOUT_REVERSAL = 'payout_reversal';

    public const string BOOST_FEE = 'boost_fee';

    public const string ADJUSTMENT = 'adjustment';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
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
     * @return MorphTo<Model, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
