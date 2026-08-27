<?php

namespace App\Actions;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Membership;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;

/**
 * Assigns a user to a branch with a branch-scoped role (used by school-scope
 * admins for any branch in their school). Creates or restores the membership —
 * the membership row IS the grant (ADR-010); no Spatie role is ever attached to
 * the user. Never grants platform/global or relationship-derived roles.
 */
class AssignMembershipAction
{
    public function execute(User $user, Branch $branch, Role $role, User $actor): Membership
    {
        if (! $role->isBranch()) {
            throw new \InvalidArgumentException("Role [{$role->value}] cannot be granted as a branch membership.");
        }

        return DB::transaction(function () use ($user, $branch, $role, $actor): Membership {
            $membership = Membership::withTrashed()->firstOrNew([
                'user_id' => $user->id,
                'school_id' => $branch->school_id,
                'branch_id' => $branch->id,
                'role' => $role->value,
            ]);

            if ($membership->trashed()) {
                $membership->restore();
            }

            $membership->fill([
                'scope' => $role->scope()->value,
                'is_active' => true,
                'joined_at' => $membership->joined_at ?? now(),
            ])->save();

            Employee::syncAssignment($user->id, $branch->school_id, $branch->id);

            ActivityLogger::log(
                $actor,
                'membership.assigned',
                $membership,
                ['user_id' => $user->id, 'role' => $role->value],
                $branch->school_id,
                $branch->id,
            );

            return $membership;
        });
    }
}
