<?php

namespace App\Enums;

enum BeneficiaryType: string
{
    case INDIVIDUAL = 'Individual';
    case FAMILY = 'Family';
    case ORGANIZATION = 'Organization';

    public function getLabel(): string
    {
        return match($this) {
            self::INDIVIDUAL => 'Individual',
            self::FAMILY => 'Family',
            self::ORGANIZATION => 'Organization',
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::INDIVIDUAL => 'primary',
            self::FAMILY => 'info',
            self::ORGANIZATION => 'warning',
        };
    }

    public function getIcon(): string
    {
        return match($this) {
            self::INDIVIDUAL => 'heroicon-o-user',
            self::FAMILY => 'heroicon-o-user-group',
            self::ORGANIZATION => 'heroicon-o-building-office',
        };
    }

    public static function getAll(): array
    {
        return [
            self::INDIVIDUAL->value,
            self::FAMILY->value,
            self::ORGANIZATION->value,
        ];
    }
}
