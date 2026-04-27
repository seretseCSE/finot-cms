<?php

namespace App\Exports;

use App\Models\Donation;
use App\Services\ExportAuditService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class DonationExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        $records = Donation::with(['recordedBy', 'bankAccount'])->get();

        ExportAuditService::log(
            resourceType: 'donations',
            format: 'xlsx',
            recordCount: $records->count(),
            filePath: 'exports/donations.xlsx',
        );

        return $records;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Donor Name',
            'Amount (ETB)',
            'Donation Date',
            'Donation Type',
            'Custom Type',
            'Notes',
            'Recorded By',
            'Bank Account',
            'Created At',
        ];
    }

    public function map($donation): array
    {
        return [
            $donation->id,
            $donation->donor_name,
            $donation->amount,
            $donation->donation_date?->format('M d, Y'),
            $donation->donation_type,
            $donation->custom_donation_type,
            $donation->notes,
            $donation->recordedBy?->name,
            $donation->bankAccount?->account_name,
            $donation->created_at?->format('M d, Y H:i'),
        ];
    }
}
