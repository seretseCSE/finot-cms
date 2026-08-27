<?php

namespace App\Enums;

/**
 * Per-invoice discount/scholarship. A full scholarship is a full_scholarship with a
 * reason — never a boolean on the student, so history and reporting stay exact
 * and partial scholarships (tuition covered, books not) work per fee line.
 */
enum DiscountType: string
{
    case None = 'none';
    case Percentage = 'percentage';
    case Fixed = 'fixed';
    case FullScholarship = 'full_scholarship';

    public function label(): string
    {
        return match ($this) {
            self::None => 'None',
            self::Percentage => 'Percentage discount',
            self::Fixed => 'Fixed discount',
            self::FullScholarship => 'Full scholarship',
        };
    }
}
