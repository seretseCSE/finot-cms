<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\AssignMembershipAction;
use App\Actions\SetMembershipStatusAction;
use App\Enums\Role;
use App\Http\Controllers\Concerns\HandlesBulkActions;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserListResource;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Membership;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MembershipController extends Controller
{
    use HandlesBulkActions;

    /**
     * Assign a user to a branch (school-scope admins only: any branch of their
     * school). Never grants platform/global roles or roles ranked at or above the
     * actor.
     */
    public function store(Request $request, User $user, AssignMembershipAction $action): JsonResponse
    {
        $this->authorize('assign', Membership::class);

        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'role' => ['required', Rule::in($this->branchRoleValues())],
        ]);

        $actor = $request->user();
        $branch = Branch::findOrFail($data['branch_id']);
        $role = Role::from($data['role']);

        $denial = $this->assignmentDenial($actor, $user, $branch, $role);
        abort_if($denial !== null, 403, match ($denial) {
            'branch_out_of_scope' => 'You cannot assign users to this branch.',
            'role_above_you' => 'You cannot grant a role at or above your own.',
            default => 'You cannot manage this user.',
        });

        $action->execute($user, $branch, $role, $actor);

        return (new UserListResource($user->load(['memberships.school', 'memberships.branch'])))
            ->additional(['message' => "{$user->name} assigned to {$branch->name}."])
            ->response();
    }

    public function updateStatus(Request $request, Membership $membership, SetMembershipStatusAction $action): JsonResponse
    {
        $this->authorize('manage', $membership);

        $data = $request->validate(['is_active' => ['required', 'boolean']]);

        $action->execute($membership, $data['is_active'], $request->user());

        $user = $membership->user;

        return (new UserListResource($user->load(['memberships.school', 'memberships.branch'])))
            ->additional(['message' => $data['is_active'] ? 'Access restored for this branch.' : 'Access removed for this branch.'])
            ->response();
    }

    public function destroy(Request $request, Membership $membership): JsonResponse
    {
        $this->authorize('manage', $membership);

        $user = $membership->user;

        if ($membership->branch_id !== null) {
            Employee::syncBranchAccess($membership->user_id, $membership->branch_id, false);
        }

        $membership->delete();

        ActivityLogger::log(
            $request->user(),
            'membership.removed',
            $membership,
            ['user_id' => $user?->id, 'role' => $membership->role->value],
            $membership->school_id,
            $membership->branch_id,
        );

        return (new UserListResource($user->load(['memberships.school', 'memberships.branch'])))
            ->additional(['message' => 'User removed from branch.'])
            ->response();
    }

    /**
     * Assign a hand-picked set of users to ONE branch with one or more branch
     * roles — the "the new campus opens on Monday" case. Same authority rules as
     * store(), applied per user: anyone out of the actor's reach is skipped and
     * reported instead of failing the whole batch.
     */
    public function bulkStore(Request $request, AssignMembershipAction $action): JsonResponse
    {
        $this->authorize('assign', Membership::class);

        $data = $request->validate([
            ...self::bulkIdRules(),
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::in($this->branchRoleValues())],
        ]);

        $actor = $request->user();
        $branch = Branch::findOrFail($data['branch_id']);
        $roles = array_map(Role::from(...), array_values(array_unique($data['roles'])));
        $assigned = 0;
        $skipped = [];

        foreach ($this->bulkTargets($data['ids'], $skipped) as $user) {
            // A user counts as assigned when at least one requested role lands;
            // a role the actor cannot grant is reported once per user, not per role.
            $granted = 0;
            $denial = null;

            foreach ($roles as $role) {
                $reason = $this->assignmentDenial($actor, $user, $branch, $role);

                if ($reason !== null) {
                    $denial ??= $reason;

                    continue;
                }

                $action->execute($user, $branch, $role, $actor);
                $granted++;
            }

            if ($granted > 0) {
                $assigned++;
            } else {
                $skipped[] = self::skip($user->id, $user->name, $denial ?? 'not_permitted');
            }
        }

        return response()->json([
            'message' => "{$assigned} user(s) assigned to {$branch->name}.",
            'meta' => ['assigned' => $assigned, 'requested' => count($data['ids']), 'skipped' => $skipped],
        ]);
    }

    /**
     * Turn branch access on or off for a hand-picked set of users in ONE branch —
     * the end-of-term / start-of-term sweep. Only memberships the actor may manage
     * (MembershipPolicy@manage) are flipped; a user with no membership in that
     * branch, or one the actor may not touch, is skipped and reported.
     */
    public function bulkStatus(Request $request, SetMembershipStatusAction $action): JsonResponse
    {
        $data = $request->validate([
            ...self::bulkIdRules(),
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'is_active' => ['required', 'boolean'],
        ]);

        $actor = $request->user();
        $branchId = (int) $data['branch_id'];
        $isActive = (bool) $data['is_active'];
        $updated = 0;
        $skipped = [];

        foreach ($this->bulkTargets($data['ids'], $skipped) as $user) {
            if ($this->isSelf($actor, $user)) {
                $skipped[] = self::skip($user->id, $user->name, 'self');

                continue;
            }

            // Multi-role staff hold one membership per role in a branch; access is
            // per branch, so every membership they hold there moves together.
            $memberships = Membership::where('user_id', $user->id)
                ->where('branch_id', $branchId)
                ->get();

            if ($memberships->isEmpty()) {
                $skipped[] = self::skip($user->id, $user->name, 'no_membership');

                continue;
            }

            $manageable = $memberships->filter(fn (Membership $m) => $actor->can('manage', $m));

            if ($manageable->isEmpty()) {
                $skipped[] = self::skip($user->id, $user->name, 'not_permitted');

                continue;
            }

            foreach ($manageable as $membership) {
                $action->execute($membership, $isActive, $actor);
            }

            $updated++;
        }

        return response()->json([
            'message' => $isActive
                ? "Branch access restored for {$updated} user(s)."
                : "Branch access removed for {$updated} user(s).",
            'meta' => ['updated' => $updated, 'requested' => count($data['ids']), 'skipped' => $skipped],
        ]);
    }

    /**
     * Why the actor may NOT give this user this role in this branch — a stable
     * machine key, or null when the assignment is allowed. Shared by the single
     * and bulk assign paths so they can never drift apart.
     */
    private function assignmentDenial(User $actor, User $target, Branch $branch, Role $role): ?string
    {
        // Actor must operate within the target branch's scope.
        $inScope = $actor->isPlatformUser()
            || $actor->managesSchool($branch->school_id)
            || in_array($branch->id, $actor->accessibleBranchIds(), true);

        if (! $inScope) {
            return 'branch_out_of_scope';
        }

        // Authority is judged WITHIN this branch's scope — never the actor's or
        // target's strongest role at some other school.
        $actorLevel = $actor->authorityLevelFor($branch->school_id, $branch->id);

        // Role being granted must rank strictly below the actor here.
        if (! $actor->isPlatformUser() && ! ($actorLevel !== null && $actorLevel < $role->hierarchyLevel())) {
            return 'role_above_you';
        }

        // Cannot touch a user who already holds a role in THIS scope ranking at or
        // above the actor.
        $targetLevel = $target->authorityLevelFor($branch->school_id, $branch->id);

        if (! $actor->isPlatformUser()
            && $target->id !== $actor->id
            && $targetLevel !== null
            && $actorLevel !== null
            && $targetLevel <= $actorLevel) {
            return 'user_outranks_you';
        }

        return null;
    }

    /**
     * Roles that may be assigned at branch level (never platform/school roles).
     *
     * @return list<string>
     */
    private function branchRoleValues(): array
    {
        return array_map(
            fn (Role $r) => $r->value,
            array_filter(Role::cases(), fn (Role $r) => $r->isBranch()),
        );
    }
}
