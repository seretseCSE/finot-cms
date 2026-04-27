<?php

namespace App\Exports;

use App\Models\AidDistribution;

class AidDistributionExport extends BaseExport
{
    public static function availableColumns(): array
    {
        return [
            'beneficiary' => 'Beneficiary',
            'distribution_date' => 'Distribution Date',
            'aid_type' => 'Aid Type',
            'amount' => 'Amount (ETB)',
            'distributed_by' => 'Distributed By',
            'receipt_number' => 'Receipt Number',
            'is_locked' => 'Locked',
            'created_at' => 'Created At',
        ];
    }

    public static function modelClass(): string
    {
        return AidDistribution::class;
    }

    public static function resourceType(): string
    {
        return 'aid_distributions';
    }

    public static function relationships(): array
    {
        return ['beneficiary', 'distributedBy'];
    }

    protected function resolveColumn($record, string $column): mixed
    {
        return match ($column) {
            'beneficiary' => $record->beneficiary?->name ?? $record->beneficiary?->full_name,
            'distribution_date' => $record->distribution_date?->format('M d, Y'),
            'aid_type' => $record->aid_type,
            'amount' => $record->amount,
            'distributed_by' => $record->distributedBy?->name,
            'receipt_number' => $record->receipt_number,
            'is_locked' => $record->is_locked ? 'Yes' : 'No',
            'created_at' => $record->created_at?->format('M d, Y H:i'),
            default => '',
        };
    }
}
