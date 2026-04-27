<?php

namespace App\Exports;

use App\Models\User;
use App\Services\ExportAuditService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UserExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        $records = User::with('roles')->get();

        ExportAuditService::log(
            resourceType: 'users',
            format: 'xlsx',
            recordCount: $records->count(),
            filePath: 'exports/users.xlsx',
        );

        return $records;
    }

    public function headings(): array
    {
        return [
            'Name',
            'Email',
            'Phone',
            'Roles',
            'Active',
            'Last Login',
            'Created At',
        ];
    }

    public function map($user): array
    {
        return [
            $user->name,
            $user->email,
            $user->phone,
            $user->roles->pluck('name')->implode(', '),
            $user->is_active ? 'Yes' : 'No',
            $user->last_login_at?->format('M d, Y H:i'),
            $user->created_at?->format('M d, Y H:i'),
        ];
    }
}
