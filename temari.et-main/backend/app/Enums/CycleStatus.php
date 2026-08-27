<?php

namespace App\Enums;

/**
 * One Ethiopian-month escrow cycle: the family prepays (`funded`), sessions
 * run and get confirmed, then Temari.et releases the net to the tutor's
 * wallet (`released` — commission kept, unfulfilled value carried as credit
 * into the next cycle). Refunds are operator decisions on engagement end.
 */
enum CycleStatus: string
{
    case AwaitingPayment = 'awaiting_payment';
    case Funded = 'funded';
    case Released = 'released';
    case Refunded = 'refunded';
    case Canceled = 'canceled';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Released, self::Refunded, self::Canceled], true);
    }
}
