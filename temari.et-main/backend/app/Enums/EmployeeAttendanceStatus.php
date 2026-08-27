<?php

namespace App\Enums;

/**
 * A recorded staff attendance mark. On-leave and holiday are NOT statuses —
 * they are computed overlays from approved leave requests and the holiday
 * calendar (see EmployeeAttendanceController::register).
 */
enum EmployeeAttendanceStatus: string
{
    case Present = 'present';
    case Late = 'late';
    case HalfDay = 'half_day';
    case Absent = 'absent';
    case Excused = 'excused';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::Late => 'Late',
            self::HalfDay => 'Half day',
            self::Absent => 'Absent',
            self::Excused => 'Excused',
        };
    }
}
