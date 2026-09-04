<?php

namespace App\Models;

use App\Services\Payments\Contracts\GatewayPayable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Paid directory placement (see the migration). Payment activates the boost
 * and pushes tutor_profiles.boosted_until forward; stacking a second boost
 * extends from the current end, never overlaps.
 */
#[Fillable(['tutor_profile_id', 'plan', 'amount', 'status', 'starts_at', 'ends_at'])]
class ProfileBoost extends Model implements GatewayPayable
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
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
     * @return MorphMany<GatewayTransaction, $this>
     */
    public function gatewayTransactions(): MorphMany
    {
        return $this->morphMany(GatewayTransaction::class, 'payable');
    }

    public function gatewayDescription(): string
    {
        return 'Tutor profile boost — '.($this->plan === 'monthly' ? '30 days' : '7 days');
    }

    public function gatewayPaid(GatewayTransaction $transaction): void
    {
        if ($this->status !== 'pending_payment') {
            return;
        }

        $profile = $this->tutorProfile;
        $days = $this->plan === 'monthly' ? 30 : 7;
        $from = $profile->boosted_until !== null && $profile->boosted_until->isFuture()
            ? $profile->boosted_until
            : now();

        $this->update([
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => $from->addDays($days),
        ]);

        $profile->update(['boosted_until' => $this->ends_at]);
    }
}
