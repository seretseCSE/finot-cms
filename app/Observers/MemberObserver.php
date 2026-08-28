<?php

namespace App\Observers;

use App\Models\Member;
use App\Services\Identity\ProvisionStudentUser;

class MemberObserver
{
    public function saved(Member $member): void
    {
        app(ProvisionStudentUser::class)->sync($member);
    }

    /**
     * Handle the Member "force deleting" event.
     */
    public function forceDeleting(Member $member): void
    {
        $member->parentGuardians()->forceDelete();
        $member->children()->delete();
        $member->childrenNames()->delete();
        $member->educationHistory()->delete();
        $member->groupAssignments()->delete();
        $member->contributions()->delete();
        $member->studentEnrollments()->delete();
    }

    /**
     * Handle the Member "deleted" event (soft delete).
     */
    public function deleted(Member $member): void
    {
        if ($member->isForceDeleting()) {
            return;
        }

        $member->parentGuardians()->delete();
        $member->groupAssignments()->update(['effective_to' => now()]);
    }

    /**
     * Handle the Member "restored" event.
     */
    public function restored(Member $member): void
    {
        $member->parentGuardians()->withTrashed()->restore();
    }
}
