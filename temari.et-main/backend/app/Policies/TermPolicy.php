<?php

namespace App\Policies;

use App\Models\Term;
use App\Models\User;

class TermPolicy
{
    public function view(User $user, Term $term): bool
    {
        return $user->hasPermissionForScope('academic_years.view', $term->school_id, $term->branch_id)
            && $user->operatesInBranch($term->branch);
    }

    public function update(User $user, Term $term): bool
    {
        return $user->hasPermissionForScope('academic_years.update', $term->school_id, $term->branch_id)
            && $user->operatesInBranch($term->branch);
    }
}
