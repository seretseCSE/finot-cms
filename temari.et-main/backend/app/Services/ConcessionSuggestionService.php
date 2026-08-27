<?php

namespace App\Services;

use App\Enums\ConcessionCategory;
use App\Enums\ConcessionStatus;
use App\Enums\DiscountType;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\FeeConcession;
use App\Models\ParentProfile;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentGuardian;
use App\Services\Notify\Notifier;

/**
 * Evaluates the school's standing concession POLICY (sibling / employee-child
 * discounts, `schools.settings`) for one student and creates PENDING
 * suggestions — never live discounts. Sibling and staff detection can be wrong
 * (shared guardians, ended contracts), so a finance officer approves each row
 * in the review queue before it touches any bill.
 *
 * Runs after an enrollment is created and after a guardian is linked —
 * idempotent per (student, category, year), and silent when the policy is off.
 */
class ConcessionSuggestionService
{
    /**
     * Evaluate policy for a student's enrollment and file any suggestions.
     * The percentages are BRANCH-effective (branch override, else the school
     * default) — sibling counting stays school-wide, since siblings may be
     * enrolled at different branches.
     */
    public function evaluate(Student $student, StudentEnrollment $enrollment): void
    {
        $branch = Branch::with('school')->find($enrollment->branch_id);

        if ($branch === null) {
            return;
        }

        $parentIds = StudentGuardian::query()
            ->where('student_id', $student->id)
            ->where('is_active', true)
            ->pluck('parent_id');

        if ($parentIds->isEmpty()) {
            return;
        }

        $siblingPercent = $branch->effectiveSiblingDiscountPercent();

        if ($siblingPercent > 0 && $this->enrolledChildrenOf($parentIds->all(), $branch->school_id) >= $branch->effectiveSiblingMinChildren()) {
            $this->suggest(
                $enrollment,
                ConcessionCategory::Sibling,
                $siblingPercent,
                'auto_sibling',
                sprintf('School policy: %d+ enrolled children', $branch->effectiveSiblingMinChildren()),
            );
        }

        $staffPercent = $branch->effectiveStaffChildDiscountPercent();

        if ($staffPercent > 0 && $this->hasStaffGuardian($parentIds->all(), $branch->school_id)) {
            $this->suggest(
                $enrollment,
                ConcessionCategory::StaffChild,
                $staffPercent,
                'auto_staff',
                'School policy: guardian is an employee',
            );
        }
    }

    /**
     * Distinct children these guardians have with a live (pending/active)
     * enrollment at the school — the just-created one included.
     *
     * @param  list<int>  $parentIds
     */
    private function enrolledChildrenOf(array $parentIds, int $schoolId): int
    {
        return StudentGuardian::query()
            ->whereIn('parent_id', $parentIds)
            ->where('is_active', true)
            ->whereHas('student.enrollments', fn ($e) => $e->where('school_id', $schoolId)->live())
            ->distinct()
            ->count('student_id');
    }

    /**
     * @param  list<int>  $parentIds
     */
    private function hasStaffGuardian(array $parentIds, int $schoolId): bool
    {
        $userIds = ParentProfile::query()
            ->whereIn('id', $parentIds)
            ->whereNotNull('user_id')
            ->pluck('user_id');

        return $userIds->isNotEmpty() && Employee::query()
            ->whereIn('user_id', $userIds)
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * File one pending, year-scoped suggestion. Skips when the student already
     * has a row for this category and year in ANY state except revoked — a
     * rejection must not be re-nagged, an approval not duplicated.
     */
    private function suggest(
        StudentEnrollment $enrollment,
        ConcessionCategory $category,
        float $percent,
        string $source,
        string $reason,
    ): void {
        $exists = FeeConcession::query()
            ->where('student_id', $enrollment->student_id)
            ->where('category', $category->value)
            ->whereIn('status', [
                ConcessionStatus::Pending->value,
                ConcessionStatus::Active->value,
                ConcessionStatus::Rejected->value,
            ])
            ->where(fn ($q) => $q->whereNull('academic_year_id')->orWhere('academic_year_id', $enrollment->academic_year_id))
            ->exists();

        if ($exists) {
            return;
        }

        FeeConcession::create([
            'school_id' => $enrollment->school_id,
            'branch_id' => $enrollment->branch_id,
            'student_id' => $enrollment->student_id,
            'category' => $category->value,
            'discount_type' => DiscountType::Percentage->value,
            'discount_value' => $percent,
            'fee_types' => null,
            'academic_year_id' => $enrollment->academic_year_id,
            'status' => ConcessionStatus::Pending->value,
            'source' => $source,
            'reason' => $reason,
        ]);

        // No silent discounts: finance hears about every filed suggestion.
        app(Notifier::class)->toStaff(
            $enrollment->school_id,
            $enrollment->branch_id,
            'fees.manage',
            'finance.concession_suggested',
            ['student' => $enrollment->student?->full_name ?? ''],
            ['link' => '/concessions?status=pending'],
        );
    }
}
