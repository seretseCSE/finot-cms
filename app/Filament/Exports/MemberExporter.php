<?php

namespace App\Filament\Exports;

use App\Models\Member;
use App\Services\ExportAuditService;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class MemberExporter extends Exporter
{
    protected static ?string $model = Member::class;

    public function getJobQueue(): ?string
    {
        return 'default';
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('member_code')
                ->label('Member ID'),
            ExportColumn::make('full_name')
                ->label('Full Name'),
            ExportColumn::make('member_type')
                ->label('Member Type'),
            ExportColumn::make('status')
                ->label('Status'),
            ExportColumn::make('phone')
                ->label('Phone'),
            ExportColumn::make('email')
                ->label('Email'),
            ExportColumn::make('currentGroupAssignment.group.name')
                ->label('Current Group'),
            ExportColumn::make('department.name')
                ->label('Department'),
            ExportColumn::make('created_at')
                ->label('Created At'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        ExportAuditService::log(
            resourceType: 'members',
            format: $export->file_name ? pathinfo($export->file_name, PATHINFO_EXTENSION) : 'xlsx',
            recordCount: $export->successful_rows,
            filters: $export->options(),
            filePath: 'filament_exports/' . $export->getKey() . '/' . ($export->file_name ?? 'members.xlsx'),
        );

        $body = 'Your member export has completed and ' . number_format($export->successful_rows) . ' rows were exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' rows failed to export.';
        }

        return $body;
    }
}
