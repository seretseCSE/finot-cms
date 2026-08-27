<?php

namespace App\Enums;

/**
 * The three teaching stages of the MoE daily lesson plan format. Rendered
 * in this fixed order everywhere (editor, PDF), whatever order rows were
 * saved in.
 */
enum LessonStage: string
{
    case Intro = 'intro';
    case Main = 'main';
    case Conclusion = 'conclusion';

    public function label(): string
    {
        return match ($this) {
            self::Intro => 'Introduction & motivation',
            self::Main => 'Main activities',
            self::Conclusion => 'Concluding activities',
        };
    }

    public function sortOrder(): int
    {
        return match ($this) {
            self::Intro => 1,
            self::Main => 2,
            self::Conclusion => 3,
        };
    }
}
