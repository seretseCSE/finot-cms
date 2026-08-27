<?php

namespace App\Policies;

use App\Models\School;
use App\Models\User;

class SchoolPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasContextPermission('schools.view');
    }

    /**
     * Who may open a school profile: Temari.et platform staff, or a manager of
     * this specific school (principal / school_admin) as a read-only view.
     * Branch-only members (director, teacher, …) are deliberately excluded —
     * they operate inside their branch and never see the school entity, even
     * though they hold an active membership tied to it.
     */
    public function view(User $user, School $school): bool
    {
        return $user->isPlatformUser() || $user->managesSchool($school->id);
    }

    public function export(User $user): bool
    {
        return $user->hasContextPermission('schools.view');
    }

    public function create(User $user): bool
    {
        return $user->hasContextPermission('schools.create');
    }

    public function update(User $user, School $school): bool
    {
        return $user->hasContextPermission('schools.update')
            && ($user->isPlatformUser() || $user->managesSchool($school->id));
    }

    public function delete(User $user, School $school): bool
    {
        return $user->hasContextPermission('schools.delete');
    }

    /**
     * Whether the user may add a branch to this school.
     */
    public function createBranch(User $user, School $school): bool
    {
        return $user->hasContextPermission('branches.create')
            && ($user->isPlatformUser() || $user->managesSchool($school->id));
    }

    /**
     * Whether the user may replace the school's contacts (principal / IT admin).
     */
    public function manageContacts(User $user, School $school): bool
    {
        return $user->hasContextPermission('schools.update')
            && ($user->isPlatformUser() || $user->managesSchool($school->id));
    }

    /**
     * The school logo appears on OFFICIAL documents (transcripts, report
     * cards) — only Temari.et platform staff may set or remove it. School
     * managers request changes; they never self-serve, so a forged letterhead
     * can never originate inside a school.
     */
    public function manageLogo(User $user, School $school): bool
    {
        return $user->hasPlatformPermission('schools.update');
    }

    /**
     * Academic policy knobs (registration gate, promotion threshold) belong
     * to the school's own managers — unlike school CRUD, which is platform
     * territory. Judged against the school acted on, never a global role.
     */
    public function updateSettings(User $user, School $school): bool
    {
        return $user->isPlatformUser() || $user->managesSchool($school->id);
    }
}
