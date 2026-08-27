<?php

namespace App\Policies;

use App\Models\CourseMaterial;
use App\Models\User;

/**
 * Materials (ADR-016): platform library rows are `exam_prep.manage`;
 * school rows are supervisory (`lms.manage`) or the posting teacher
 * (`lms.manage_own` + being the creator).
 */
class CourseMaterialPolicy
{
    public function view(User $user, CourseMaterial $material): bool
    {
        if ($material->school_id === null) {
            return $user->hasPlatformPermission('exam_prep.manage');
        }

        return $user->hasPermissionForScope('lms.view', $material->school_id, $material->branch_id)
            || ($user->hasPermissionForScope('lms.manage_own', $material->school_id, $material->branch_id)
                && (int) $material->created_by === (int) $user->id);
    }

    public function update(User $user, CourseMaterial $material): bool
    {
        if ($material->school_id === null) {
            return $user->hasPlatformPermission('exam_prep.manage');
        }

        if ($user->hasPermissionForScope('lms.manage', $material->school_id, $material->branch_id)) {
            return true;
        }

        return $user->hasPermissionForScope('lms.manage_own', $material->school_id, $material->branch_id)
            && (int) $material->created_by === (int) $user->id;
    }

    public function delete(User $user, CourseMaterial $material): bool
    {
        return $this->update($user, $material);
    }
}
