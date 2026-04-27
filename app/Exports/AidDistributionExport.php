<?php

namespace App\Exports;

use App\Models\AidDistribution;
use App\Services\ExportAuditService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AidDistributionExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        $records = AidDistribution::with(['beneficiary', 'distributedBy'])->get();

        ExportAuditService::log(
            resourceType: 'aid_distributions',
            format: 'xlsx',
            recordCount: $records->count(),
            filePath: 'exports/aid_distributions.xlsx',
        );

        return $records;
    }

    public function headings(): array
    {
        return [
            'Beneficiary',
            'Distribution Date',
            'Aid Type',
            'Amount (ETB)',
            'Distributed By',
            'Receipt Number',
            'Locked',
            'Created At',
        ];
    }

    public function map($distribution): array
    {
        return [
            $distribution->beneficiary?->name,
            $distribution->distribution_date?->format('M d, Y'),
            $distribution->aid_type,
            $distribution->amount,
            $distribution->distributedBy?->name,
            $distribution->receipt_number,
            $distribution->is_locked ? 'Yes' : 'No',
            $distribution->created_at?->format('M d, Y H:i'),
        ];
    }
}
