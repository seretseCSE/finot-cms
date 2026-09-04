<?php

namespace App\Actions;

use App\Models\Employee;
use App\Models\Membership;
use App\Models\User;
use App\Support\ActivityLogger;

/**
 * Activates or deactivates a single membership — i.e. a user's access within one
 * branch/school. This is scope-level access only and never touches the user's
 * global account status. Used by school-scope admins (their schools) and
 * directors (their own branch).
 */
class SetMembershipStatusAction
{
    public function execute(Membership $membership, bool $isActive, User $actor): Membership
    {
        $membership->forceFill(['is_active' => $isActive])->save();

        if ($membership->branch_id !== null) {
            Employee::syncBranchAccess($membership->user_id, $membership->branch_id, $isActive);
        }

        ActivityLogger::log(
            $actor,
            $isActive ? 'membership.activated' : 'membership.deactivated',
            $membership,
            ['user_id' => $membership->user_id, 'role' => $membership->role->value],
            $membership->school_id,
            $membership->branch_id,
        );

        return $membership;
    }
}
