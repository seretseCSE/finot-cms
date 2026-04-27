<?php

namespace App\Exports;

use App\Models\Contribution;
use App\Services\ExportAuditService;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ContributionExport implements FromCollection, WithHeadings, WithMapping
{
    protected ?Builder $query = null;

    public function __construct(?Builder $query = null)
    {
        $this->query = $query;
    }

    public function collection()
    {
        if ($this->query) {
            $records = $this->query->with(['member', 'academicYear', 'recordedBy'])->get();
        } else {
            $records = Contribution::with(['member', 'academicYear', 'recordedBy'])->get();
        }

        ExportAuditService::log(
            resourceType: 'contributions',
            format: 'xlsx',
            recordCount: $records->count(),
            filePath: 'exports/contributions.xlsx',
        );

        return $records;
    }

    public function headings(): array
    {
        return [
            'ID',
            'First Name',
            'Father Name',
            'Member Code',
            'Academic Year',
            'Month',
            'Amount (ETB)',
            'Payment Date',
            'Payment Method',
            'Status',
            'Notes',
            'Recorded By',
            'Created At',
        ];
    }

    public function map($contribution): array
    {
        return [
            $contribution->id,
            $contribution->member?->first_name,
            $contribution->member?->father_name,
            $contribution->member?->member_code,
            $contribution->academicYear?->name,
            $contribution->month_name,
            $contribution->amount,
            $contribution->payment_date?->format('M d, Y'),
            $contribution->payment_method,
            $contribution->status,
            $contribution->notes,
            $contribution->recordedBy?->name,
            $contribution->created_at?->format('M d, Y H:i'),
        ];
    }
}
