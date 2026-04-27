<?php

namespace App\Observers;

use App\Models\Member;

class MemberObserver
{
    /**
     * Handle the Member "force deleting" event.
     */
    public function forceDeleting(Member $member): void
    {
        // Clean up relationships that may not have FK coverage or need explicit handling
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

        // Soft-delete related parent guardians so they can be restored along with member
        $member->parentGuardians()->delete();
        $member->groupAssignments()->update(['effective_to' => now()]);
    }

    /**
     * Handle the Member "restored" event.
     */
    public function restored(Member $member): void
    {
        // Restore soft-deleted parent guardians
        $member->parentGuardians()->withTrashed()->restore();
    }
}
