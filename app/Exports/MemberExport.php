<?php

namespace App\Exports;

use App\Models\Member;
use App\Services\ExportAuditService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class MemberExport implements FromCollection, WithHeadings, WithMapping
{
    protected ?array $ids = null;

    public function __construct(?array $ids = null)
    {
        $this->ids = $ids;
    }

    public function collection()
    {
        $query = Member::query();

        if ($this->ids !== null) {
            $query->whereIn('id', $this->ids);
        }

        $records = $query->get();

        ExportAuditService::log(
            resourceType: 'members',
            format: 'xlsx',
            recordCount: $records->count(),
            filePath: 'exports/members.xlsx',
        );

        return $records;
    }

    public function headings(): array
    {
        return [
            'Member ID',
            'First Name',
            'Father Name',
            'Grandfather Name',
            'Member Type',
            'Status',
            'Phone',
            'Email',
            'Created At',
        ];
    }

    public function map($member): array
    {
        return [
            $member->member_code,
            $member->first_name,
            $member->father_name,
            $member->grandfather_name,
            $member->member_type,
            $member->status,
            $member->phone,
            $member->email,
            $member->created_at?->format('M d, Y H:i'),
        ];
    }
}
