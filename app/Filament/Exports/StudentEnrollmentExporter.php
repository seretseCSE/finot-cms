<?php

namespace App\Filament\Exports;

use App\Models\StudentEnrollment;
use App\Services\ExportAuditService;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class StudentEnrollmentExporter extends Exporter
{
    protected static ?string $model = StudentEnrollment::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('student.full_name')
                ->label('Student'),
            ExportColumn::make('class.name')
                ->label('Class'),
            ExportColumn::make('academicYear.name')
                ->label('Academic Year'),
            ExportColumn::make('enrollment_date')
                ->label('Enrollment Date'),
            ExportColumn::make('status')
                ->label('Status'),
            ExportColumn::make('created_at')
                ->label('Created At'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        ExportAuditService::log(
            resourceType: 'student_enrollments',
            format: 'xlsx',
            recordCount: $export->successful_rows,
            filters: $export->getOptions(),
            filePath: 'filament_exports/' . $export->getKey() . '/' . ($export->file_name ?? 'export.xlsx'),
        );

        $body = 'Your student enrollment export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
