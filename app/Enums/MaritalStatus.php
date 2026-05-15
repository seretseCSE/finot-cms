<?php

namespace App\Enums;

enum MaritalStatus: string
{
    case SINGLE = 'Single';
    case MARRIED = 'Married';

    public function getLabel(): string
    {
        return match($this) {
            self::SINGLE => 'Single',
            self::MARRIED => 'Married',
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::SINGLE => 'gray',
            self::MARRIED => 'success',
        };
    }

    public function getIcon(): string
    {
        return match($this) {
            self::SINGLE => 'heroicon-o-user',
            self::MARRIED => 'heroicon-o-heart',
        };
    }

    public static function getAll(): array
    {
        return [
            self::SINGLE->value => self::SINGLE->getLabel(),
            self::MARRIED->value => self::MARRIED->getLabel(),
        ];
    }
}
