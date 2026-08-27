<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

/**
 * Courses follow the material rules (ADR-016): platform courses belong to
 * exam_prep.manage; school/branch courses to lms.manage supervisors; class
 * courses to their owning teacher (lms.manage_own + ownership).
 */
class CoursePolicy
{
    public function view(User $user, Course $course): bool
    {
        if ($course->isPlatform()) {
            return $user->hasPlatformPermission('exam_prep.manage');
        }

        return $user->hasPermissionForScope('lms.view', $course->school_id, $course->branch_id)
            || ($user->hasPermissionForScope('lms.manage_own', $course->school_id, $course->branch_id)
                && ($course->subject_assignment_id === null || $course->isOwnedBy($user)));
    }

    public function update(User $user, Course $course): bool
    {
        if ($course->isPlatform()) {
            return $user->hasPlatformPermission('exam_prep.manage');
        }

        if ($user->hasPermissionForScope('lms.manage', $course->school_id, $course->branch_id)) {
            return true;
        }

        return $course->subject_assignment_id !== null
            && $user->hasPermissionForScope('lms.manage_own', $course->school_id, $course->branch_id)
            && $course->isOwnedBy($user);
    }

    public function delete(User $user, Course $course): bool
    {
        return $this->update($user, $course);
    }
}
