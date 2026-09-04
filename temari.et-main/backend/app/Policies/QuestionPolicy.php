<?php

namespace App\Policies;

use App\Models\Question;
use App\Models\User;

/**
 * A question's own authoring authority (ADR-016) — separate from the bank's
 * `view`/create ability. Judged in the bank's scope, but the ownership check
 * is the QUESTION's creator, not the bank's: a shared bank a teacher may add
 * to doesn't give them rights over a colleague's questions inside it.
 */
class QuestionPolicy
{
    public function update(User $user, Question $question): bool
    {
        $bank = $question->loadMissing('bank')->bank;

        if ($bank->isPlatform()) {
            return $user->hasPlatformPermission('exam_prep.manage');
        }

        if ($user->hasPermissionForScope('lms.manage', $bank->school_id, $bank->branch_id)) {
            return true;
        }

        return $user->hasPermissionForScope('lms.manage_own', $bank->school_id, $bank->branch_id)
            && (int) $question->created_by === (int) $user->id;
    }

    public function delete(User $user, Question $question): bool
    {
        return $this->update($user, $question);
    }
}
