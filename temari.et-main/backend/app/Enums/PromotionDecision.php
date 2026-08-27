<?php

namespace App\Enums;

/**
 * Year-end decision for one enrollment, recorded on the promotion board and
 * executed by the year rollover. `Transferred` rows are written by the
 * transfer workflow, never chosen on the board.
 */
enum PromotionDecision: string
{
    case Promoted = 'promoted';
    case Repeated = 'repeated';
    case Graduated = 'graduated';
    case Withdrawn = 'withdrawn';
    case Transferred = 'transferred';

    public function label(): string
    {
        return match ($this) {
            self::Promoted => 'Promoted',
            self::Repeated => 'Repeated',
            self::Graduated => 'Graduated',
            self::Withdrawn => 'Withdrawn',
            self::Transferred => 'Transferred',
        };
    }

    /** The enrollment status the source enrollment takes on execution. */
    public function enrollmentStatus(): EnrollmentStatus
    {
        return match ($this) {
            self::Promoted => EnrollmentStatus::Promoted,
            self::Repeated => EnrollmentStatus::Repeated,
            self::Graduated => EnrollmentStatus::Graduated,
            self::Withdrawn => EnrollmentStatus::Withdrawn,
            self::Transferred => EnrollmentStatus::TransferredOut,
        };
    }

    /** Does executing this decision create a next-year enrollment? */
    public function continues(): bool
    {
        return $this === self::Promoted || $this === self::Repeated;
    }
}
