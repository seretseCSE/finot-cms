<?php

namespace App\Enums;

/**
 * Payment CHANNEL types — the specific wallet or bank (Telebirr, CBE Birr…)
 * lives on the payment's collection account, never here. Wallet and bank
 * transfer require an account; cash and other never take one.
 */
enum PaymentMethod: string
{
    case Wallet = 'wallet';
    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Wallet => 'Wallet',
            self::BankTransfer => 'Bank transfer',
            self::Cash => 'Cash',
            self::Other => 'Other',
        };
    }

    /** Channels whose money lands in a named collection account. */
    public function needsAccount(): bool
    {
        return in_array($this, [self::Wallet, self::BankTransfer], true);
    }
}
