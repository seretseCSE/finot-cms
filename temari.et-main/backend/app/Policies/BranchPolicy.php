<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasContextPermission('branches.view');
    }

    public function view(User $user, Branch $branch): bool
    {
        return $user->isPlatformUser() || $user->canAccessBranch($branch);
    }

    public function export(User $user): bool
    {
        return $user->hasContextPermission('branches.view');
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->hasContextPermission('branches.update')
            && ($user->isPlatformUser() || $user->managesSchool($branch->school_id));
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $user->hasContextPermission('branches.delete')
            && ($user->isPlatformUser() || $user->managesSchool($branch->school_id));
    }

    /**
     * Whether the user may replace the branch's director.
     */
    public function manageDirector(User $user, Branch $branch): bool
    {
        return $user->hasContextPermission('branches.update')
            && ($user->isPlatformUser() || $user->managesSchool($branch->school_id));
    }
}
