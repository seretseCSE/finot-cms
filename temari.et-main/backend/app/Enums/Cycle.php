<?php

namespace App\Enums;

/**
 * The Ethiopian national education cycles. Grade levels are grouped into these
 * fixed cycles; national exams sit at Grade 6 (primary), Grade 8 (middle
 * school) and Grade 12 (EUEE) — Grade 10 has NO national exam.
 */
enum Cycle: string
{
    case Kindergarten = 'kindergarten';
    case LowerPrimary = 'lower_primary';
    case UpperPrimary = 'upper_primary';
    case Secondary = 'secondary';
    case Preparatory = 'preparatory';

    public function label(): string
    {
        return match ($this) {
            self::Kindergarten => 'Kindergarten',
            self::LowerPrimary => 'Lower Primary',
            self::UpperPrimary => 'Upper Primary',
            self::Secondary => 'Secondary',
            self::Preparatory => 'Preparatory',
        };
    }
}
