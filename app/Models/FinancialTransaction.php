<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinancialTransaction extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'type',
        'transaction_id',
        'title',
        'description',
        'amount',
        'currency',
        'category',
        'source',
        'transaction_date',
        'payment_method',
        'bank_account_id',
        'attachment_path',
        'attachment_type',
        'recorded_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
        'approved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($transaction) {
            if (empty($transaction->transaction_id)) {
                $transaction->transaction_id = self::generateTransactionId();
            }
        });

        static::saved(function (self $transaction): void {
            $newAccountId = $transaction->bank_account_id;
            $newEffect = self::getSignedEffect($transaction->type, (float) $transaction->amount);

            if ($transaction->wasRecentlyCreated) {
                self::applyEffectToAccount($newAccountId, $newEffect);

                return;
            }

            if (! $transaction->wasChanged(['type', 'amount', 'bank_account_id'])) {
                return;
            }

            $oldAccountId = $transaction->getOriginal('bank_account_id');
            $oldEffect = self::getSignedEffect(
                (string) $transaction->getOriginal('type'),
                (float) $transaction->getOriginal('amount')
            );

            if ($oldAccountId === $newAccountId) {
                self::applyEffectToAccount($newAccountId, $newEffect - $oldEffect);

                return;
            }

            self::applyEffectToAccount($oldAccountId, -$oldEffect);
            self::applyEffectToAccount($newAccountId, $newEffect);
        });

        static::deleted(function (self $transaction): void {
            self::applyEffectToAccount(
                $transaction->bank_account_id,
                -self::getSignedEffect($transaction->type, (float) $transaction->amount)
            );
        });

        static::restored(function (self $transaction): void {
            self::applyEffectToAccount(
                $transaction->bank_account_id,
                self::getSignedEffect($transaction->type, (float) $transaction->amount)
            );
        });
    }

    private static function getSignedEffect(string $type, float $amount): float
    {
        return $type === 'income' ? $amount : -$amount;
    }

    private static function applyEffectToAccount(?int $accountId, float $effect): void
    {
        if (! $accountId || $effect == 0.0) {
            return;
        }

        $account = BankAccount::query()->find($accountId);

        if ($account) {
            $account->adjustBalance($effect);
        }
    }

    public static function generateTransactionId(): string
    {
        $prefix = 'FT';
        $latest = self::withTrashed()->orderBy('id', 'desc')->first();
        $number = $latest ? ((int) substr($latest->transaction_id, -6) + 1) : 1;

        return $prefix.'-'.str_pad($number, 6, '0', STR_PAD_LEFT);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function scopeIncome($query)
    {
        return $query->where('type', 'income');
    }

    public function scopeExpense($query)
    {
        return $query->where('type', 'expense');
    }

    public function scopeApproved($query)
    {
        return $query->whereNotNull('approved_at');
    }

    public function scopePending($query)
    {
        return $query->whereNull('approved_at');
    }

    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 2).' '.$this->currency;
    }

    public function getTypeLabelAttribute(): string
    {
        return ucfirst($this->type);
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        if ($this->attachment_path) {
            return asset('storage/'.$this->attachment_path);
        }

        return null;
    }
}
