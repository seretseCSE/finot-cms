<?php

namespace App\Filament\Exports;

use App\Models\Rehearsal;
use App\Services\ExportAuditService;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class RehearsalExporter extends Exporter
{
    protected static ?string $model = Rehearsal::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('date_time')
                ->label('Date & Time'),
            ExportColumn::make('location')
                ->label('Location'),
            ExportColumn::make('status')
                ->label('Status'),
            ExportColumn::make('recurrence_type')
                ->label('Recurrence'),
            ExportColumn::make('createdBy.name')
                ->label('Created By'),
            ExportColumn::make('created_at')
                ->label('Created At'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        ExportAuditService::log(
            resourceType: 'rehearsals',
            format: 'xlsx',
            recordCount: $export->successful_rows,
            filters: $export->getOptions(),
            filePath: 'filament_exports/' . $export->getKey() . '/' . ($export->file_name ?? 'export.xlsx'),
        );

        $body = 'Your rehearsal export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
