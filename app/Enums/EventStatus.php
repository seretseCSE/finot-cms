<?php

namespace App\Enums;

enum EventStatus: string
{
    case DRAFT = 'Draft';
    case PUBLISHED = 'Published';
    case FULL = 'Full';
    case ONGOING = 'Ongoing';
    case COMPLETED = 'Completed';
    case CANCELLED = 'Cancelled';

    public function getLabel(): string
    {
        return match($this) {
            self::DRAFT => 'Draft',
            self::PUBLISHED => 'Published',
            self::FULL => 'Full',
            self::ONGOING => 'Ongoing',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::DRAFT => 'gray',
            self::PUBLISHED => 'info',
            self::FULL => 'warning',
            self::ONGOING => 'primary',
            self::COMPLETED => 'success',
            self::CANCELLED => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match($this) {
            self::DRAFT => 'heroicon-o-document-text',
            self::PUBLISHED => 'heroicon-o-eye',
            self::FULL => 'heroicon-o-exclamation-triangle',
            self::ONGOING => 'heroicon-o-play-circle',
            self::COMPLETED => 'heroicon-o-check-circle',
            self::CANCELLED => 'heroicon-o-x-circle',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::PUBLISHED, self::FULL, self::ONGOING]);
    }

    public function isInactive(): bool
    {
        return in_array($this, [self::DRAFT, self::COMPLETED, self::CANCELLED]);
    }

    public static function getAll(): array
    {
        return [
            self::DRAFT->value => self::DRAFT->getLabel(),
            self::PUBLISHED->value => self::PUBLISHED->getLabel(),
            self::CANCELLED->value => self::CANCELLED->getLabel(),
            self::COMPLETED->value => self::COMPLETED->getLabel(),
        ];
    }
}
