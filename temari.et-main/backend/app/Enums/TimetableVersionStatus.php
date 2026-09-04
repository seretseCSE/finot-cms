<?php

namespace App\Enums;

/**
 * Draft → (generating ⇄ draft) → published → archived. Only draft versions
 * are editable; publishing archives the previously published version of the
 * same term so history is never destroyed.
 */
enum TimetableVersionStatus: string
{
    case Draft = 'draft';
    case Generating = 'generating';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Generating => 'Generating',
            self::Published => 'Published',
            self::Archived => 'Archived',
        };
    }
}
