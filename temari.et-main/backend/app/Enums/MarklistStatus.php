<?php

namespace App\Enums;

enum MarklistStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::Approved => 'Approved',
        };
    }

    /** Marks are only editable while the marklist is a draft. */
    public function isLocked(): bool
    {
        return $this !== self::Draft;
    }
}
