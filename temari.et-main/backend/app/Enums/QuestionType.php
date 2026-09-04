<?php

namespace App\Enums;

enum QuestionType: string
{
    case McqSingle = 'mcq_single';
    case McqMulti = 'mcq_multi';
    case TrueFalse = 'true_false';
    case ShortAnswer = 'short_answer';
    case Numeric = 'numeric';
    case FillBlank = 'fill_blank';
    case Matching = 'matching';
    case Essay = 'essay';

    /**
     * A parent container (reading passage, matching set) whose sub-questions
     * carry the actual answers. Never graded, never a paper entry itself.
     */
    case Group = 'group';

    /**
     * Whether the AutoGrader can score this type without a human. Short
     * answers auto-grade only when the answer key lists accepted strings;
     * essays always queue for manual grading.
     */
    public function isAutoGradable(): bool
    {
        return $this !== self::Essay && $this !== self::Group;
    }

    public function label(): string
    {
        return match ($this) {
            self::McqSingle => 'Multiple choice (one answer)',
            self::McqMulti => 'Multiple choice (many answers)',
            self::TrueFalse => 'True / False',
            self::ShortAnswer => 'Short answer',
            self::Numeric => 'Numeric',
            self::FillBlank => 'Fill in the blank',
            self::Matching => 'Matching',
            self::Essay => 'Essay',
            self::Group => 'Question group (passage)',
        };
    }
}
