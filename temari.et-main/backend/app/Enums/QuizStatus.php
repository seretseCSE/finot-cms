<?php

namespace App\Enums;

enum QuizStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';
    case Archived = 'archived';

    /** Whether takers may currently start attempts (window permitting). */
    public function isOpen(): bool
    {
        return $this === self::Published;
    }
}
