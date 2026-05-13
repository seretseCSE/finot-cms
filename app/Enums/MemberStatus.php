<?php

namespace App\Enums;

enum MemberStatus: string
{
    case DRAFT = 'Draft';
    case MEMBER = 'Member';
    case ACTIVE = 'Active';
    case FORMER = 'Former';

    public function getLabel(): string
    {
        return match($this) {
            self::DRAFT => 'Draft',
            self::MEMBER => 'Member',
            self::ACTIVE => 'Active',
            self::FORMER => 'Former',
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::DRAFT => 'gray',
            self::MEMBER => 'warning',
            self::ACTIVE => 'success',
            self::FORMER => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match($this) {
            self::DRAFT => 'heroicon-o-document-text',
            self::MEMBER => 'heroicon-o-user-clock',
            self::ACTIVE => 'heroicon-o-check-circle',
            self::FORMER => 'heroicon-o-user-x-mark',
        };
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function isInactive(): bool
    {
        return in_array($this, [self::DRAFT, self::FORMER]);
    }

    public static function getAll(): array
    {
        return [
            self::DRAFT->value,
            self::MEMBER->value,
            self::ACTIVE->value,
            self::FORMER->value,
        ];
    }
}
