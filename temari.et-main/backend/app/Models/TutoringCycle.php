<?php

namespace App\Models;

use App\Enums\CycleStatus;
use App\Services\Notify\Notifier;
use App\Services\Payments\Contracts\GatewayPayable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * One Ethiopian-month escrow cycle (see the migration). This is the
 * GatewayPayable of the marketplace: the family's checkout funds THIS row,
 * and gatewayPaid() flips it to funded exactly once (manager-locked).
 */
#[Fillable([
    'engagement_id', 'ec_year', 'ec_month', 'label', 'starts_on', 'ends_on',
    'planned_hours', 'hourly_rate', 'gross_amount', 'credit_applied', 'amount_due',
    'commission_percent', 'status', 'funded_at', 'confirmed_hours', 'confirmed_value',
    'commission_amount', 'released_amount', 'credit_carried', 'released_at',
    'released_by', 'refunded_at', 'refunded_by', 'refund_note',
])]
class TutoringCycle extends Model implements GatewayPayable
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CycleStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'planned_hours' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'gross_amount' => 'decimal:2',
            'credit_applied' => 'decimal:2',
            'amount_due' => 'decimal:2',
            'commission_percent' => 'decimal:2',
            'confirmed_hours' => 'decimal:2',
            'confirmed_value' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'released_amount' => 'decimal:2',
            'credit_carried' => 'decimal:2',
            'funded_at' => 'datetime',
            'released_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TutoringEngagement, $this>
     */
    public function engagement(): BelongsTo
    {
        return $this->belongsTo(TutoringEngagement::class, 'engagement_id');
    }

    /**
     * @return HasMany<TutoringSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(TutoringSession::class, 'cycle_id');
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
        return "Tutoring — {$this->label}";
    }

    /** Funding is idempotent: a second paid tx can never double-fund. */
    public function gatewayPaid(GatewayTransaction $transaction): void
    {
        if ($this->status !== CycleStatus::AwaitingPayment) {
            return;
        }

        $this->update([
            'status' => CycleStatus::Funded->value,
            'funded_at' => now(),
        ]);

        // The tutor can start teaching — the month is in escrow.
        app(Notifier::class)->toUser(
            $this->engagement?->tutorProfile?->user,
            'tutoring.cycle_funded',
            ['label' => $this->label],
            ['link' => '/tutoring/engagements/'.$this->engagement_id],
        );
    }
}
