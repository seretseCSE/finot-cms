<?php

namespace App\Exports;

use App\Models\User;

class UserExport extends BaseExport
{
    public static function availableColumns(): array
    {
        return [
            'name' => 'Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'roles' => 'Roles',
            'is_active' => 'Active',
            'last_login_at' => 'Last Login',
            'created_at' => 'Created At',
        ];
    }

    public static function modelClass(): string
    {
        return User::class;
    }

    public static function resourceType(): string
    {
        return 'users';
    }

    public static function relationships(): array
    {
        return ['roles'];
    }

    protected function resolveColumn($record, string $column): mixed
    {
        return match ($column) {
            'name' => $record->name,
            'email' => $record->email,
            'phone' => $record->phone,
            'roles' => $record->roles->pluck('name')->implode(', '),
            'is_active' => $record->is_active ? 'Yes' : 'No',
            'last_login_at' => $record->last_login_at?->format('M d, Y H:i'),
            'created_at' => $record->created_at?->format('M d, Y H:i'),
            default => '',
        };
    }
}
