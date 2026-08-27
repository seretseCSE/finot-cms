<?php

namespace App\Enums;

/**
 * Payroll run lifecycle. DRAFT is freely recomputable; APPROVED freezes the
 * items (no recompute, no HR-edit effects); PAID is terminal.
 */
enum PayrollStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Approved => 'Approved',
            self::Paid => 'Paid',
        };
    }
}
