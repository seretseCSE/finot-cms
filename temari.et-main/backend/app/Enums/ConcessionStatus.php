<?php

namespace App\Enums;

/**
 * Concession lifecycle. Auto-suggested rows are born Pending and only apply
 * once approved; manual grants by fees.manage staff are born Active. Revoking
 * stops FUTURE invoices only — already-billed history is never rewritten.
 */
enum ConcessionStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Rejected = 'rejected';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending review',
            self::Active => 'Active',
            self::Rejected => 'Rejected',
            self::Revoked => 'Revoked',
        };
    }
}
