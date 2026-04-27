<?php

namespace App\Filament\Exports;

use App\Models\FinancialTransaction;
use App\Services\ExportAuditService;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class FinancialTransactionExporter extends Exporter
{
    protected static ?string $model = FinancialTransaction::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('transaction_id')
                ->label('Transaction ID'),

            ExportColumn::make('type')
                ->label('Type'),

            ExportColumn::make('title')
                ->label('Title'),

            ExportColumn::make('description')
                ->label('Description'),

            ExportColumn::make('amount')
                ->label('Amount (ETB)'),

            ExportColumn::make('currency')
                ->label('Currency'),

            ExportColumn::make('category')
                ->label('Category'),

            ExportColumn::make('source')
                ->label('Source/Payer'),

            ExportColumn::make('payment_method')
                ->label('Payment Method'),

            ExportColumn::make('bankAccount.account_name')
                ->label('Bank Account'),

            ExportColumn::make('transaction_date')
                ->label('Transaction Date'),

            ExportColumn::make('recordedBy.name')
                ->label('Recorded By'),

            ExportColumn::make('approvedBy.name')
                ->label('Approved By'),

            ExportColumn::make('approved_at')
                ->label('Approved At'),

            ExportColumn::make('created_at')
                ->label('Created At'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        ExportAuditService::log(
            resourceType: 'financial_transactions',
            format: 'xlsx',
            recordCount: $export->successful_rows,
            filters: $export->getOptions(),
            filePath: 'filament_exports/' . $export->getKey() . '/' . ($export->file_name ?? 'export.xlsx'),
        );

        $body = 'Your financial transaction export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
