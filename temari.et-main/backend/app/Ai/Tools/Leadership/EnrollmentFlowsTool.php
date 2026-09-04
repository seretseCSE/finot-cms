<?php

namespace App\Ai\Tools\Leadership;

use App\Models\StudentEnrollment;
use App\Models\StudentTransferRequest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Enrollment movement for the active year: intake, exits (withdrawn /
 * transferred out) and transfer traffic — the retention picture.
 */
class EnrollmentFlowsTool extends LeadershipScopedTool
{
    public function description(): Stringable|string
    {
        return 'Enrollment flows for the active year: enrollments by status, new intakes per month, withdrawals, and transfer requests in/out. Use for retention, growth, or "how many students joined/left" questions.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (($denied = $this->missingPermission('students.view', 'reports.view')) !== null) {
            return $this->deny($denied);
        }

        $branchIds = $this->branchIds($request->integer('branch_id') ?: null);

        $byStatus = StudentEnrollment::query()
            ->whereIn('branch_id', $branchIds)
            ->whereHas('academicYear', fn ($q) => $q->where('status', 'active'))
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $monthlyIntake = StudentEnrollment::query()
            ->whereIn('branch_id', $branchIds)
            ->whereHas('academicYear', fn ($q) => $q->where('status', 'active'))
            ->whereNotNull('enrolled_on')
            ->selectRaw("to_char(enrolled_on, 'YYYY-MM') as month, count(*) as enrolled")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn ($row): array => ['month' => $row->month, 'enrolled' => (int) $row->enrolled]);

        $transfersIn = StudentTransferRequest::query()
            ->whereIn('to_branch_id', $branchIds)
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $transfersOut = StudentTransferRequest::query()
            ->whereIn('from_branch_id', $branchIds)
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return $this->ok([
            'enrollments_by_status' => $byStatus,
            'monthly_intake' => $monthlyIntake,
            'transfer_requests_in' => $transfersIn,
            'transfer_requests_out' => $transfersOut,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'branch_id' => $schema->integer()->description('School-wide sessions only: narrow to one branch.'),
        ];
    }
}
