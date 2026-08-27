<?php

namespace App\Enums;

/**
 * Lifecycle of a student's enrollment within an academic year. Only one
 * live (`pending` or `active`) enrollment per (student, year, program) is
 * allowed. `pending` = seat reserved, registration fee not yet settled —
 * the student appears on no roster until activated.
 */
enum EnrollmentStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Promoted = 'promoted';
    case Repeated = 'repeated';
    case TransferredOut = 'transferred_out';
    case Withdrawn = 'withdrawn';
    case Graduated = 'graduated';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Active => 'Active',
            self::Promoted => 'Promoted',
            self::Repeated => 'Repeated',
            self::TransferredOut => 'Transferred out',
            self::Withdrawn => 'Withdrawn',
            self::Graduated => 'Graduated',
        };
    }
}
