<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

/**
 * STAFF-lane access to students. A student is a global person (ADR-011); staff
 * authority flows through the student's scopes — never a single school_id
 * column. Two tiers (mirrors how top SIS platforms bound "legitimate
 * educational interest" to the enrollment):
 *
 *  - VIEW runs on adminScopes(): every branch the student ever touched. A
 *    former school keeps a read-only archive view of its own era.
 *  - MUTATION (update/delete/enroll/guardians, and everything gated on those:
 *    documents, photo, health data) runs on activeAdminScopes(): only the
 *    scopes holding live custody. The moment a transfer lands, the old school
 *    loses every write path and all forward visibility.
 *
 * Parent/self access is the RELATIONSHIP lane (/me endpoints) and deliberately
 * does not pass through this policy. Guard rail: PostTransferAccessTest.
 */
class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasContextPermission('students.view');
    }

    public function view(User $user, Student $student): bool
    {
        return $this->allowedIn($user, $student->adminScopes(), 'students.view');
    }

    public function update(User $user, Student $student): bool
    {
        return $this->allowedIn($user, $student->activeAdminScopes(), 'students.update');
    }

    public function delete(User $user, Student $student): bool
    {
        return $this->allowedIn($user, $student->activeAdminScopes(), 'students.delete');
    }

    public function enroll(User $user, Student $student): bool
    {
        // Enrolling requires live custody; receiving a student from another
        // school goes through the transfer-request lane, never this ability.
        // The action itself validates the target section/year belong together.
        return $this->allowedIn($user, $student->activeAdminScopes(), 'enrollments.create');
    }

    public function viewGuardians(User $user, Student $student): bool
    {
        // Guardian contact details are live family data, not archive material.
        return $this->allowedIn($user, $student->activeAdminScopes(), 'guardians.view');
    }

    public function manageGuardians(User $user, Student $student): bool
    {
        return $this->allowedIn($user, $student->activeAdminScopes(), 'guardians.manage');
    }

    /**
     * @param  list<array{0: ?int, 1: ?int}>  $scopes
     */
    private function allowedIn(User $user, array $scopes, string $permission): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        foreach ($scopes as [$schoolId, $branchId]) {
            if ($user->hasPermissionForScope($permission, $schoolId, $branchId)) {
                return true;
            }
        }

        // A student with no scopes at all (independent B2C learner) is
        // administered by platform staff only.
        return $scopes === [] && $user->hasPlatformPermission($permission);
    }
}
