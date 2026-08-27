<?php

namespace App\Enums;

/**
 * A tutor withdrawal from their wallet. Requested by the tutor, approved by
 * Temari.et staff (`marketplace.manage`), then paid via Chapa transfer or
 * recorded manually. The wallet is debited at APPROVAL (funds reserved);
 * a failed/canceled payout credits it back.
 */
enum PayoutStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Paid = 'paid';
    case Failed = 'failed';
    case Canceled = 'canceled';
}
