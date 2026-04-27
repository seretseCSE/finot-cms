<?php

namespace App\Services;

use App\Models\AidDistribution;
use App\Models\AttendanceRecord;
use App\Models\Beneficiary;
use App\Models\Contribution;
use App\Models\Donation;
use App\Models\Event;
use App\Models\FinancialTransaction;
use App\Models\InventoryItem;
use App\Models\Member;
use App\Models\PredefinedReport;
use App\Models\Rehearsal;
use App\Models\RehearsalAttendance;
use App\Models\StudentAttendance;
use App\Models\StudentEnrollment;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\Tour;
use App\Models\TourPassenger;
use Illuminate\Database\Eloquent\Builder;

class PredefinedReportService
{
    /**
     * Apply a predefined report's filters to a query builder.
     */
    public function applyReport(PredefinedReport $report, Builder $query): Builder
    {
        $criteria = $report->filter_criteria ?? [];

        foreach ($criteria as $field => $value) {
            if (blank($value)) {
                continue;
            }

            if (is_array($value)) {
                $query->whereIn($field, $value);
            } elseif (str_contains($field, '.')) {
                // Handle relationship filters
                [$relation, $column] = explode('.', $field, 2);
                $query->whereHas($relation, fn ($q) => $q->where($column, $value));
            } else {
                $query->where($field, $value);
            }
        }

        return $query;
    }

    /**
     * Get reports available for a resource type.
     */
    public function getAvailableReports(string $resourceType): array
    {
        return PredefinedReport::getActiveForResource($resourceType);
    }

    /**
     * Execute a predefined report and return results.
     */
    public function executeReport(PredefinedReport $report): array
    {
        $modelClass = $this->resolveModelClass($report->resource_type);

        if (! $modelClass) {
            throw new \RuntimeException("Unknown resource type: {$report->resource_type}");
        }

        $query = $modelClass::query();
        $query = $this->applyReport($report, $query);

        $columns = $report->columns ?? [];

        if (! empty($columns)) {
            $query->select($columns);
        }

        return [
            'report' => $report,
            'data' => $query->get(),
            'count' => $query->count(),
        ];
    }

    /**
     * Execute a report with aggregations.
     */
    public function executeReportWithAggregates(PredefinedReport $report): array
    {
        $result = $this->executeReport($report);
        $aggregates = $this->getAggregates($report->resource_type, $result['data']);

        return array_merge($result, ['aggregates' => $aggregates]);
    }

    /**
     * Get aggregate metrics for a resource type.
     */
    protected function getAggregates(string $resourceType, $data): array
    {
        return match ($resourceType) {
            'members' => [
                'total_count' => $data->count(),
                'active_count' => $data->where('status', 'Active')->count(),
                'by_gender' => $data->groupBy('gender')->map->count(),
                'by_type' => $data->groupBy('member_type')->map->count(),
            ],
            'contributions' => [
                'total_amount' => $data->sum('amount'),
                'paid_count' => $data->where('is_paid', true)->count(),
                'unpaid_count' => $data->where('is_paid', false)->count(),
            ],
            'donations' => [
                'total_amount' => $data->sum('amount'),
                'count' => $data->count(),
                'average_amount' => $data->avg('amount'),
            ],
            'financial_transactions' => [
                'total_income' => $data->where('type', 'income')->sum('amount'),
                'total_expense' => $data->where('type', 'expense')->sum('amount'),
                'net' => $data->where('type', 'income')->sum('amount') - $data->where('type', 'expense')->sum('amount'),
            ],
            'attendance' => [
                'total_records' => $data->count(),
                'present_count' => $data->where('status', 'Present')->count(),
                'absent_count' => $data->where('status', 'Absent')->count(),
                'attendance_rate' => $data->count() > 0
                    ? round(($data->where('status', 'Present')->count() / $data->count()) * 100, 2)
                    : 0,
            ],
            'aid_distributions' => [
                'total_amount' => $data->sum('amount'),
                'count' => $data->count(),
                'by_type' => $data->groupBy('aid_type')->map->count(),
            ],
            'tours' => [
                'total_count' => $data->count(),
                'total_passengers' => $data->sum(fn ($tour) => $tour->passengers?->sum('passenger_count') ?? 0),
                'total_revenue' => $data->sum(fn ($tour) => ($tour->passengers?->where('status', 'Confirmed')->sum('passenger_count') ?? 0) * $tour->cost_per_person),
            ],
            'rehearsals' => [
                'total_count' => $data->count(),
                'scheduled_count' => $data->where('status', 'Scheduled')->count(),
                'completed_count' => $data->where('status', 'Completed')->count(),
            ],
            default => [],
        };
    }

    /**
     * Get report data formatted for export.
     */
    public function getExportData(PredefinedReport $report): array
    {
        $result = $this->executeReport($report);
        $columns = $report->columns ?? [];

        if (empty($columns)) {
            $firstItem = $result['data']->first();
            if ($firstItem) {
                $columns = array_keys($firstItem->toArray());
            }
        }

        return [
            'headers' => $columns,
            'rows' => $result['data']->map(fn ($item) => $item->only($columns))->toArray(),
            'aggregates' => $this->getAggregates($report->resource_type, $result['data']),
        ];
    }

    /**
     * Resolve model class from resource type string.
     */
    protected function resolveModelClass(string $resourceType): ?string
    {
        $map = [
            'members' => Member::class,
            'contributions' => Contribution::class,
            'attendance' => AttendanceRecord::class,
            'student_attendance' => StudentAttendance::class,
            'teacher_attendance' => TeacherAttendance::class,
            'rehearsal_attendance' => RehearsalAttendance::class,
            'donations' => Donation::class,
            'financial_transactions' => FinancialTransaction::class,
            'inventory_items' => InventoryItem::class,
            'events' => Event::class,
            'tours' => Tour::class,
            'tour_passengers' => TourPassenger::class,
            'rehearsals' => Rehearsal::class,
            'teachers' => Teacher::class,
            'student_enrollments' => StudentEnrollment::class,
            'aid_distributions' => AidDistribution::class,
            'beneficiaries' => Beneficiary::class,
        ];

        return $map[$resourceType] ?? null;
    }

    /**
     * Get all available resource types.
     */
    public function getAvailableResourceTypes(): array
    {
        return [
            'members' => 'Members',
            'contributions' => 'Contributions',
            'attendance' => 'Attendance',
            'student_attendance' => 'Student Attendance',
            'teacher_attendance' => 'Teacher Attendance',
            'rehearsal_attendance' => 'Rehearsal Attendance',
            'donations' => 'Donations',
            'financial_transactions' => 'Financial Transactions',
            'inventory_items' => 'Inventory Items',
            'events' => 'Events',
            'tours' => 'Tours',
            'tour_passengers' => 'Tour Passengers',
            'rehearsals' => 'Rehearsals',
            'teachers' => 'Teachers',
            'student_enrollments' => 'Student Enrollments',
            'aid_distributions' => 'Aid Distributions',
            'beneficiaries' => 'Beneficiaries',
        ];
    }
}
