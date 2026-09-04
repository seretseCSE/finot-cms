<?php

namespace App\Enums;

/**
 * Lifecycle of one gateway collection. `initiated` = checkout created on our
 * side; `pending` = handed to the gateway, awaiting confirmation; `paid` is
 * the ONLY state that triggers the payable's fulfilment (exactly once);
 * `refunded` is an operator decision recorded after the fact.
 */
enum GatewayTransactionStatus: string
{
    case Initiated = 'initiated';
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Canceled = 'canceled';
    case Refunded = 'refunded';

    /** Terminal states never transition again (except paid → refunded). */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Paid, self::Failed, self::Canceled, self::Refunded], true);
    }
}
