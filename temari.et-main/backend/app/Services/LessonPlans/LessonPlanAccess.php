<?php

namespace App\Services\LessonPlans;

use App\Models\AnnualLessonPlan;
use App\Models\EmployeePosition;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Row-level authorization for the lesson-plan module, shared by the annual
 * and weekly controllers (same shape as the marklist lane):
 *
 *  - OWNER  — `lesson_plans.manage_own` + the plan is pinned to the teacher's
 *    own employee file (a departed teacher keeps nothing);
 *  - REVIEWER — `lesson_plans.review`: director or principal, each holding
 *    the approve/decline authority independently. When the school flips
 *    `lesson_plan_department_review` on, an active department_head position
 *    at the plan's branch carries the same authority (never over one's own
 *    plans — a department head's own submissions still go to the director).
 *  - VIEWER — reviewers, `lesson_plans.view` supervisors, or the owner.
 */
final class LessonPlanAccess
{
    public static function isOwner(User $user, AnnualLessonPlan $plan): bool
    {
        return $user->hasPermissionForScope('lesson_plans.manage_own', $plan->school_id, $plan->branch_id)
            && $plan->isOwnedBy($user);
    }

    public static function isReviewer(User $user, AnnualLessonPlan $plan): bool
    {
        if ($user->hasPermissionForScope('lesson_plans.review', $plan->school_id, $plan->branch_id)) {
            return true;
        }

        return self::departmentReviewEnabled($plan->school_id)
            && ! $plan->isOwnedBy($user)
            && self::isDepartmentHead($user, $plan->branch_id);
    }

    /**
     * List-level check for the review inbox / registers: does the user get
     * reviewer visibility in the CONTEXT branch via the department-head lane
     * (they may still lack it on their own plans — row checks re-verify)?
     */
    public static function isContextReviewer(User $user, ?object $branch): bool
    {
        if ($branch === null) {
            return false;
        }

        return self::departmentReviewEnabled((int) $branch->school_id)
            && self::isDepartmentHead($user, (int) $branch->id);
    }

    /** Whether the school routes lesson-plan review through department heads too. */
    public static function departmentReviewEnabled(int $schoolId): bool
    {
        return (bool) Cache::remember(
            "school:{$schoolId}:lesson_plan_department_review",
            300,
            fn (): bool => (bool) (School::query()->whereKey($schoolId)->first(['id', 'settings'])
                ?->lessonPlanDepartmentReviewEnabled() ?? false),
        );
    }

    private static function isDepartmentHead(User $user, int $branchId): bool
    {
        return EmployeePosition::query()
            ->active()
            ->where('job_title', 'department_head')
            ->whereHas('employee', fn ($q) => $q
                ->where('user_id', $user->id)
                ->where('branch_id', $branchId))
            ->exists();
    }

    public static function assertOwner(User $user, AnnualLessonPlan $plan): void
    {
        abort_unless(self::isOwner($user, $plan), 403);
    }

    public static function assertReviewer(User $user, AnnualLessonPlan $plan): void
    {
        abort_unless(self::isReviewer($user, $plan), 403);
    }

    public static function assertViewer(User $user, AnnualLessonPlan $plan): void
    {
        abort_unless(
            self::isReviewer($user, $plan)
            || $user->hasPermissionForScope('lesson_plans.view', $plan->school_id, $plan->branch_id)
            || self::isOwner($user, $plan),
            403,
        );
    }
}
