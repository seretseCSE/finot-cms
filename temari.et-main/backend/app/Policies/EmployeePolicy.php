<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasContextPermission('employees.view');
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->hasPermissionForScope('employees.view', $employee->school_id, $employee->branch_id)
            && $this->canTouch($user, $employee);
    }

    public function export(User $user): bool
    {
        return $user->hasContextPermission('employees.view');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->hasPermissionForScope('employees.update', $employee->school_id, $employee->branch_id)
            && $this->canTouch($user, $employee);
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->hasPermissionForScope('employees.delete', $employee->school_id, $employee->branch_id)
            && $this->canTouch($user, $employee);
    }

    /**
     * Branch staff are gated by the branch; school-level staff (branch_id null)
     * by school management.
     */
    private function canTouch(User $user, Employee $employee): bool
    {
        if ($employee->branch) {
            return $user->operatesInBranch($employee->branch);
        }

        return $user->isPlatformUser() || $user->managesSchool($employee->school_id);
    }
}
