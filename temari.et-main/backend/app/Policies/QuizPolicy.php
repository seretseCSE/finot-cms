<?php

namespace App\Policies;

use App\Models\Quiz;
use App\Models\User;

/**
 * Two lanes (ADR-016): platform quizzes (mocks / national exam prep) are
 * Temari.et territory (`exam_prep.manage`); class quizzes follow the same
 * split as continuous assessments — supervisory (`lms.manage`, unrestricted
 * in scope) or the owning teacher (`lms.manage_own`). Judged in the quiz's
 * own school/branch, never the actor's context.
 *
 * Two different notions of "own" are deliberately kept apart:
 *  - `update`/`delete` (editing the quiz itself — title, paper, settings, or
 *    removing it) require true AUTHORSHIP (`created_by`). A teacher who
 *    merely teaches a targeted section didn't write this paper and
 *    shouldn't be able to reshape or delete it.
 *  - `manage` (publish/close/grade/invalidate/monitor attempts/sync to the
 *    gradebook — day-to-day running of the exam for a class) stays keyed to
 *    `isOwnedBy()` (current teaching assignment), since any teacher of the
 *    targeted section legitimately runs the exam for their own students.
 */
class QuizPolicy
{
    public function view(User $user, Quiz $quiz): bool
    {
        if ($quiz->is_platform) {
            return $user->hasPlatformPermission('exam_prep.manage');
        }

        if ($user->hasPermissionForScope('lms.view', $quiz->school_id, $quiz->branch_id)) {
            return true;
        }

        return $user->hasPermissionForScope('lms.manage_own', $quiz->school_id, $quiz->branch_id)
            && $quiz->isOwnedBy($user);
    }

    public function update(User $user, Quiz $quiz): bool
    {
        if ($quiz->is_platform) {
            return $user->hasPlatformPermission('exam_prep.manage');
        }

        if ($user->hasPermissionForScope('lms.manage', $quiz->school_id, $quiz->branch_id)) {
            return true;
        }

        return $user->hasPermissionForScope('lms.manage_own', $quiz->school_id, $quiz->branch_id)
            && (int) $quiz->created_by === (int) $user->id;
    }

    public function delete(User $user, Quiz $quiz): bool
    {
        return $this->update($user, $quiz);
    }

    /** Operational actions (publish/close/grade/invalidate/sync/monitor). */
    public function manage(User $user, Quiz $quiz): bool
    {
        if ($quiz->is_platform) {
            return $user->hasPlatformPermission('exam_prep.manage');
        }

        if ($user->hasPermissionForScope('lms.manage', $quiz->school_id, $quiz->branch_id)) {
            return true;
        }

        return $user->hasPermissionForScope('lms.manage_own', $quiz->school_id, $quiz->branch_id)
            && $quiz->isOwnedBy($user);
    }
}
