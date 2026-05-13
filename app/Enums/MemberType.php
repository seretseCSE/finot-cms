<?php

namespace App\Enums;

enum MemberType: string
{
    case KIDS = 'Kids';
    case YOUTH = 'Youth';
    case ADULT = 'Adult';

    public function getLabel(): string
    {
        return match($this) {
            self::KIDS => 'Kids',
            self::YOUTH => 'Youth',
            self::ADULT => 'Adult',
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::KIDS => 'success',
            self::YOUTH => 'info',
            self::ADULT => 'primary',
        };
    }

    public function getIcon(): string
    {
        return match($this) {
            self::KIDS => 'heroicon-o-academic-cap',
            self::YOUTH => 'heroicon-o-user',
            self::ADULT => 'heroicon-o-user-group',
        };
    }

    public static function getAll(): array
    {
        return [
            self::KIDS->value,
            self::YOUTH->value,
            self::ADULT->value,
        ];
    }
}
