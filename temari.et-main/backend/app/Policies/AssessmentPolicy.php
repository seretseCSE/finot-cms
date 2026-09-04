<?php

namespace App\Policies;

use App\Models\Assessment;
use App\Models\SubjectAssignment;
use App\Models\User;

/**
 * ContinuousAssessment authority has two lanes (ADR-011):
 *  - `grades.manage`     — supervisory (director/principal/school_admin): any
 *                          continuous assessment in their scope;
 *  - `grades.manage_own` — teachers: ONLY assignments that are actually theirs.
 * Scope is always the assignment's own denormalized school/branch — never the
 * actor's global role union.
 */
class AssessmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasContextPermission('grades.view');
    }

    /**
     * Row-scoped READ over one assignment's assessments/marks. Judged in the
     * assignment's own school/branch (context permissions alone would let a
     * grades.view holder at another school read marks by id). Teachers read
     * through ownership: grades.manage_own + the assignment being theirs.
     */
    public function viewAssignment(User $user, SubjectAssignment $assignment): bool
    {
        if ($user->hasPermissionForScope('grades.view', $assignment->school_id, $assignment->branch_id)) {
            return true;
        }

        return $user->hasPermissionForScope('grades.manage_own', $assignment->school_id, $assignment->branch_id)
            && $assignment->isOwnedBy($user);
    }

    public function update(User $user, Assessment $assessment): bool
    {
        return $this->canManage($user, $assessment->subjectAssignment);
    }

    public function delete(User $user, Assessment $assessment): bool
    {
        return $this->canManage($user, $assessment->subjectAssignment);
    }

    /**
     * Shared by AssessmentController@store (via SubjectAssignment) and the
     * update/delete abilities above.
     */
    public function canManage(User $user, SubjectAssignment $assignment): bool
    {
        if ($user->hasPermissionForScope('grades.manage', $assignment->school_id, $assignment->branch_id)) {
            return true;
        }

        return $user->hasPermissionForScope('grades.manage_own', $assignment->school_id, $assignment->branch_id)
            && $assignment->isOwnedBy($user);
    }

    /**
     * Mark ENTRY (score cells) is stricter than structure: while a draft
     * belongs to a teacher with an account, only that teacher types — the
     * submit signature must mean "I entered these marks". A supervisor
     * (grades.manage) writes scores only when
     *  - the assignment has no owning teacher account (vacancy), or
     *  - they declared on-behalf entry (marklists.assisted_by) — the loud
     *    lane that notifies the teacher and badges every surface.
     */
    public function enterMarks(User $user, Assessment $assessment): bool
    {
        $assignment = $assessment->subjectAssignment;

        if ($assignment->isOwnedBy($user)) {
            return $user->hasPermissionForScope('grades.manage_own', $assignment->school_id, $assignment->branch_id)
                || $user->hasPermissionForScope('grades.manage', $assignment->school_id, $assignment->branch_id);
        }

        if (! $user->hasPermissionForScope('grades.manage', $assignment->school_id, $assignment->branch_id)) {
            return false;
        }

        $assignment->loadMissing(['employee', 'marklist']);

        $vacant = $assignment->employee?->user_id === null;

        return $vacant || (int) ($assignment->marklist?->assisted_by ?? 0) === (int) $user->id;
    }
}
