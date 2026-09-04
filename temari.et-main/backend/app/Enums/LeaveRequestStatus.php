<?php

namespace App\Enums;

/**
 * Leave request lifecycle. PENDING requests are editable/cancellable by the
 * requester; a decision (approved/rejected) is final except that approved
 * future leave may still be cancelled before it starts.
 */
enum LeaveRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
        };
    }
}
