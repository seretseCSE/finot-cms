<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Services\Reports\AttendanceReportQuery;
use App\Services\Reports\AttendanceReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Student attendance analytics. One controller, four read-only endpoints,
 * every caller sees exactly the slice their memberships allow:
 *
 *  - Temari.et platform staff → system-wide, narrowable to a school/branch;
 *  - principals / school admins (school-wide workspace) → their school,
 *    narrowable to a branch (no context switch — ADR school-wide targeting);
 *  - directors / registrars (branch context) → their branch;
 *  - teachers (attendance.view_own, no reports permission) → ONLY the
 *    sections they homeroom, mirroring the register lane.
 *
 * Everything below the scope (grade, section, date window, manual/device
 * source, individual terminal) is a filter, not an authority decision.
 */
class AttendanceReportController extends Controller
{
    use HandlesListQueries;

    public function overview(Request $request, AttendanceReportService $reports): JsonResponse
    {
        $query = $this->query($request);

        return response()->json([
            'data' => $reports->overview($query),
            'meta' => [
                'from' => $query->from,
                'to' => $query->to,
                // The device-filter options ride along: registrars can hold
                // the reports permission without devices.view.
                'devices' => $reports->deviceOptions($query),
            ],
        ]);
    }

    public function trends(Request $request, AttendanceReportService $reports): JsonResponse
    {
        $query = $this->query($request);

        return response()->json([
            'data' => $reports->trends($query),
            'meta' => ['from' => $query->from, 'to' => $query->to],
        ]);
    }

    public function students(Request $request, AttendanceReportService $reports): JsonResponse
    {
        $query = $this->query($request);

        $paginator = $reports->students($query, $this->studentOptions($request), $this->perPage($request));

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /** Full filtered result for CSV/Excel export (capped server-side). */
    public function studentsExport(Request $request, AttendanceReportService $reports): JsonResponse
    {
        $query = $this->query($request);

        return response()->json([
            'data' => $reports->studentsExport($query, $this->studentOptions($request)),
        ]);
    }

    /**
     * Authorise the caller and resolve the slice of the register they may
     * aggregate over. hasContextPermission() validates the X-School-Id /
     * X-Branch-Id context against real memberships, so the scope ids fed to
     * the service can never be spoofed wider than the caller's authority.
     */
    private function query(Request $request): AttendanceReportQuery
    {
        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'school_id' => ['nullable', 'integer'],
            'branch_id' => ['nullable', 'integer'],
            'grade_level_id' => ['nullable', 'integer'],
            'section_id' => ['nullable', 'integer'],
            'source' => ['nullable', Rule::in(['manual', 'device'])],
            'device_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $supervisory = $user->hasContextPermission('attendance.reports.view');

        abort_unless($supervisory || $user->hasContextPermission('attendance.view_own'), 403);

        $from = $request->date('from')?->toDateString() ?? now()->subDays(29)->toDateString();
        $to = $request->date('to')?->toDateString() ?? now()->toDateString();
        abort_if($from > $to, 422, 'The start of the window must be before its end.');

        // Ownership lane: a teacher reads the same reports, capped to the
        // sections they homeroom — identical to the register's read lane.
        if (! $supervisory) {
            $branch = $this->activeBranchOrNull($request);
            abort_if($branch === null, 422, 'Select a branch to continue.');

            return $this->buildQuery(
                $request,
                schoolId: $branch->school_id,
                branchId: $branch->id,
                allowedSectionIds: $user->homeroomSectionIdsInBranch($branch->id),
                from: $from,
                to: $to,
            );
        }

        $branch = $this->activeBranchOrNull($request);
        $schoolScopeId = $this->activeSchoolScopeId($request);

        return $this->buildQuery(
            $request,
            // Platform staff may narrow to one school; school staff are
            // pinned to theirs (a foreign school_id can then only yield
            // an empty set, never another tenant's rows).
            schoolId: $branch?->school_id
                ?? $schoolScopeId
                ?? ($request->filled('school_id') ? $request->integer('school_id') : null),
            branchId: $branch?->id ?? $this->branchFilterId($request, $branch),
            allowedSectionIds: null,
            from: $from,
            to: $to,
        );
    }

    /**
     * @param  list<int>|null  $allowedSectionIds
     */
    private function buildQuery(
        Request $request,
        ?int $schoolId,
        ?int $branchId,
        ?array $allowedSectionIds,
        string $from,
        string $to,
    ): AttendanceReportQuery {
        return new AttendanceReportQuery(
            schoolId: $schoolId,
            branchId: $branchId,
            allowedSectionIds: $allowedSectionIds,
            from: $from,
            to: $to,
            gradeLevelId: $request->filled('grade_level_id') ? $request->integer('grade_level_id') : null,
            sectionId: $request->filled('section_id') ? $request->integer('section_id') : null,
            source: $request->filled('source') ? (string) $request->input('source') : null,
            deviceId: $request->filled('device_id') ? $request->integer('device_id') : null,
        );
    }

    /**
     * @return array{search: string|null, flag: string|null, sort: string|null, dir: string|null}
     */
    private function studentOptions(Request $request): array
    {
        $request->validate([
            'flag' => ['nullable', Rule::in(['chronic', 'perfect', 'frequent_late'])],
        ]);

        return [
            'search' => $request->string('search')->trim()->value() ?: null,
            'flag' => $request->string('flag')->value() ?: null,
            'sort' => $request->string('sort')->value() ?: null,
            'dir' => $request->string('dir')->value() ?: null,
        ];
    }
}
