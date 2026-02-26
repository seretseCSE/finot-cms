<?php

namespace App\Filament\Exports;

use App\Models\Member;
use App\Models\ExportLog;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Facades\Auth;

class MemberExporter extends Exporter
{
    protected static ?string $model = Member::class;

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
            ExportColumn::make('currentGroup.name')
                ->label('Current Group'),
            ExportColumn::make('department.name')
                ->label('Department'),
            ExportColumn::make('created_at')
                ->label('Created At'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        // Write to export_logs
        ExportLog::create([
            'resource_type' => 'members',
            'format' => $export->file_name ? pathinfo($export->file_name, PATHINFO_EXTENSION) : 'xlsx',
            'file_path' => 'filament_exports/' . $export->getKey() . '/' . ($export->file_name ?? 'members.xlsx'),
            'record_count' => $export->successful_rows,
            'exported_by' => Auth::id(),
            'created_at' => now(),
        ]);

        $body = 'Your member export has completed and ' . number_format($export->successful_rows) . ' rows were exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' rows failed to export.';
        }

        return $body;
    }
}

