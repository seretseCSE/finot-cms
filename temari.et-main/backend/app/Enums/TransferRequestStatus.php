<?php

namespace App\Enums;

/**
 * Lifecycle of an in-platform transfer request. The RECEIVING branch creates
 * (`requested`) and may withdraw (`cancelled`); the SENDING branch decides
 * (`approved` / `rejected`). Approval is the transactional handover moment.
 */
enum TransferRequestStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Requested',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
        };
    }
}
