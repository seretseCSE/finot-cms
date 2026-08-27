<?php

namespace App\Policies;

use App\Models\QuestionBank;
use App\Models\User;

/**
 * Question banks (ADR-016) live in three scopes: platform (national bank —
 * `exam_prep.manage`), school/branch (supervisory `lms.manage`), and
 * teacher-created branch banks (`lms.manage_own` + being the creator).
 * Teachers may READ any bank in their scope — quizzes draw from shared
 * school banks — but only shape their own.
 */
class QuestionBankPolicy
{
    public function view(User $user, QuestionBank $bank): bool
    {
        // Temari.et LMS staff read every bank (support + curation lane).
        if ($user->hasPlatformPermission('exam_prep.manage')) {
            return true;
        }

        if ($bank->isPlatform()) {
            return false;
        }

        return $user->hasPermissionForScope('lms.view', $bank->school_id, $bank->branch_id)
            || $user->hasPermissionForScope('lms.manage_own', $bank->school_id, $bank->branch_id);
    }

    public function update(User $user, QuestionBank $bank): bool
    {
        if ($bank->isPlatform()) {
            return $user->hasPlatformPermission('exam_prep.manage');
        }

        if ($user->hasPermissionForScope('lms.manage', $bank->school_id, $bank->branch_id)) {
            return true;
        }

        return $user->hasPermissionForScope('lms.manage_own', $bank->school_id, $bank->branch_id)
            && (int) $bank->created_by === (int) $user->id;
    }

    public function delete(User $user, QuestionBank $bank): bool
    {
        return $this->update($user, $bank);
    }
}
