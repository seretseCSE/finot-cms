<?php

namespace App\Policies;

use App\Models\StudentGuardian;
use App\Models\User;

class StudentGuardianPolicy
{
    public function update(User $user, StudentGuardian $guardian): bool
    {
        return $this->manages($user, $guardian);
    }

    public function delete(User $user, StudentGuardian $guardian): bool
    {
        return $this->manages($user, $guardian);
    }

    /**
     * Guardian links are live family data: administered only where the
     * STUDENT is in live custody (activeAdminScopes) — mirrors StudentPolicy.
     * Former schools keep zero authority over the family graph.
     */
    private function manages(User $user, StudentGuardian $guardian): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        foreach ($guardian->student->activeAdminScopes() as [$schoolId, $branchId]) {
            if ($user->hasPermissionForScope('guardians.manage', $schoolId, $branchId)) {
                return true;
            }
        }

        return false;
    }
}
