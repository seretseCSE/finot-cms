<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\SetUserStatusAction;
use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Enums\RoleScope;
use App\Http\Controllers\Concerns\HandlesBulkActions;
use App\Http\Controllers\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserListResource;
use App\Http\Resources\UserResource;
use App\Models\ImpersonationToken;
use App\Models\Membership;
use App\Models\User;
use App\Rules\EthiopianPhone;
use App\Services\PasswordSetupService;
use App\Support\ActivityLogger;
use App\Support\PhoneNumber;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    use HandlesBulkActions;
    use HandlesListQueries;

    /** Maximum rows returned by a single export request. */
    private const EXPORT_LIMIT = 10000;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $query = $this->baseQuery($request);
        $this->applySort($query, $request, ['name', 'created_at', 'last_login_at', 'status', 'public_id', 'phone', 'email'], 'created_at');

        return UserListResource::collection($query->paginate($this->perPage($request))->withQueryString());
    }

    public function export(Request $request): AnonymousResourceCollection
    {
        $this->authorize('export', User::class);

        $users = $this->baseQuery($request)
            ->orderBy('name')
            ->limit(self::EXPORT_LIMIT)
            ->get();

        return UserListResource::collection($users);
    }

    public function show(Request $request, User $user): UserResource
    {
        $this->authorize('view', $user);

        return new UserResource($user->load(['memberships.school', 'memberships.branch']));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $request->merge(['phone' => PhoneNumber::normalize($request->input('phone')) ?? $request->input('phone')]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', new EthiopianPhone(), 'unique:users,phone'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'preferred_language' => ['nullable', Rule::in(['en', 'am', 'om'])],
            // Only PLATFORM roles may be granted here (as platform memberships).
            // School/branch roles are granted where they belong (school
            // provisioning + MembershipController); relationship roles
            // (parent/student/tutor/vendor) are never assignable (ADR-010).
            'roles' => ['nullable', 'array'],
            'roles.*' => [Rule::in($this->platformRoleValues())],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'preferred_language' => $data['preferred_language'] ?? 'en',
            'status' => AccountStatus::Active,
        ]);

        if (! empty($data['roles'])) {
            $this->syncPlatformMemberships($user, $data['roles']);
        }

        ActivityLogger::log($request->user(), 'user.created', $user, ['roles' => $data['roles'] ?? []]);

        return (new UserResource($user->load(['memberships.school', 'memberships.branch'])))
            ->additional(['message' => 'User created successfully.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $request->merge(['phone' => PhoneNumber::normalize($request->input('phone')) ?? $request->input('phone')]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', new EthiopianPhone(), Rule::unique('users', 'phone')->ignore($user->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'preferred_language' => ['nullable', Rule::in(['en', 'am', 'om'])],
            'avatar_path' => ['nullable', 'string', 'max:2048'],
            'roles' => ['nullable', 'array'],
            'roles.*' => [Rule::in($this->platformRoleValues())],
        ]);

        $user->update([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'preferred_language' => $data['preferred_language'] ?? $user->preferred_language,
            'avatar_path' => $data['avatar_path'] ?? $user->avatar_path,
        ]);

        if (array_key_exists('roles', $data)) {
            $this->syncPlatformMemberships($user, $data['roles'] ?? []);
        }

        ActivityLogger::log($request->user(), 'user.updated', $user);

        return (new UserResource($user->load(['memberships.school', 'memberships.branch'])))
            ->additional(['message' => 'User updated successfully.'])
            ->response();
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $user->delete();
        ActivityLogger::log($request->user(), 'user.deleted', $user);

        return response()->json(['message' => 'User deleted successfully.']);
    }

    /**
     * Take one account back out of the bin. The route resolves trashed models
     * (`->withTrashed()`), since by definition the target is soft-deleted.
     * Deleting a user never cascaded to their memberships, so a restored account
     * comes back with exactly the access it had.
     */
    public function restore(Request $request, User $user): JsonResponse
    {
        $this->authorize('restore', $user);

        if (! $user->trashed()) {
            return response()->json(['message' => 'This account is not deleted.'], 422);
        }

        $user->restore();
        ActivityLogger::log($request->user(), 'user.restored', $user);

        return (new UserListResource($user->load(['memberships.school', 'memberships.branch'])))
            ->additional(['message' => 'User restored.'])
            ->response();
    }

    /**
     * Undo a whole sweep — the counterpart of bulkDestroy, and the recovery path
     * for an accidental bulk delete. Rows that are not actually deleted are
     * skipped and reported rather than silently counted as restored.
     */
    public function bulkRestore(Request $request): JsonResponse
    {
        $data = $request->validate(self::bulkIdRules());

        $actor = $request->user();
        $restored = 0;
        $skipped = [];

        foreach ($this->bulkTrashedTargets($data['ids'], $skipped) as $user) {
            if (! $actor->can('restore', $user)) {
                $skipped[] = self::skip($user->id, $user->name, 'not_permitted');

                continue;
            }

            $user->restore();
            ActivityLogger::log($actor, 'user.restored', $user);
            $restored++;
        }

        return response()->json([
            'message' => "{$restored} user(s) restored.",
            'meta' => ['restored' => $restored, 'requested' => count($data['ids']), 'skipped' => $skipped],
        ]);
    }

    public function setStatus(Request $request, User $user, SetUserStatusAction $action): JsonResponse
    {
        $this->authorize('setStatus', $user);

        $data = $request->validate([
            'status' => ['required', Rule::enum(AccountStatus::class)],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $status = AccountStatus::from($data['status']);
        $action->execute($user, $status, $request->user(), $data['reason'] ?? null);

        return (new UserListResource($user->load(['memberships.school', 'memberships.branch'])))
            ->additional(['message' => "Account status set to {$status->label()}."])
            ->response();
    }

    public function bulkStatus(Request $request, SetUserStatusAction $action): JsonResponse
    {
        $data = $request->validate([
            ...self::bulkIdRules(),
            'status' => ['required', Rule::enum(AccountStatus::class)],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $status = AccountStatus::from($data['status']);
        $actor = $request->user();
        $updated = 0;
        $skipped = [];

        foreach ($this->bulkTargets($data['ids'], $skipped) as $user) {
            if ($this->isSelf($actor, $user)) {
                $skipped[] = self::skip($user->id, $user->name, 'self');

                continue;
            }

            if (! $actor->can('setStatus', $user)) {
                $skipped[] = self::skip($user->id, $user->name, 'not_permitted');

                continue;
            }

            $action->execute($user, $status, $actor, $data['reason'] ?? null);
            $updated++;
        }

        return response()->json([
            'message' => "{$updated} account(s) updated.",
            'meta' => ['updated' => $updated, 'requested' => count($data['ids']), 'skipped' => $skipped],
        ]);
    }

    /**
     * Send a password-setup/reset link to many accounts at once — the "the whole
     * staff room is locked out" case. Each account is policy-checked individually;
     * anyone the actor may not reset, or who has no phone to text, is SKIPPED and
     * reported rather than failing the batch.
     */
    public function bulkResetPassword(Request $request, PasswordSetupService $passwordSetup): JsonResponse
    {
        $data = $request->validate(self::bulkIdRules());

        $actor = $request->user();
        $sent = 0;
        $skipped = [];

        foreach ($this->bulkTargets($data['ids'], $skipped) as $user) {
            if (! $actor->can('resetPassword', $user)) {
                $skipped[] = self::skip($user->id, $user->name, 'not_permitted');

                continue;
            }

            // sendLink() silently no-ops without a recipient number; say so instead
            // of reporting a link that never left the building.
            if ($user->phone === null) {
                $skipped[] = self::skip($user->id, $user->name, 'no_phone');

                continue;
            }

            $passwordSetup->sendLink($user, 'A password reset was requested for your Temari.et account. Set a new password here: ');
            ActivityLogger::log($actor, 'user.password_reset_sent', $user);
            $sent++;
        }

        return response()->json([
            'message' => "Password reset link sent to {$sent} account(s).",
            'meta' => ['sent' => $sent, 'requested' => count($data['ids']), 'skipped' => $skipped],
        ]);
    }

    /**
     * Soft-delete many accounts at once (platform staff only, per-user policy).
     * Already-trashed rows and the actor's own account are skipped, never errors.
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $data = $request->validate(self::bulkIdRules());

        $actor = $request->user();
        $deleted = 0;
        $skipped = [];

        foreach ($this->bulkTargets($data['ids'], $skipped) as $user) {
            if ($this->isSelf($actor, $user)) {
                $skipped[] = self::skip($user->id, $user->name, 'self');

                continue;
            }

            if (! $actor->can('delete', $user)) {
                $skipped[] = self::skip($user->id, $user->name, 'not_permitted');

                continue;
            }

            $user->delete();
            ActivityLogger::log($actor, 'user.deleted', $user);
            $deleted++;
        }

        return response()->json([
            'message' => "{$deleted} user(s) deleted.",
            'meta' => ['deleted' => $deleted, 'requested' => count($data['ids']), 'skipped' => $skipped],
        ]);
    }

    public function resetPassword(Request $request, User $user, PasswordSetupService $passwordSetup): JsonResponse
    {
        $this->authorize('resetPassword', $user);

        $passwordSetup->sendLink($user, 'A password reset was requested for your Temari.et account. Set a new password here: ');
        ActivityLogger::log($request->user(), 'user.password_reset_sent', $user);

        return response()->json(['message' => 'Password reset link sent to the user.']);
    }

    public function uploadAvatar(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $request->validate([
            'avatar' => ['required', 'image', 'max:4096'],
        ]);

        $path = $request->file('avatar')->store('avatars', ['disk' => config('filesystems.default')]);

        $user->forceFill(['avatar_path' => $path])->save();
        ActivityLogger::log($request->user(), 'user.avatar_updated', $user);

        return response()->json([
            'data' => ['avatar_path' => $path, 'avatar_url' => $user->avatarUrl()],
            'message' => 'Avatar updated.',
        ]);
    }

    public function impersonate(Request $request, User $user): JsonResponse
    {
        $this->authorize('impersonate', $user);

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot impersonate yourself.'], 422);
        }

        $plainToken = Str::random(48);

        ImpersonationToken::create([
            'created_by' => $request->user()->id,
            'target_user_id' => $user->id,
            'token' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes(15),
        ]);

        ActivityLogger::log($request->user(), 'user.impersonated', $user);

        $frontendUrl = rtrim(env('FRONTEND_URL', 'http://localhost:3000'), '/');
        $url = "{$frontendUrl}/auth/impersonate?token={$plainToken}";

        return response()->json(['data' => ['url' => $url], 'message' => 'Impersonation link generated.']);
    }

    /**
     * Base query with role-based scoping and all list filters applied.
     *
     * @return Builder<User>
     */
    private function baseQuery(Request $request): Builder
    {
        $actor = $request->user();

        $query = User::query()
            ->manageableBy($actor)
            ->with([
                'memberships.school', 'memberships.branch',
                // Relationship lane (ADR-012): what the Access column shows for
                // students/parents. LIVE enrollments (pending counts — a
                // fee-gated row is already this school's person) — column-pruned
                // and shallow so a 25-row page stays a handful of indexed queries.
                'studentProfile:id,user_id,is_active',
                'studentProfile.enrollments' => fn ($q) => $q
                    ->live()
                    ->select('id', 'student_id', 'school_id', 'branch_id', 'grade_level_id')
                    ->with(['branch:id,name,school_id', 'branch.school:id,name', 'gradeLevel:id,name']),
                'parentProfile:id,user_id',
                'parentProfile.guardianships' => fn ($q) => $q
                    ->where('is_active', true)
                    ->select('id', 'parent_id', 'student_id', 'relationship')
                    ->with([
                        'student:id,first_name,father_name',
                        'student.enrollments' => fn ($e) => $e
                            ->live()
                            ->select('id', 'student_id', 'school_id', 'branch_id')
                            ->with(['branch:id,name,school_id', 'branch.school:id,name']),
                    ]),
            ]);

        // Honor the active workspace context (CLAUDE.md §4) so switching schools/
        // branches changes results. Bounded by manageableBy() above; explicit
        // school_id/branch_id table filters below still narrow further.
        $this->applyActiveContext($query, $request);

        // `search_text` spans name, phone (raw + digits-only), email and the
        // public id behind one trigram index.
        $this->applySearch($query, $request, fn ($q, string $n) => $q
            ->where('search_text', 'ilike', $this->needle($n)));

        $roles = array_values(array_intersect($this->csvValues($request, 'role'), $this->roleValues()));
        if ($roles !== []) {
            // student/parent are relationship-lane labels (ADR-012) — they never
            // exist as memberships, so they filter on the profile link instead.
            $membershipRoles = array_values(array_diff($roles, [Role::Student->value, Role::Parent->value]));

            $query->where(function (Builder $q) use ($roles, $membershipRoles): void {
                $q->whereRaw('1 = 0');
                if ($membershipRoles !== []) {
                    $q->orWhereHas('memberships', fn (Builder $m) => $m->whereIn('role', $membershipRoles));
                }
                if (in_array(Role::Student->value, $roles, true)) {
                    $q->orWhereHas('studentProfile');
                }
                if (in_array(Role::Parent->value, $roles, true)) {
                    $q->orWhereHas('parentProfile');
                }
            });
        }

        $statuses = array_values(array_filter(
            $this->csvValues($request, 'status'),
            fn (string $s) => AccountStatus::tryFrom($s) !== null,
        ));
        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }

        if ($schoolIds = $this->csvIds($request, 'school_id')) {
            $this->applyPlaceFilter($query, $schoolIds, []);
        }

        if ($branchIds = $this->csvIds($request, 'branch_id')) {
            $this->applyPlaceFilter($query, [], $branchIds);
        }

        // User type: affiliated (belongs to a school) vs independent (self-registered).
        // Both selected at once cancels out — no constraint.
        $types = $this->csvValues($request, 'type');
        $hasSchool = $request->has('has_school') ? $request->boolean('has_school') : null;
        $affiliated = in_array('affiliated', $types, true);
        $independent = in_array('independent', $types, true);

        if (($affiliated && ! $independent) || (! $affiliated && ! $independent && $hasSchool === true)) {
            $query->whereHas('memberships', fn (Builder $q) => $q->whereNotNull('school_id'));
        } elseif (($independent && ! $affiliated) || (! $affiliated && ! $independent && $hasSchool === false)) {
            $query->whereDoesntHave('memberships', fn (Builder $q) => $q->whereNotNull('school_id'));
        }

        if ($from = $request->date('registered_from')) {
            $query->where('created_at', '>=', $from->startOfDay());
        }
        if ($to = $request->date('registered_to')) {
            $query->where('created_at', '<=', $to->endOfDay());
        }
        if ($from = $request->date('last_login_from')) {
            $query->where('last_login_at', '>=', $from->startOfDay());
        }
        if ($to = $request->date('last_login_to')) {
            $query->where('last_login_at', '<=', $to->endOfDay());
        }

        if ($actor->hasPlatformPermission('users.delete')) {
            $this->applyTrashedFilter($query, $request);
        }

        return $query;
    }

    /**
     * The explicit School/Branch table filters: an ACTIVE membership there, a
     * student actively enrolled there, or a parent of one.
     *
     * @param  Builder<User>  $query
     * @param  list<int>  $schoolIds
     * @param  list<int>  $branchIds
     */
    private function applyPlaceFilter(Builder $query, array $schoolIds, array $branchIds): void
    {
        $query->where(function (Builder $q) use ($schoolIds, $branchIds): void {
            $q->whereHas('memberships', function (Builder $m) use ($schoolIds, $branchIds): void {
                $m->where('is_active', true);
                $schoolIds !== []
                    ? $m->whereIn('school_id', $schoolIds)
                    : $m->whereIn('branch_id', $branchIds);
            })
                ->orWhereHas('studentProfile.enrollments', User::activeEnrollmentInScope($schoolIds, $branchIds))
                ->orWhereHas('parentProfile.guardianships', function (Builder $g) use ($schoolIds, $branchIds): void {
                    $g->where('is_active', true)
                        ->whereHas('student.enrollments', User::activeEnrollmentInScope($schoolIds, $branchIds));
                });
        });
    }

    /**
     * Narrow the user list to the active branch (or managed school) context, so
     * the workspace switcher visibly changes results. Uses membership *existence*
     * (not is_active) to match scopeManageableBy(), and the shared
     * activeSchoolScopeId() helper so school/platform resolution stays in one
     * place. No concrete context (platform global) → no narrowing.
     *
     * @param  Builder<User>  $query
     */
    private function applyActiveContext(Builder $query, Request $request): void
    {
        $branchId = $request->user()->activeBranchId();
        $schoolId = $branchId === null ? $this->activeSchoolScopeId($request) : null;

        if ($branchId === null && $schoolId === null) {
            return;
        }

        $schoolIds = $schoolId !== null ? [$schoolId] : [];
        $branchIds = $branchId !== null ? [$branchId] : [];

        // Memberships OR the relationship lane (ADR-012): the workspace's
        // directory includes students actively enrolled here and their parents.
        $query->where(function (Builder $q) use ($branchId, $schoolId, $schoolIds, $branchIds): void {
            $q->whereHas('memberships', fn (Builder $m) => $branchId !== null
                ? $m->where('branch_id', $branchId)
                : $m->where('school_id', $schoolId))
                ->orWhereHas('studentProfile.enrollments', User::activeEnrollmentInScope($schoolIds, $branchIds))
                ->orWhereHas('parentProfile.guardianships', function (Builder $g) use ($schoolIds, $branchIds): void {
                    $g->where('is_active', true)
                        ->whereHas('student.enrollments', User::activeEnrollmentInScope($schoolIds, $branchIds));
                });
        });
    }

    /**
     * @return list<string>
     */
    private function roleValues(): array
    {
        return array_map(fn (Role $r) => $r->value, Role::cases());
    }

    /**
     * @return list<string>
     */
    private function platformRoleValues(): array
    {
        return array_map(fn (Role $r) => $r->value, Role::platformRoles());
    }

    /**
     * Reconcile the user's PLATFORM memberships with the given role list —
     * memberships are the sole record of roles (ADR-010). School/branch
     * memberships are never touched here.
     *
     * @param  list<string>  $roles
     */
    private function syncPlatformMemberships(User $user, array $roles): void
    {
        $user->memberships()
            ->whereNull('school_id')
            ->whereNull('branch_id')
            ->whereNotIn('role', $roles)
            ->delete();

        foreach ($roles as $role) {
            $membership = Membership::withTrashed()->firstOrNew([
                'user_id' => $user->id,
                'school_id' => null,
                'branch_id' => null,
                'role' => $role,
            ]);

            if ($membership->trashed()) {
                $membership->restore();
            }

            $membership->fill([
                'scope' => RoleScope::Platform->value,
                'is_active' => true,
                'joined_at' => $membership->joined_at ?? now(),
            ])->save();
        }

        $user->unsetRelation('memberships');
    }
}
