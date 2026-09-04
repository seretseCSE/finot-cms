<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Membership;
use App\Models\User;

class MembershipPolicy
{
    /**
     * Whether the actor may administer this membership (activate/deactivate or
     * remove). Enforces: the manage_branch_access permission, that the membership
     * sits within the actor's scope, is not a platform membership, and that the
     * actor outranks the target user (Temari Admin > Principal > Director).
     *
     * NOTE: this is distinct from assign_branch — a director may manage access for
     * people already in their branch but may not assign users to branches.
     */
    public function manage(User $user, Membership $membership): bool
    {
        // Judge the permission in the SCOPE THIS MEMBERSHIP LIVES IN — never the
        // actor's global grant. A user who is only a director at this branch must
        // pass here even if they hold no such role elsewhere, and a user who is a
        // principal at some OTHER school must not have that authority leak in.
        $hasPermission = $user->hasPermissionForScope(
            'users.manage_branch_access',
            $membership->school_id !== null ? (int) $membership->school_id : null,
            $membership->branch_id !== null ? (int) $membership->branch_id : null,
        );

        if (! $hasPermission) {
            return false;
        }

        $target = $membership->user;
        if ($target === null || $target->id === $user->id) {
            return false;
        }

        $role = $membership->role instanceof Role
            ? $membership->role
            : Role::tryFrom((string) $membership->role);

        // Unknown or platform-scoped memberships are never managed here.
        if ($role === null || $role->isPlatform()) {
            return false;
        }

        if (! $this->membershipInScope($user, $membership)) {
            return false;
        }

        if ($user->isPlatformUser()) {
            return true;
        }

        // Hierarchy is judged against the TARGET USER's strongest standing within
        // this membership's school/branch — not the individual membership's role.
        // So a director cannot touch ANY membership of someone who is a principal
        // of this school (even that person's incidental teacher/parent membership),
        // because the person outranks them here. Both levels are scoped to this
        // school/branch, so a role the target holds at an unrelated school never
        // protects them here and the actor's power never leaks across tenants.
        $schoolId = $membership->school_id !== null ? (int) $membership->school_id : null;
        $branchId = $membership->branch_id !== null ? (int) $membership->branch_id : null;

        $actorLevel = $user->authorityLevelFor($schoolId, $branchId);
        // Rank the target including a just-deactivated membership so reactivation is
        // not a one-way trip, and so a deactivated principal is still protected.
        // platformOutranks:false — a target's incidental platform hat must not shield
        // their in-scope school role from the admin who manages it.
        $targetLevel = $target->authorityLevelFor($schoolId, $branchId, includeInactive: true, platformOutranks: false);

        // The actor must STRICTLY outrank the target in this scope (lower level =
        // more authority). Peers (e.g. director↔director, principal↔principal) and
        // superiors are off-limits; only platform staff manage those.
        return $actorLevel !== null && $targetLevel !== null && $actorLevel < $targetLevel;
    }

    /**
     * Whether the actor may assign users to branches in their ACTIVE context.
     * Judged per-context (not the global grant) so a user who is a principal at
     * one school does not gain assignment power while acting as a director at
     * another. The controller further scopes the exact target branch.
     */
    public function assign(User $user): bool
    {
        return $user->hasContextPermission('users.assign_branch');
    }

    private function membershipInScope(User $user, Membership $membership): bool
    {
        if ($user->isPlatformUser()) {
            return true;
        }

        // Scope is judged in the actor's ACTIVE context, not their global authority:
        // a principal at School B may not manage School B memberships while they are
        // operating as a director at School A.
        if ($membership->school_id !== null
            && in_array((int) $membership->school_id, $user->managedSchoolIdsForContext(), true)) {
            return true;
        }

        return $membership->branch_id !== null
            && in_array((int) $membership->branch_id, $user->accessibleBranchIdsForContext(), true);
    }
}
