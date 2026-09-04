<?php

namespace App\Enums;

enum QuizAttemptStatus: string
{
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Graded = 'graded';
    case Invalidated = 'invalidated';

    /** Finished states — the attempt no longer accepts answers. */
    public function isFinal(): bool
    {
        return $this !== self::InProgress;
    }
}
