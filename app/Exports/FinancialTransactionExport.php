<?php

namespace App\Exports;

use App\Models\FinancialTransaction;

class FinancialTransactionExport extends BaseExport
{
    public static function availableColumns(): array
    {
        return [
            'transaction_id' => 'Transaction ID',
            'type' => 'Type',
            'title' => 'Title',
            'description' => 'Description',
            'amount' => 'Amount (ETB)',
            'currency' => 'Currency',
            'category' => 'Category',
            'source' => 'Source/Payer',
            'payment_method' => 'Payment Method',
            'bank_account' => 'Bank Account',
            'transaction_date' => 'Transaction Date',
            'recorded_by' => 'Recorded By',
            'approved_by' => 'Approved By',
            'approved_at' => 'Approved At',
            'created_at' => 'Created At',
        ];
    }

    public static function modelClass(): string
    {
        return FinancialTransaction::class;
    }

    public static function resourceType(): string
    {
        return 'financial_transactions';
    }

    public static function relationships(): array
    {
        return ['bankAccount', 'recordedBy', 'approvedBy'];
    }

    protected function resolveColumn($record, string $column): mixed
    {
        return match ($column) {
            'transaction_id' => $record->transaction_id,
            'type' => $record->type,
            'title' => $record->title,
            'description' => $record->description,
            'amount' => $record->amount,
            'currency' => $record->currency,
            'category' => $record->category,
            'source' => $record->source,
            'payment_method' => $record->payment_method,
            'bank_account' => $record->bankAccount?->account_name,
            'transaction_date' => $record->transaction_date?->format('M d, Y'),
            'recorded_by' => $record->recordedBy?->name,
            'approved_by' => $record->approvedBy?->name,
            'approved_at' => $record->approved_at?->format('M d, Y H:i'),
            'created_at' => $record->created_at?->format('M d, Y H:i'),
            default => '',
        };
    }
}
