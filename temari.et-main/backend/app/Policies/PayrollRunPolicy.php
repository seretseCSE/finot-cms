<?php

namespace App\Policies;

use App\Models\PayrollRun;
use App\Models\User;

class PayrollRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasContextPermission('payroll.view');
    }

    public function view(User $user, PayrollRun $run): bool
    {
        return $user->hasPermissionForScope('payroll.view', $run->school_id, $run->branch_id)
            && $user->operatesInBranch($run->branch);
    }

    public function update(User $user, PayrollRun $run): bool
    {
        return $user->hasPermissionForScope('payroll.manage', $run->school_id, $run->branch_id)
            && $user->operatesInBranch($run->branch);
    }

    public function delete(User $user, PayrollRun $run): bool
    {
        return $this->update($user, $run);
    }

    /** Approving (freezing) and marking paid is a step above preparing. */
    public function approve(User $user, PayrollRun $run): bool
    {
        return $user->hasPermissionForScope('payroll.approve', $run->school_id, $run->branch_id)
            && $user->operatesInBranch($run->branch);
    }
}
