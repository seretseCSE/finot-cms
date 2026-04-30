<?php

namespace App\Exports;

use App\Models\Contribution;
use Illuminate\Database\Eloquent\Builder;

class ContributionExport extends BaseExport
{
    public static function availableColumns(): array
    {
        return [
            'id' => 'ID',
            'first_name' => 'First Name',
            'father_name' => 'Father Name',
            'member_code' => 'Member Code',
            'academic_year' => 'Academic Year',
            'month' => 'Month',
            'amount' => 'Amount (ETB)',
            'payment_date' => 'Payment Date',
            'payment_method' => 'Payment Method',
            'status' => 'Status',
            'notes' => 'Notes',
            'recorded_by' => 'Recorded By',
            'created_at' => 'Created At',
        ];
    }

    public static function modelClass(): string
    {
        return Contribution::class;
    }

    public static function resourceType(): string
    {
        return 'contributions';
    }

    public static function relationships(): array
    {
        return ['member', 'academicYear', 'recordedBy'];
    }

    protected function buildQuery(): Builder
    {
        $query = Contribution::with(static::relationships())
            ->orderBy('payment_date', 'desc');

        if ($this->filters) {
            if (! empty($this->filters['academic_year_id'])) {
                $query->where('academic_year_id', $this->filters['academic_year_id']);
            }
            if (! empty($this->filters['group_id'])) {
                $query->whereHas('member.currentGroupAssignment', fn ($q) => $q->where('group_id', $this->filters['group_id']));
            }
            if (! empty($this->filters['start_date'])) {
                $query->whereDate('payment_date', '>=', $this->filters['start_date']);
            }
            if (! empty($this->filters['end_date'])) {
                $query->whereDate('payment_date', '<=', $this->filters['end_date']);
            }
        }

        return $query;
    }

    protected function resolveColumn($record, string $column): mixed
    {
        return match ($column) {
            'id' => $record->id,
            'first_name' => $record->member?->first_name,
            'father_name' => $record->member?->father_name,
            'member_code' => $record->member?->member_code,
            'academic_year' => $record->academicYear?->name,
            'month' => $record->month_name,
            'amount' => $record->amount,
            'payment_date' => $record->payment_date?->format('M d, Y'),
            'payment_method' => $record->payment_method,
            'status' => $record->status,
            'notes' => $record->notes,
            'recorded_by' => $record->recordedBy?->name,
            'created_at' => $record->created_at?->format('M d, Y H:i'),
            default => '',
        };
    }
}
