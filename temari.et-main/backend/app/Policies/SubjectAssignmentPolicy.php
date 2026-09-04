<?php

namespace App\Policies;

use App\Models\SubjectAssignment;
use App\Models\User;

class SubjectAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasContextPermission('timetable.view');
    }

    public function create(User $user): bool
    {
        return $user->hasContextPermission('timetable.manage');
    }

    public function update(User $user, SubjectAssignment $assignment): bool
    {
        return $this->canManage($user, $assignment);
    }

    public function delete(User $user, SubjectAssignment $assignment): bool
    {
        return $this->canManage($user, $assignment);
    }

    /**
     * Judged in the assignment's own denormalized school/branch scope, so a
     * role held only at another school never carries over.
     */
    private function canManage(User $user, SubjectAssignment $assignment): bool
    {
        return $user->hasPermissionForScope('timetable.manage', $assignment->school_id, $assignment->branch_id);
    }
}
