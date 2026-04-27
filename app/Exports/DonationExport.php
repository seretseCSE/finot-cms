<?php

namespace App\Exports;

use App\Models\Donation;

class DonationExport extends BaseExport
{
    public static function availableColumns(): array
    {
        return [
            'id' => 'ID',
            'donor_name' => 'Donor Name',
            'amount' => 'Amount (ETB)',
            'donation_date' => 'Donation Date',
            'donation_type' => 'Donation Type',
            'custom_donation_type' => 'Custom Type',
            'notes' => 'Notes',
            'recorded_by' => 'Recorded By',
            'bank_account' => 'Bank Account',
            'created_at' => 'Created At',
        ];
    }

    public static function modelClass(): string
    {
        return Donation::class;
    }

    public static function resourceType(): string
    {
        return 'donations';
    }

    public static function relationships(): array
    {
        return ['recordedBy', 'bankAccount'];
    }

    protected function resolveColumn($record, string $column): mixed
    {
        return match ($column) {
            'id' => $record->id,
            'donor_name' => $record->donor_name,
            'amount' => $record->amount,
            'donation_date' => $record->donation_date?->format('M d, Y'),
            'donation_type' => $record->donation_type,
            'custom_donation_type' => $record->custom_donation_type,
            'notes' => $record->notes,
            'recorded_by' => $record->recordedBy?->name,
            'bank_account' => $record->bankAccount?->account_name,
            'created_at' => $record->created_at?->format('M d, Y H:i'),
            default => '',
        };
    }
}
