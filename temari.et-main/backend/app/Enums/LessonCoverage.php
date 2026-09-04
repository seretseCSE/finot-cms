<?php

namespace App\Enums;

/**
 * What actually happened to a planned lesson, marked by the teacher after
 * the week runs. Anything short of `covered` counts against pacing and
 * triggers the next week's justification gate.
 */
enum LessonCoverage: string
{
    case Pending = 'pending';
    case Covered = 'covered';
    case Partial = 'partial';
    case Missed = 'missed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Covered => 'Covered',
            self::Partial => 'Partially covered',
            self::Missed => 'Not covered',
        };
    }

    public function isCovered(): bool
    {
        return $this === self::Covered;
    }
}
