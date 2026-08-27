<?php

namespace App\Enums;

/**
 * Lifecycle of a parent/student-initiated transfer application: submitted to
 * the destination school → accepted (materialized into a standard transfer
 * request the current school decides) or declined; the family may withdraw
 * any time before acceptance.
 */
enum TransferApplicationStatus: string
{
    case Submitted = 'submitted';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::Accepted => 'Accepted',
            self::Declined => 'Declined',
            self::Withdrawn => 'Withdrawn',
        };
    }
}
