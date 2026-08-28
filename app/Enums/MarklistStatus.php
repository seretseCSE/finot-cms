<?php

namespace App\Enums;

enum MarklistStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';

    public function isLocked(): bool
    {
        return $this !== self::Draft;
    }
}
