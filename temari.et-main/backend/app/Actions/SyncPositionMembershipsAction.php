<?php

namespace App\Actions;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\Membership;
use App\Support\JobTitles;

/**
 * Keeps the authorization kernel in lockstep with HR: branch staff roles are
 * POSITION-DRIVEN. After any change to an employee's positions, the four
 * role-mapped job titles (director / teacher / registrar / finance_officer)
 * reconcile against `memberships` — an active position guarantees an active
 * membership; ending the last position holding a mapped role deactivates it.
 * Memberships for unmapped job titles or other scopes are never touched.
 */
class SyncPositionMembershipsAction
{
    public function execute(Employee $employee): void
    {
        if ($employee->user_id === null || $employee->branch_id === null) {
            return;
        }

        $impliedRoles = $employee->activePositions()
            ->pluck('job_title')
            ->map(fn (string $d): ?Role => JobTitles::roleFor($d))
            ->filter()
            ->unique()
            ->values();

        foreach (JobTitles::ROLE_MAP as $role) {
            $membership = Membership::withTrashed()
                ->where('user_id', $employee->user_id)
                ->where('branch_id', $employee->branch_id)
                ->where('role', $role->value)
                ->first();

            if ($impliedRoles->contains($role)) {
                if ($membership === null) {
                    Membership::create([
                        'user_id' => $employee->user_id,
                        'school_id' => $employee->school_id,
                        'branch_id' => $employee->branch_id,
                        'role' => $role->value,
                        'scope' => $role->scope()->value,
                        // A just-created model may not have the DB default hydrated.
                        'is_active' => $employee->is_active ?? true,
                        'joined_at' => now(),
                    ]);

                    continue;
                }

                if ($membership->trashed()) {
                    $membership->restore();
                }

                if (! $membership->is_active && ($employee->is_active ?? true)) {
                    $membership->forceFill(['is_active' => true])->save();
                }
            } elseif ($membership !== null && ! $membership->trashed() && $membership->is_active) {
                $membership->forceFill(['is_active' => false])->save();
            }
        }
    }
}
