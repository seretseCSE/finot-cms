<?php

namespace App\Enums;

/**
 * How a guardian relates to a student. `relationship` style labels live in the
 * frontend i18n layer; this enum is the canonical machine value.
 */
enum GuardianRelationship: string
{
    case Father = 'father';
    case Mother = 'mother';
    case Grandfather = 'grandfather';
    case Grandmother = 'grandmother';
    case Uncle = 'uncle';
    case Aunt = 'aunt';
    case Sibling = 'sibling';
    case LegalGuardian = 'legal_guardian';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Father => 'Father',
            self::Mother => 'Mother',
            self::Grandfather => 'Grandfather',
            self::Grandmother => 'Grandmother',
            self::Uncle => 'Uncle',
            self::Aunt => 'Aunt',
            self::Sibling => 'Sibling',
            self::LegalGuardian => 'Legal guardian',
            self::Other => 'Other',
        };
    }
}
