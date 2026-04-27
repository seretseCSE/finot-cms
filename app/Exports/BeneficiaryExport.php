<?php

namespace App\Exports;

use App\Models\Beneficiary;

class BeneficiaryExport extends BaseExport
{
    public static function availableColumns(): array
    {
        return [
            'name' => 'Name',
            'status' => 'Status',
            'phone' => 'Phone',
            'address' => 'Address',
            'aid_type' => 'Aid Type',
            'created_at' => 'Created At',
        ];
    }

    public static function modelClass(): string
    {
        return Beneficiary::class;
    }

    public static function resourceType(): string
    {
        return 'beneficiaries';
    }

    public static function relationships(): array
    {
        return [];
    }

    protected function resolveColumn($record, string $column): mixed
    {
        return match ($column) {
            'name' => $record->full_name ?? $record->name,
            'status' => $record->status,
            'phone' => $record->phone,
            'address' => $record->address,
            'aid_type' => $record->aid_type ?? $record->need_category,
            'created_at' => $record->created_at?->format('M d, Y H:i'),
            default => '',
        };
    }
}
