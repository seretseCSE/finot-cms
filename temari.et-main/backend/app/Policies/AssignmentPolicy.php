<?php

namespace App\Policies;

use App\Models\Assignment;
use App\Models\User;

/**
 * Homework follows the continuous-assessment split (ADR-016): supervisory
 * (`lms.view` / `lms.manage`) or the owning teacher (`lms.manage_own` +
 * the subject assignment being theirs).
 */
class AssignmentPolicy
{
    public function view(User $user, Assignment $assignment): bool
    {
        if ($user->hasPermissionForScope('lms.view', $assignment->school_id, $assignment->branch_id)) {
            return true;
        }

        return $user->hasPermissionForScope('lms.manage_own', $assignment->school_id, $assignment->branch_id)
            && $assignment->isOwnedBy($user);
    }

    public function update(User $user, Assignment $assignment): bool
    {
        if ($user->hasPermissionForScope('lms.manage', $assignment->school_id, $assignment->branch_id)) {
            return true;
        }

        return $user->hasPermissionForScope('lms.manage_own', $assignment->school_id, $assignment->branch_id)
            && $assignment->isOwnedBy($user);
    }

    public function delete(User $user, Assignment $assignment): bool
    {
        return $this->update($user, $assignment);
    }
}
