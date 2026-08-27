<?php

namespace App\Enums;

/**
 * Term lifecycle: planned → active → closed. A closed term is read-only for
 * every academic record anchored to it (enforced by App\Support\TermGate).
 */
enum TermStatus: string
{
    case Planned = 'planned';
    case Active = 'active';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Planned',
            self::Active => 'Active',
            self::Closed => 'Closed',
        };
    }
}
