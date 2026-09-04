<?php

namespace App\Policies;

use App\Models\AcademicYear;
use App\Models\User;

class AcademicYearPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasContextPermission('academic_years.view');
    }

    public function view(User $user, AcademicYear $year): bool
    {
        return $user->hasPermissionForScope('academic_years.view', $year->school_id, $year->branch_id)
            && $user->operatesInBranch($year->branch);
    }

    public function update(User $user, AcademicYear $year): bool
    {
        return $user->hasPermissionForScope('academic_years.update', $year->school_id, $year->branch_id)
            && $user->operatesInBranch($year->branch);
    }

    public function delete(User $user, AcademicYear $year): bool
    {
        return $user->hasPermissionForScope('academic_years.delete', $year->school_id, $year->branch_id)
            && $user->operatesInBranch($year->branch);
    }
}
