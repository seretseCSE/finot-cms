<?php

namespace App\Policies;

use App\Models\FeeStructure;
use App\Models\User;

class FeeStructurePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasContextPermission('fees.view');
    }

    public function view(User $user, FeeStructure $feeStructure): bool
    {
        return $user->hasPermissionForScope('fees.view', $feeStructure->school_id, $feeStructure->branch_id)
            && $user->operatesInBranch($feeStructure->branch);
    }

    public function update(User $user, FeeStructure $feeStructure): bool
    {
        return $user->hasPermissionForScope('fees.manage', $feeStructure->school_id, $feeStructure->branch_id)
            && $user->operatesInBranch($feeStructure->branch);
    }

    public function delete(User $user, FeeStructure $feeStructure): bool
    {
        return $user->hasPermissionForScope('fees.manage', $feeStructure->school_id, $feeStructure->branch_id)
            && $user->operatesInBranch($feeStructure->branch);
    }
}
