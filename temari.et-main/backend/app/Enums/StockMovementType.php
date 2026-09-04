<?php

namespace App\Enums;

/**
 * Stock ledger movement kinds. The sign of a movement is derived from its
 * type (receive/return add, issue/write_off subtract, adjustment carries its
 * own sign) — StockLedger is the only writer and the ledger is append-only.
 */
enum StockMovementType: string
{
    case Receive = 'receive';
    case Issue = 'issue';
    case Return = 'return';
    case Adjustment = 'adjustment';
    case WriteOff = 'write_off';

    public function label(): string
    {
        return match ($this) {
            self::Receive => 'Received',
            self::Issue => 'Issued',
            self::Return => 'Returned',
            self::Adjustment => 'Adjustment',
            self::WriteOff => 'Write-off',
        };
    }
}
