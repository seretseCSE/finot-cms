<?php

namespace App\Filament\Exports;

use App\Models\Donation;
use App\Services\ExportAuditService;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class SimpleDonationExporter extends Exporter
{
    protected static ?string $model = Donation::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('donor_name')
                ->label('Donor Name'),
            ExportColumn::make('amount')
                ->label('Amount (ETB)'),
            ExportColumn::make('donation_date')
                ->label('Donation Date'),
            ExportColumn::make('donation_type')
                ->label('Donation Type'),
            ExportColumn::make('custom_donation_type')
                ->label('Custom Type'),
            ExportColumn::make('notes')
                ->label('Notes'),
            ExportColumn::make('recordedBy.name')
                ->label('Recorded By'),
            ExportColumn::make('bankAccount.account_name')
                ->label('Bank Account'),
            ExportColumn::make('created_at')
                ->label('Created At'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        ExportAuditService::log(
            resourceType: 'donations',
            format: 'xlsx',
            recordCount: $export->successful_rows,
            filters: $export->options(),
            filePath: 'filament_exports/' . $export->getKey() . '/' . ($export->file_name ?? 'export.xlsx'),
        );

        $body = 'Your donation export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
