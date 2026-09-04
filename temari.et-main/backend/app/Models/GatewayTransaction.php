<?php

namespace App\Models;

use App\Enums\GatewayPurpose;
use App\Enums\GatewayTransactionStatus;
use App\Support\PaymentGateways;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * One online collection through a payment gateway (see the migration).
 * Financial record — never deleted, and `paid` fulfils the payable exactly
 * once (PaymentGatewayManager::settle is the only writer of that transition).
 */
#[Fillable([
    'tx_ref', 'gateway', 'purpose', 'payable_type', 'payable_id', 'user_id',
    'amount', 'currency', 'status', 'checkout_url', 'gateway_ref', 'raw',
    'failure_reason', 'paid_at', 'refunded_at', 'refunded_by',
])]
#[Hidden(['raw'])]
class GatewayTransaction extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => GatewayPurpose::class,
            'status' => GatewayTransactionStatus::class,
            'amount' => 'decimal:2',
            'raw' => 'array',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public static function allocateRef(): string
    {
        do {
            $ref = 'TMR-'.strtoupper(Str::random(12));
        } while (self::query()->where('tx_ref', $ref)->exists());

        return $ref;
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gatewayLabel(): string
    {
        return PaymentGateways::label($this->gateway);
    }
}
