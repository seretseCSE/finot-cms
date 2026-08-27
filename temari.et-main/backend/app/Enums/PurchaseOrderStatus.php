<?php

namespace App\Enums;

/**
 * Purchase-order lifecycle (the OPTIONAL procurement lane — direct receiving
 * never needs one). RECEIVED is set automatically when every line lands.
 */
enum PurchaseOrderStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Declined = 'declined';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Declined => 'Declined',
            self::Received => 'Received',
            self::Cancelled => 'Cancelled',
        };
    }
}
