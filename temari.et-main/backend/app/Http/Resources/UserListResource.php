<?php

namespace App\Http\Resources;

use App\Enums\Role;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Resource for listing / exporting users. Expects `roles` and
 * `memberships.school` / `memberships.branch` to be eager-loaded to avoid N+1.
 *
 * @mixin User
 */
class UserListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $actor = $request->user();

        /** @var Collection<int, Membership> $allMemberships */
        $allMemberships = $this->relationLoaded('memberships') ? $this->memberships : collect();

        // Privacy: a scoped admin (principal / director) must only ever see the
        // parts of a user's identity that fall within their own school/branch
        // scope — never that user's roles or affiliations at other schools.
        // Platform staff (super admin / support) see the full picture.
        $scoped = $actor !== null && ! $actor->isPlatformUser();
        $memberships = $scoped
            ? $this->visibleMemberships($allMemberships, $actor)
            : $allMemberships;

        $schools = $memberships
            ->filter(fn (Membership $m) => $m->school_id !== null && $m->school !== null)
            ->map(fn (Membership $m) => ['id' => $m->school_id, 'name' => $m->school->name])
            ->unique('id')
            ->values();

        $branches = $memberships
            ->filter(fn (Membership $m) => $m->branch_id !== null && $m->branch !== null)
            ->map(fn (Membership $m) => [
                'id' => $m->branch_id,
                'name' => $m->branch->name,
                'school_id' => $m->school_id,
                'membership_id' => $m->id,
                'membership_active' => $m->is_active,
                'role' => $this->roleValue($m),
                // Whether the ACTOR may administer this membership right now (scope +
                // hierarchy + permission, in their active context). Drives whether the
                // UI offers activate/deactivate/remove — a director sees a peer
                // director or another branch as read-only, never actionable.
                'can_manage' => $this->canManage($actor, $m),
            ])
            ->values();

        $roleNames = collect($this->roleNames());

        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'preferred_language' => $this->preferred_language,
            'avatar_url' => $this->avatarUrl(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'roles' => $roleNames,
            'type' => $schools->isNotEmpty() ? 'affiliated' : 'independent',
            'schools' => $schools,
            'branches' => $branches,
            // Whether ANY visible membership is active — the scoped status
            // column reads this, since school-level memberships (principal /
            // school_admin) have no branch row to check.
            'has_active_membership' => $memberships->contains(fn (Membership $m): bool => $m->is_active),
            // Structured School → Branch → Role tree so the UI can show which role
            // a user holds where, instead of three disconnected flat columns.
            'affiliations' => $this->affiliations($memberships),
            // Platform-staff status and loose global roles aren't anchored to any
            // school, so they carry no meaning for a scoped admin — and exposing
            // them would leak a user's identity beyond the viewer's scope.
            'platform_roles' => $scoped ? collect() : $this->platformRoles($roleNames),
            'other_roles' => $scoped ? collect() : $this->otherRoles($roleNames, $memberships),
            // Relationship lane (ADR-012): student/parent access is never a
            // membership — it derives from enrollment/guardianship links, shown
            // here so the directory explains WHY these accounts exist. Scoped
            // admins only see the relationship tying the person to THEIR scope.
            'relationships' => $this->relationshipAccess($actor, $scoped),
            'last_login_at' => $this->last_login_at,
            'created_at' => $this->created_at,
            'deleted_at' => $this->deleted_at,
        ];
    }

    /**
     * The user's student/parent hats, derived from enrollment and guardianship
     * links (never memberships — ADR-012). For scoped admins, filtered to the
     * enrollments/links inside their own schools/branches so nothing leaks
     * about the person's life at other schools.
     *
     * @return array{student: array<string, mixed>|null, parent: array<string, mixed>|null}
     */
    private function relationshipAccess(?User $actor, bool $scoped): array
    {
        $schoolIds = $scoped && $actor !== null ? $actor->managedSchoolIdsForContext() : [];
        $branchIds = $scoped && $actor !== null ? $actor->accessibleBranchIdsForContext() : [];

        $inScope = function (Collection $enrollments) use ($scoped, $schoolIds, $branchIds): Collection {
            if (! $scoped) {
                return $enrollments;
            }

            return $enrollments->filter(fn ($e): bool => in_array((int) $e->school_id, $schoolIds, true)
                || in_array((int) $e->branch_id, $branchIds, true));
        };

        $student = null;
        $profile = $this->relationLoaded('studentProfile') ? $this->studentProfile : null;
        if ($profile !== null && $profile->relationLoaded('enrollments')) {
            $enrollments = $inScope($profile->enrollments);

            // Scoped admins only learn the person is a student at all when an
            // in-scope enrollment justifies it.
            if (! $scoped || $enrollments->isNotEmpty()) {
                $enrollment = $enrollments->first();
                $student = [
                    'student_id' => $profile->id,
                    'school_name' => $enrollment?->branch?->school?->name,
                    'branch_name' => $enrollment?->branch?->name,
                    'grade' => $enrollment?->gradeLevel?->name,
                ];
            }
        }

        $parent = null;
        $profile = $this->relationLoaded('parentProfile') ? $this->parentProfile : null;
        if ($profile !== null && $profile->relationLoaded('guardianships')) {
            $links = $profile->guardianships;
            if ($scoped) {
                $links = $links->filter(fn ($g): bool => $g->student !== null
                    && $g->student->relationLoaded('enrollments')
                    && $inScope($g->student->enrollments)->isNotEmpty());
            }

            if (! $scoped || $links->isNotEmpty()) {
                $parent = [
                    'children_count' => $links->count(),
                    'children' => $links
                        ->map(fn ($g): string => trim(($g->student?->first_name ?? '').' '.($g->student?->father_name ?? '')))
                        ->filter()
                        ->values(),
                    'schools' => $links
                        ->flatMap(fn ($g): Collection => $g->student !== null && $g->student->relationLoaded('enrollments')
                            ? $inScope($g->student->enrollments)
                            : collect())
                        ->map(fn ($e): ?string => $e->branch?->school?->name)
                        ->filter()
                        ->unique()
                        ->values(),
                ];
            }
        }

        return ['student' => $student, 'parent' => $parent];
    }

    /**
     * Group school/branch-anchored memberships into a School → Branch → Role tree.
     *
     * @param  Collection<int, Membership>  $memberships
     * @return Collection<int, array<string, mixed>>
     */
    private function affiliations(Collection $memberships): Collection
    {
        return $memberships
            ->filter(fn (Membership $m) => $m->school_id !== null && $m->school !== null)
            ->groupBy('school_id')
            ->map(function (Collection $group) {
                /** @var Membership $first */
                $first = $group->first();

                // School-scoped memberships (principal / school_admin) have no branch.
                $schoolRoles = $group
                    ->filter(fn (Membership $m) => $m->branch_id === null)
                    ->map(fn (Membership $m) => $this->roleValue($m))
                    ->unique()
                    ->values();

                $branches = $group
                    ->filter(fn (Membership $m) => $m->branch_id !== null && $m->branch !== null)
                    ->groupBy('branch_id')
                    ->map(fn (Collection $bg) => [
                        'id' => $bg->first()->branch_id,
                        'name' => $bg->first()->branch->name,
                        'active' => $bg->contains(fn (Membership $m) => $m->is_active),
                        'roles' => $bg->map(fn (Membership $m) => $this->roleValue($m))->unique()->values(),
                    ])
                    ->values();

                return [
                    'school_id' => $first->school_id,
                    'school_name' => $first->school->name,
                    'roles' => $schoolRoles,
                    'branches' => $branches,
                ];
            })
            ->values();
    }

    /**
     * Platform-scoped roles (Temari staff) the user holds — not tied to a school.
     *
     * @param  Collection<int, string>  $roleNames
     * @return Collection<int, string>
     */
    private function platformRoles(Collection $roleNames): Collection
    {
        return $roleNames
            ->filter(fn (string $name) => (Role::tryFrom($name)?->isPlatform()) ?? false)
            ->values();
    }

    /**
     * Roles the user holds that are anchored nowhere — no platform, school, or
     * branch membership represents them (e.g. an independent parent / student).
     *
     * @param  Collection<int, string>  $roleNames
     * @param  Collection<int, Membership>  $memberships
     * @return Collection<int, string>
     */
    private function otherRoles(Collection $roleNames, Collection $memberships): Collection
    {
        $anchored = $memberships->map(fn (Membership $m) => $this->roleValue($m));

        return $roleNames
            ->reject(fn (string $name) => (Role::tryFrom($name)?->isPlatform()) ?? false)
            ->reject(fn (string $name) => $anchored->contains($name))
            ->values();
    }

    /**
     * Restrict a target user's memberships to those a scoped admin is allowed to
     * see — the schools they manage and the branches they belong to. Mirrors the
     * scope logic in User::scopeManageableBy() / sharesScopeWith() so that WHAT is
     * shown about a user never exceeds WHY they are visible in the first place.
     *
     * @param  Collection<int, Membership>  $memberships
     * @return Collection<int, Membership>
     */
    private function visibleMemberships(Collection $memberships, User $actor): Collection
    {
        $schoolIds = $actor->managedSchoolIdsForContext();
        $branchIds = $actor->accessibleBranchIdsForContext();

        /** @var User $target */
        $target = $this->resource;

        return $memberships
            ->filter(function (Membership $m) use ($schoolIds, $branchIds, $actor, $target): bool {
                $inScope = ($m->school_id !== null && in_array((int) $m->school_id, $schoolIds, true))
                    || ($m->branch_id !== null && in_array((int) $m->branch_id, $branchIds, true));

                if (! $inScope) {
                    return false;
                }

                // Rank gate: never reveal the affiliations of someone who OUTRANKS the
                // actor in this scope. A director must not see which branches a
                // principal is on. Peers (equal rank) stay visible but read-only
                // (see the `can_manage` flag); only strict superiors are hidden.
                $schoolId = $m->school_id !== null ? (int) $m->school_id : null;
                $branchId = $m->branch_id !== null ? (int) $m->branch_id : null;
                $actorLevel = $actor->authorityLevelFor($schoolId, $branchId);
                $targetLevel = $target->authorityLevelFor($schoolId, $branchId, includeInactive: true, platformOutranks: false);

                return $actorLevel === null || $targetLevel === null || $targetLevel >= $actorLevel;
            })
            ->values();
    }

    /**
     * Authoritative per-membership manage check, reusing MembershipPolicy so the UI
     * can never offer an action the API would reject. The membership's `user`
     * relation is set to this resource's model to avoid an extra query per row.
     */
    private function canManage(?User $actor, Membership $membership): bool
    {
        if ($actor === null) {
            return false;
        }

        if (! $membership->relationLoaded('user')) {
            $membership->setRelation('user', $this->resource);
        }

        return $actor->can('manage', $membership);
    }

    private function roleValue(Membership $membership): string
    {
        return $membership->role instanceof Role ? $membership->role->value : $membership->role;
    }
}
