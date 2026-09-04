<?php

namespace App\Enums;

/**
 * Requisition lifecycle. PENDING rows are editable/cancellable by the
 * requester; the decision is a countersignature (never the requester's own);
 * ISSUED means every approved line is fully fulfilled by the store.
 */
enum RequisitionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Declined = 'declined';
    case Issued = 'issued';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Declined => 'Declined',
            self::Issued => 'Issued',
            self::Cancelled => 'Cancelled',
        };
    }
}
