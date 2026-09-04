<?php

namespace App\Enums;

/**
 * Academic year lifecycle. `Active` is the branch's operating year — the anchor
 * new enrollments/invoices default to; at most one per branch (enforced in
 * SaveAcademicYearAction / AcademicYearController@setStatus). `Planned` is a
 * next year being prepared; `Completed` is finished; `Archived` is historical.
 */
enum AcademicYearStatus: string
{
    case Planned = 'planned';
    case Active = 'active';
    case Completed = 'completed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Planned',
            self::Active => 'Active',
            self::Completed => 'Completed',
            self::Archived => 'Archived',
        };
    }
}
