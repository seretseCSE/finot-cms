<?php

namespace App\Filament\Exports;

use App\Models\AidDistribution;
use App\Services\ExportAuditService;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class AidDistributionExporter extends Exporter
{
    protected static ?string $model = AidDistribution::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('beneficiary.name')
                ->label('Beneficiary'),
            ExportColumn::make('distribution_date')
                ->label('Distribution Date'),
            ExportColumn::make('aid_type')
                ->label('Aid Type'),
            ExportColumn::make('amount')
                ->label('Amount (ETB)'),
            ExportColumn::make('distributedBy.name')
                ->label('Distributed By'),
            ExportColumn::make('receipt_number')
                ->label('Receipt Number'),
            ExportColumn::make('is_locked')
                ->label('Locked'),
            ExportColumn::make('created_at')
                ->label('Created At'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        ExportAuditService::log(
            resourceType: 'aid_distributions',
            format: 'xlsx',
            recordCount: $export->successful_rows,
            filters: $export->options(),
            filePath: 'filament_exports/' . $export->getKey() . '/' . ($export->file_name ?? 'export.xlsx'),
        );

        $body = 'Your aid distribution export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
