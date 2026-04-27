<?php

namespace App\Filament\Exports;

use App\Models\Tour;
use App\Services\ExportAuditService;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class TourExporter extends Exporter
{
    protected static ?string $model = Tour::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('place')
                ->label('Destination'),
            ExportColumn::make('tour_date')
                ->label('Tour Date'),
            ExportColumn::make('start_time')
                ->label('Start Time'),
            ExportColumn::make('cost_per_person')
                ->label('Cost Per Person'),
            ExportColumn::make('registration_deadline')
                ->label('Registration Deadline'),
            ExportColumn::make('max_capacity')
                ->label('Max Capacity'),
            ExportColumn::make('status')
                ->label('Status'),
            ExportColumn::make('createdBy.name')
                ->label('Created By'),
            ExportColumn::make('created_at')
                ->label('Created At'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        ExportAuditService::log(
            resourceType: 'tours',
            format: 'xlsx',
            recordCount: $export->successful_rows,
            filters: $export->getOptions(),
            filePath: 'filament_exports/' . $export->getKey() . '/' . ($export->file_name ?? 'export.xlsx'),
        );

        $body = 'Your tour export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
