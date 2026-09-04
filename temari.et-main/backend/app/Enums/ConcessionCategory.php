<?php

namespace App\Enums;

/**
 * Why a fee concession exists. Sibling and staff-child are the standing
 * Ethiopian private-school policies (auto-suggested from school settings);
 * the rest are manual grants. Drives the review queue and value reporting.
 */
enum ConcessionCategory: string
{
    case Sibling = 'sibling';
    case StaffChild = 'staff_child';
    case Merit = 'merit';
    case Hardship = 'hardship';
    case Scholarship = 'scholarship';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Sibling => 'Sibling discount',
            self::StaffChild => 'Employee child',
            self::Merit => 'Merit',
            self::Hardship => 'Hardship',
            self::Scholarship => 'Scholarship',
            self::Other => 'Other',
        };
    }
}
