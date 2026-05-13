<?php

namespace App\Enums;

enum Gender: string
{
    case MALE = 'Male';
    case FEMALE = 'Female';

    public function getLabel(): string
    {
        return match($this) {
            self::MALE => 'Male',
            self::FEMALE => 'Female',
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::MALE => 'blue',
            self::FEMALE => 'pink',
        };
    }

    public function getIcon(): string
    {
        return match($this) {
            self::MALE => 'heroicon-o-user',
            self::FEMALE => 'heroicon-o-user',
        };
    }

    public static function getAll(): array
    {
        return [
            self::MALE->value,
            self::FEMALE->value,
        ];
    }
}
