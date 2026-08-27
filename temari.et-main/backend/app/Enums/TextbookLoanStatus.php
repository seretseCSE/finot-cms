<?php

namespace App\Enums;

/**
 * Textbook loan lifecycle: OUT with the student, RETURNED to the store at
 * year end, or LOST (a ledger write-off; the family is told).
 */
enum TextbookLoanStatus: string
{
    case Out = 'out';
    case Returned = 'returned';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Out => 'With student',
            self::Returned => 'Returned',
            self::Lost => 'Lost',
        };
    }
}
