<?php

namespace App\Policies;

use App\Models\Subject;
use App\Models\User;

class SubjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // all authenticated users can list subjects
    }

    public function create(User $user): bool
    {
        return $user->hasContextPermission('subjects.manage');
    }

    public function update(User $user, Subject $subject): bool
    {
        // Platform-seeded subjects (no school) are super-admin only.
        if ($subject->school_id === null) {
            return $user->isSuperAdmin();
        }

        // Subjects are school-level data (no branch). The permission must be
        // effective in the actor's ACTIVE context and that context must be the
        // subject's own school — so a role held only at another school never
        // reaches in.
        return $user->hasContextPermission('subjects.manage')
            && $user->activeSchoolId() === $subject->school_id;
    }

    public function delete(User $user, Subject $subject): bool
    {
        return $this->update($user, $subject);
    }
}
