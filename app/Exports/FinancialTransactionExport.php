<?php

namespace App\Exports;

use App\Models\FinancialTransaction;
use App\Services\ExportAuditService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class FinancialTransactionExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        $records = FinancialTransaction::with(['bankAccount', 'recordedBy', 'approvedBy'])->get();

        ExportAuditService::log(
            resourceType: 'financial_transactions',
            format: 'xlsx',
            recordCount: $records->count(),
            filePath: 'exports/financial_transactions.xlsx',
        );

        return $records;
    }

    public function headings(): array
    {
        return [
            'Transaction ID',
            'Type',
            'Title',
            'Description',
            'Amount (ETB)',
            'Currency',
            'Category',
            'Source/Payer',
            'Payment Method',
            'Bank Account',
            'Transaction Date',
            'Recorded By',
            'Approved By',
            'Approved At',
            'Created At',
        ];
    }

    public function map($transaction): array
    {
        return [
            $transaction->transaction_id,
            $transaction->type,
            $transaction->title,
            $transaction->description,
            $transaction->amount,
            $transaction->currency,
            $transaction->category,
            $transaction->source,
            $transaction->payment_method,
            $transaction->bankAccount?->account_name,
            $transaction->transaction_date?->format('M d, Y'),
            $transaction->recordedBy?->name,
            $transaction->approvedBy?->name,
            $transaction->approved_at?->format('M d, Y H:i'),
            $transaction->created_at?->format('M d, Y H:i'),
        ];
    }
}
