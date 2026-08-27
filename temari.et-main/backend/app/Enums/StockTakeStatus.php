<?php

namespace App\Enums;

/**
 * Stock-take session lifecycle. Counting never touches stock; POSTED writes
 * the counted-vs-expected differences to the ledger as adjustments.
 */
enum StockTakeStatus: string
{
    case InProgress = 'in_progress';
    case Posted = 'posted';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'In progress',
            self::Posted => 'Posted',
            self::Cancelled => 'Cancelled',
        };
    }
}
