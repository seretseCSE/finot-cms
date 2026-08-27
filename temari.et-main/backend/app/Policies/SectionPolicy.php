<?php

namespace App\Policies;

use App\Models\Section;
use App\Models\User;

/**
 * Two read lanes (ADR-010): `sections.view` — supervisory, any section in
 * scope; `sections.view_own` — teachers, ONLY sections that are actually
 * theirs (homeroom or an active teaching assignment). Writes stay
 * supervisory-only.
 */
class SectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasContextPermission('sections.view')
            || $user->hasContextPermission('sections.view_own');
    }

    public function view(User $user, Section $section): bool
    {
        if ($user->hasPermissionForScope('sections.view', $section->school_id, $section->branch_id)
            && $user->operatesInBranch($section->branch)) {
            return true;
        }

        return $user->hasPermissionForScope('sections.view_own', $section->school_id, $section->branch_id)
            && $section->isTaughtOrHomeroomedBy($user);
    }

    public function update(User $user, Section $section): bool
    {
        return $user->hasPermissionForScope('sections.update', $section->school_id, $section->branch_id)
            && $user->operatesInBranch($section->branch);
    }

    public function delete(User $user, Section $section): bool
    {
        return $user->hasPermissionForScope('sections.delete', $section->school_id, $section->branch_id)
            && $user->operatesInBranch($section->branch);
    }
}
