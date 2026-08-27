<?php

namespace App\Models;

use App\Services\Payments\Contracts\GatewayPayable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * The B2C parent/student AI upgrade (see the migration). Payment activates
 * the subscription and extends ends_at from max(now, current end) — renewing
 * early never loses days; a second paid tx on an already-active row extends
 * again (idempotence lives in the transaction, not the row).
 */
#[Fillable(['user_id', 'plan', 'amount', 'status', 'starts_at', 'ends_at'])]
class AiSubscription extends Model implements GatewayPayable
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
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActiveFor(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId)
            ->where('status', 'active')
            ->where('ends_at', '>', now());
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
        return 'Temari AI — 30 day subscription';
    }

    public function gatewayPaid(GatewayTransaction $transaction): void
    {
        $days = (int) config('temari-ai.subscription.days', 30);

        // Extend the user's LATEST active subscription window; this row is
        // the payable, but entitlement is per-user, so stacking renewals
        // always extends from the furthest active end.
        $currentEnd = self::query()
            ->where('user_id', $this->user_id)
            ->where('status', 'active')
            ->max('ends_at');

        $from = $currentEnd !== null && now()->lessThan($currentEnd)
            ? Carbon::parse($currentEnd)
            : now();

        $this->update([
            'status' => 'active',
            'starts_at' => $this->starts_at ?? now(),
            'ends_at' => $from->addDays($days),
        ]);
    }
}
