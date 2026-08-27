<?php

namespace App\Enums;

/**
 * Shared workflow states for annual AND weekly lesson plans: the teacher
 * drafts and submits, a reviewer (director or principal — each holds the
 * authority independently) approves or declines. Declined plans reopen for
 * editing with the reason on record.
 */
enum LessonPlanStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::Approved => 'Approved',
            self::Declined => 'Declined',
        };
    }

    /** Content is only editable while drafting or after a decline. */
    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::Declined;
    }
}
