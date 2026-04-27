<?php

namespace App\Filament\Exports;

use App\Models\Contribution;
use App\Services\ExportAuditService;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class ContributionExporter extends Exporter
{
    protected static ?string $model = Contribution::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('member.first_name')
                ->label('First Name'),
            ExportColumn::make('member.father_name')
                ->label('Father Name'),
            ExportColumn::make('member.member_code')
                ->label('Member Code'),
            ExportColumn::make('academicYear.name')
                ->label('Academic Year'),
            ExportColumn::make('month_name')
                ->label('Month'),
            ExportColumn::make('amount')
                ->label('Amount (ETB)'),
            ExportColumn::make('payment_date')
                ->label('Payment Date'),
            ExportColumn::make('payment_method')
                ->label('Payment Method'),
            ExportColumn::make('status')
                ->label('Status'),
            ExportColumn::make('notes')
                ->label('Notes'),
            ExportColumn::make('recordedBy.name')
                ->label('Recorded By'),
            ExportColumn::make('created_at')
                ->label('Created At'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        ExportAuditService::log(
            resourceType: 'contributions',
            format: 'xlsx',
            recordCount: $export->successful_rows,
            filters: $export->getOptions(),
            filePath: 'filament_exports/' . $export->getKey() . '/' . ($export->file_name ?? 'export.xlsx'),
        );

        $body = 'Your contribution export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
