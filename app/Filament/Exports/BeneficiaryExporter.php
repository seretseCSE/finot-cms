<?php

namespace App\Filament\Exports;

use App\Models\Beneficiary;
use App\Services\ExportAuditService;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class BeneficiaryExporter extends Exporter
{
    protected static ?string $model = Beneficiary::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('name')
                ->label('Name'),
            ExportColumn::make('status')
                ->label('Status'),
            ExportColumn::make('phone')
                ->label('Phone'),
            ExportColumn::make('address')
                ->label('Address'),
            ExportColumn::make('aid_type')
                ->label('Aid Type'),
            ExportColumn::make('created_at')
                ->label('Created At'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        ExportAuditService::log(
            resourceType: 'beneficiaries',
            format: 'xlsx',
            recordCount: $export->successful_rows,
            filters: $export->options(),
            filePath: 'filament_exports/' . $export->getKey() . '/' . ($export->file_name ?? 'export.xlsx'),
        );

        $body = 'Your beneficiary export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
