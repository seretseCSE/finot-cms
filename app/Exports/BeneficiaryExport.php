<?php

namespace App\Exports;

use App\Models\Beneficiary;
use App\Services\ExportAuditService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class BeneficiaryExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        $records = Beneficiary::all();

        ExportAuditService::log(
            resourceType: 'beneficiaries',
            format: 'xlsx',
            recordCount: $records->count(),
            filePath: 'exports/beneficiaries.xlsx',
        );

        return $records;
    }

    public function headings(): array
    {
        return [
            'Name',
            'Status',
            'Phone',
            'Address',
            'Aid Type',
            'Created At',
        ];
    }

    public function map($beneficiary): array
    {
        return [
            $beneficiary->name,
            $beneficiary->status,
            $beneficiary->phone,
            $beneficiary->address,
            $beneficiary->aid_type,
            $beneficiary->created_at?->format('M d, Y H:i'),
        ];
    }
}
