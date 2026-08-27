<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\School;
use App\Services\DashboardService;
use App\Services\OrgStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The staff dashboard: ONE aggregated request per landing, assembled from
 * permission-gated blocks so every role sees exactly its own command center —
 * principals a school pulse, directors a branch pulse, finance officers the
 * money desk, registrars the enrollment pipeline, teachers "my day".
 *
 * Scoping mirrors the report controllers: platform staff see the whole
 * platform (narrowable per school via the context switcher), school managers
 * their school (optionally narrowed to a branch via ?branch_id), branch roles
 * their branch. hasContextPermission() validates the X-School-Id/X-Branch-Id
 * headers against real memberships, so blocks can never widen beyond the
 * caller's authority (ADR-010).
 */
class DashboardController extends Controller
{
    /** Queue item → the permission that lets the caller act on that pile. */
    private const QUEUE_PERMISSIONS = [
        'pending_enrollments' => 'enrollments.create',
        'payment_verifications' => 'payments.record',
        'expenses_pending' => 'finance.books.approve',
        'leave_pending' => 'leave.manage',
        'transfers_incoming' => 'transfers.manage',
        'marklists_submitted' => 'grades.approve',
        'concessions_pending' => 'fees.manage',
    ];

    public function show(Request $request, DashboardService $dashboard, OrgStatsService $orgStats): JsonResponse
    {
        $user = $request->user();

        // Relationship-only users (parents/students) live on /me — the staff
        // dashboard has nothing for them.
        abort_unless($user->memberships()->where('is_active', true)->exists(), 403);

        $branch = $this->activeBranchOrNull($request);
        $schoolScopeId = $this->activeSchoolScopeId($request);

        // Platform staff may narrow to a school; school staff are pinned to
        // theirs. In a school-wide workspace ?branch_id narrows one level.
        $schoolId = $branch?->school_id
            ?? $schoolScopeId
            ?? ($user->isPlatformUser() && $request->filled('school_id') ? $request->integer('school_id') : null);
        $branchId = $branch?->id ?? $this->branchFilterId($request, $branch);

        $can = fn (string $permission): bool => $user->hasContextPermission($permission);

        $data = [
            'context' => $dashboard->context($branchId),
        ];

        // Org vitals (students, gender split, per-grade picture) reuse the
        // same cached aggregates as the school/branch profile pages, so the
        // dashboard and the profiles always agree.
        if ($can('students.view')) {
            if ($branchId !== null && ($b = Branch::find($branchId)) !== null) {
                $data['org'] = $orgStats->forBranch($b);
            } elseif ($schoolId !== null && ($s = School::find($schoolId)) !== null) {
                $data['org'] = $orgStats->forSchool($s);
            }
        }

        if ($can('attendance.reports.view')) {
            $data['attendance'] = $dashboard->attendance($schoolId, $branchId);
        }

        if ($can('fees.reports.view')) {
            $data['finance'] = $dashboard->finance($schoolId, $branchId);
        }

        if ($can('employee_attendance.view') || $can('hr.reports.view')) {
            $data['staff_today'] = $dashboard->staffToday($schoolId, $branchId);
        }

        $queueKeys = array_keys(array_filter(
            self::QUEUE_PERMISSIONS,
            fn (string $permission): bool => $can($permission),
        ));
        if ($queueKeys !== []) {
            $data['queue'] = $dashboard->queue($queueKeys, $schoolId, $branchId);
        }

        // Branch comparison: the school-wide (or platform, school-narrowed)
        // workspace with more than the current branch in play.
        if ($branchId === null && $schoolId !== null && $can('branches.view')) {
            $school = School::find($schoolId);
            if ($school !== null) {
                $data['branches'] = $dashboard->branchComparison($school);
            }
        }

        if ($user->isPlatformUser() && $schoolId === null) {
            $data['platform'] = $dashboard->platform();
        }

        // The personal teaching block rides along whenever the caller
        // actually teaches or homerooms in the active branch.
        if ($branchId !== null) {
            $data['teacher'] = $dashboard->teacher($user, $branchId);
        }

        return response()->json(['data' => $data]);
    }
}
