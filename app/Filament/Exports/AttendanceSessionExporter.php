<?php

namespace App\Filament\Exports;

use App\Models\AttendanceSession;
use App\Services\ExportAuditService;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class AttendanceSessionExporter extends Exporter
{
    protected static ?string $model = AttendanceSession::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('class.name')
                ->label('Class'),
            ExportColumn::make('session_date')
                ->label('Session Date'),
            ExportColumn::make('academicYear.name')
                ->label('Academic Year'),
            ExportColumn::make('status')
                ->label('Status'),
            ExportColumn::make('locked_at')
                ->label('Locked At'),
            ExportColumn::make('createdBy.name')
                ->label('Created By'),
            ExportColumn::make('created_at')
                ->label('Created At'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        ExportAuditService::log(
            resourceType: 'attendance_sessions',
            format: 'xlsx',
            recordCount: $export->successful_rows,
            filters: $export->getOptions(),
            filePath: 'filament_exports/' . $export->getKey() . '/' . ($export->file_name ?? 'export.xlsx'),
        );

        $body = 'Your attendance session export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
