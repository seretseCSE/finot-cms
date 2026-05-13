<?php

namespace App\Enums;

enum OccupationStatus: string
{
    case STUDENT = 'Student';
    case EMPLOYEE = 'Employee';

    public function getLabel(): string
    {
        return match($this) {
            self::STUDENT => 'Student',
            self::EMPLOYEE => 'Employee',
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::STUDENT => 'info',
            self::EMPLOYEE => 'success',
        };
    }

    public function getIcon(): string
    {
        return match($this) {
            self::STUDENT => 'heroicon-o-academic-cap',
            self::EMPLOYEE => 'heroicon-o-briefcase',
        };
    }

    public static function getAll(): array
    {
        return [
            self::STUDENT->value,
            self::EMPLOYEE->value,
        ];
    }
}
