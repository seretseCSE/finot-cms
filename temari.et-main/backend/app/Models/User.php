<?php

namespace App\Models;

use App\Enums\AcademicYearStatus;
use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Support\Authorization\PermissionCatalog;
use App\Support\FinanceControls;
use App\Support\PhoneNumber;
use App\Support\PublicId;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * AUTHORIZATION KERNEL (ADR-010)
 *
 * `memberships` is the ONLY source of a user's roles. Spatie tables hold the
 * role → permission catalog; users are never assigned Spatie roles directly.
 * Every effective-permission question is answered by ONE rule, deny-by-default:
 *
 *   allowedTo(permission, schoolId, branchId) =
 *       permission ∈ catalog[roles of the active memberships applying there]
 *
 * where a platform membership applies everywhere, a school membership applies
 * to its school (any branch beneath it), and a branch membership applies only
 * to its exact branch. There is NO fallback to a global permission union — a
 * role held at School A grants nothing at School B, and grants nothing when no
 * context is resolved (platform staff excepted, since their memberships apply
 * everywhere by definition).
 */
#[Fillable([
    'name', 'phone', 'email', 'password', 'preferred_language',
    'notify_via_sms', 'notify_via_email', 'notify_via_push', 'notification_preferences',
    'status', 'status_changed_at', 'status_changed_by', 'status_reason', 'avatar_path', 'last_login_at',
])]
#[Hidden(['password', 'remember_token', 'search_text'])]
class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    /**
     * Mirror the DB defaults on in-memory models: a just-created user must
     * read notify_via_* = true immediately — the notification pipeline often
     * runs in the same request that created the account, before any refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'notify_via_sms' => true,
        'notify_via_email' => true,
        'notify_via_push' => true,
    ];

    /**
     * The active school/branch context for the current request, resolved and
     * validated by the SetActiveContext middleware. Null until resolved —
     * there is deliberately no fallback to any "home" column.
     */
    protected ?int $activeSchoolId = null;

    protected ?int $activeBranchId = null;

    /**
     * Whether setActiveContext() has run for this request. Once it has, a null
     * branch means "all branches" (a real state, e.g. a principal operating at
     * school level).
     */
    protected bool $contextResolved = false;

    /**
     * Per-request memos. Safe because an authenticated actor's own memberships
     * never change mid-request (they administer OTHER users); avoids re-querying
     * for every row of a list.
     *
     * @var list<int>|null
     */
    protected ?array $managedSchoolIdsCache = null;

    /** @var list<int>|null */
    protected ?array $accessibleBranchIdsCache = null;

    /**
     * Ids of DEACTIVATED branches among this user's branch memberships, resolved
     * once per request (single indexed query over at most a handful of ids).
     * A membership tied to an inactive branch grants nothing — see permissionsForScope().
     *
     * @var list<int>|null
     */
    protected ?array $inactiveMembershipBranchIdsCache = null;

    protected ?bool $isPlatformUserCache = null;

    protected ?bool $isSuperAdminCache = null;

    /**
     * Per-branch memo of the user's own section ids (ownership read lane).
     *
     * @var array<int, list<int>>
     */
    protected array $ownedSectionIdsCache = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => AccountStatus::class,
            'status_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'notify_via_sms' => 'boolean',
            'notify_via_email' => 'boolean',
            'notify_via_push' => 'boolean',
            'notification_preferences' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Public-facing code (H8R6WV). Seeders run WithoutModelEvents, so they
        // must backfill explicitly — see DatabaseSeeder.
        static::creating(function (self $user): void {
            $user->public_id ??= PublicId::generate('users');
        });
    }

    /**
     * Every write of the phone lands in the canonical local form (`09…`/`07…`),
     * whatever shape the caller passed — factories, seeders and imports included.
     * Un-normalisable input is stored as typed and rejected by validation upstream.
     */
    protected function phone(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => $value === null ? null : (PhoneNumber::normalize($value) ?? $value),
        );
    }

    /**
     * Match a user by phone in ANY accepted shape — the input is normalised to
     * the canonical local form before comparison, so `+251912…` finds the same
     * account stored as `0912…`. Returns the query unconstrained-to-nothing
     * (matches no rows) when the value is not a valid phone.
     *
     * @param  Builder<User>  $query
     */
    public function scopeWherePhone(Builder $query, ?string $raw): void
    {
        $query->where('phone', PhoneNumber::normalize($raw) ?? '');
    }

    /**
     * Convenience finder for exact phone lookups (login, dedupe). Normalises the
     * input first; null when the value is invalid or no account matches.
     */
    public static function findByPhone(?string $raw): ?self
    {
        if (PhoneNumber::normalize($raw) === null) {
            return null;
        }

        return static::query()->wherePhone($raw)->first();
    }

    /**
     * Whether the account has platform-wide access (global status).
     */
    public function isActive(): bool
    {
        return $this->status === AccountStatus::Active;
    }

    /**
     * Signed/public URL for the user's avatar, or null when unset or the disk
     * cannot resolve it.
     */
    public function avatarUrl(): ?string
    {
        if (! $this->avatar_path) {
            return null;
        }

        try {
            return s3Url($this->avatar_path);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * @return HasMany<Notification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * May the pipeline use this CHANNEL for this CATEGORY? Master boolean ×
     * the per-category override in notification_preferences. Critical events
     * pass `critical: true` — they skip the category mute (a parent must
     * never silently lose "your child is absent"), but still respect the
     * master switch. In-app rows never consult this — the feed is always on.
     */
    public function notificationChannelEnabled(string $channel, string $category, bool $critical = false): bool
    {
        $master = match ($channel) {
            'sms' => $this->notify_via_sms,
            'email' => $this->notify_via_email,
            'push' => $this->notify_via_push,
            default => false,
        };

        if (! $master) {
            return false;
        }

        if ($critical) {
            return true;
        }

        return ($this->notification_preferences[$category][$channel] ?? true) !== false;
    }

    /**
     * @return HasOne<Employee, $this>
     */
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    /**
     * @return HasOne<ParentProfile, $this>
     */
    public function parentProfile(): HasOne
    {
        return $this->hasOne(ParentProfile::class);
    }

    /**
     * @return HasOne<Student, $this>
     */
    public function studentProfile(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    /**
     * The tutor marketplace identity (ADR-012 relationship lane: owning this
     * row IS what makes a user a tutor — never a membership).
     *
     * @return HasOne<TutorProfile, $this>
     */
    public function tutorProfile(): HasOne
    {
        return $this->hasOne(TutorProfile::class);
    }

    public function setActiveContext(?int $schoolId, ?int $branchId): void
    {
        $this->activeSchoolId = $schoolId;
        $this->activeBranchId = $branchId;
        $this->contextResolved = true;
    }

    public function activeSchoolId(): ?int
    {
        return $this->activeSchoolId;
    }

    public function activeBranchId(): ?int
    {
        return $this->activeBranchId;
    }

    /*
    |--------------------------------------------------------------------------
    | Kernel — the single effective-permission gate
    |--------------------------------------------------------------------------
    */

    /**
     * THE effective-permission gate. Deny-by-default; see the class docblock.
     */
    public function allowedTo(string $permission, ?int $schoolId, ?int $branchId): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return in_array($permission, $this->permissionsForScope($schoolId, $branchId), true);
    }

    /**
     * allowedTo() judged in the request's ACTIVE (validated) context. With no
     * context resolved only platform memberships apply — school/branch roles
     * grant nothing outside a concrete context.
     */
    public function hasContextPermission(string $permission): bool
    {
        return $this->allowedTo($permission, $this->activeSchoolId(), $this->activeBranchId());
    }

    /**
     * allowedTo() judged in an EXPLICIT scope, independent of the request
     * headers. Use this whenever the target scope is known from the data being
     * acted on (a student's branch, a membership's school) — it cannot be
     * spoofed by context headers.
     */
    public function hasPermissionForScope(string $permission, ?int $schoolId, ?int $branchId): bool
    {
        return $this->allowedTo($permission, $schoolId, $branchId);
    }

    /**
     * Permission granted by any active PLATFORM membership (or super admin).
     * Use for platform-only capabilities (user administration, trashed views).
     */
    public function hasPlatformPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return in_array($permission, PermissionCatalog::permissionsForRoles($this->platformRoleNames()), true);
    }

    /**
     * The permission names effective for the given school/branch scope, unioned
     * across the roles of the active memberships that apply there. Platform
     * memberships always apply; a school-level membership (principal /
     * school_admin, `branch_id` null) applies to any context within its school,
     * including drilling into one of its branches; a branch membership applies
     * only when that exact branch is in scope.
     *
     * @return list<string>
     */
    public function permissionsForScope(?int $schoolId, ?int $branchId): array
    {
        $roleNames = [];
        $inactiveBranchIds = $this->inactiveMembershipBranchIds();

        foreach ($this->memberships as $membership) {
            if (! $membership->is_active) {
                continue;
            }

            // A DEACTIVATED branch suspends every branch-anchored role in it:
            // teachers can't record marks, directors can't manage — nothing that
            // rides on the branch association works until it is reactivated.
            // School managers and platform staff are unaffected (their authority
            // comes from school/platform memberships), so they can still see the
            // branch and switch it back on.
            if ($membership->branch_id !== null
                && in_array((int) $membership->branch_id, $inactiveBranchIds, true)) {
                continue;
            }

            $role = $this->membershipRole($membership);
            if ($role === null) {
                continue;
            }

            $applies = $role->isPlatform()
                || ($membership->branch_id !== null && $branchId !== null && (int) $membership->branch_id === $branchId)
                || ($membership->branch_id === null && $membership->school_id !== null
                    && $schoolId !== null && (int) $membership->school_id === $schoolId);

            if ($applies) {
                $roleNames[$role->value] = true;
            }
        }

        $permissions = PermissionCatalog::permissionsForRoles(array_keys($roleNames));

        // Director finance gate (school setting, default OFF): the director
        // role alone grants no money authority — in Ethiopian schools the
        // director is the academic head; finance belongs to the finance
        // officer and the principal. A school that wants directors handling
        // money flips `director_finance_access` (school-scope setting a
        // director can never edit). Permissions also granted by another
        // applicable role (e.g. the same user is a finance officer) survive.
        if (isset($roleNames[Role::Director->value])
            && $schoolId !== null
            && ! FinanceControls::directorAccess($schoolId)) {
            $otherPerms = PermissionCatalog::permissionsForRoles(
                array_keys(array_diff_key($roleNames, [Role::Director->value => true])),
            );

            $permissions = array_values(array_filter(
                $permissions,
                fn (string $p): bool => ! in_array($p, self::DIRECTOR_FINANCE_GATED, true)
                    || in_array($p, $otherPerms, true),
            ));
        }

        return $permissions;
    }

    /**
     * Finance permissions the director role only receives when the school's
     * `director_finance_access` setting is on. fees.view / fees.reports.view
     * are NOT gated — directors still chase unpaid students.
     *
     * @var list<string>
     */
    private const DIRECTOR_FINANCE_GATED = [
        'fees.manage',
        'payments.record',
        'finance.books.view',
        'finance.books.manage',
        'finance.books.approve',
    ];

    /**
     * Ids of inactive branches among the user's ACTIVE branch memberships,
     * memoized per request. One `WHERE id IN (…) AND is_active = false` lookup
     * over the (small) set of branch ids — never a per-membership query.
     *
     * @return list<int>
     */
    protected function inactiveMembershipBranchIds(): array
    {
        if ($this->inactiveMembershipBranchIdsCache !== null) {
            return $this->inactiveMembershipBranchIdsCache;
        }

        $branchIds = [];
        foreach ($this->memberships as $membership) {
            if ($membership->is_active && $membership->branch_id !== null) {
                $branchIds[(int) $membership->branch_id] = true;
            }
        }

        if ($branchIds === []) {
            return $this->inactiveMembershipBranchIdsCache = [];
        }

        return $this->inactiveMembershipBranchIdsCache = Branch::query()
            ->whereIn('id', array_keys($branchIds))
            ->where('is_active', false)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Ownership read lane (*_own permissions)
    |--------------------------------------------------------------------------
    */

    /**
     * Ids of the sections that are THE USER'S OWN in a branch: where one of
     * their employee profiles holds a homeroom in an ACTIVE academic year or
     * an active teaching assignment. This is the read-side twin of
     * Section::isTaughtOrHomeroomedBy() and drives every `*_own` list scope
     * (sections.view_own, attendance.view_own). Memoized per request.
     *
     * @return list<int>
     */
    public function ownedSectionIds(int $branchId): array
    {
        if (array_key_exists($branchId, $this->ownedSectionIdsCache)) {
            return $this->ownedSectionIdsCache[$branchId];
        }

        $employeeIds = Employee::query()
            ->where('user_id', $this->id)
            ->where('branch_id', $branchId)
            ->pluck('id');

        if ($employeeIds->isEmpty()) {
            return $this->ownedSectionIdsCache[$branchId] = [];
        }

        $homeroom = SectionHomeroom::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereHas('academicYear', fn ($q) => $q->where('status', AcademicYearStatus::Active->value))
            ->pluck('section_id');

        $teaching = SubjectAssignment::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('is_active', true)
            ->pluck('section_id');

        return $this->ownedSectionIdsCache[$branchId] = $homeroom
            ->merge($teaching)
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Ids of the sections the user homerooms in a branch's ACTIVE academic
     * year(s) — the narrow ownership window behind the attendance lane and
     * the `homeroom_only` section-picker filter.
     *
     * @return list<int>
     */
    public function homeroomSectionIdsInBranch(int $branchId): array
    {
        return SectionHomeroom::query()
            ->whereHas('employee', fn ($q) => $q->where('user_id', $this->id)->where('branch_id', $branchId))
            ->whereHas('academicYear', fn ($q) => $q->where('status', AcademicYearStatus::Active->value))
            ->pluck('section_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Ids of the sections where the user holds the homeroom for ONE academic
     * year — the narrower ownership window for report-card data (a subject
     * teacher sees marks through their marklists; only the homeroom teacher
     * sees the section's assembled term results).
     *
     * @return list<int>
     */
    public function homeroomSectionIds(int $academicYearId): array
    {
        return SectionHomeroom::query()
            ->where('academic_year_id', $academicYearId)
            ->whereHas('employee', fn ($q) => $q->where('user_id', $this->id))
            ->pluck('section_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Every role name the user holds via an ACTIVE membership (any scope).
     *
     * @return list<string>
     */
    public function roleNames(): array
    {
        $names = [];

        foreach ($this->memberships as $membership) {
            if ($membership->is_active && ($role = $this->membershipRole($membership)) !== null) {
                $names[$role->value] = true;
            }
        }

        return array_keys($names);
    }

    /**
     * Union of catalog permissions across every active membership role. This is
     * a COARSE set for client bootstrapping only (the frontend narrows it per
     * context via role_permissions × memberships) — never use it server-side
     * for an authorization decision.
     *
     * @return list<string>
     */
    public function allPermissionNames(): array
    {
        return PermissionCatalog::permissionsForRoles($this->roleNames());
    }

    /**
     * Map of each membership role the user holds → the permission names it
     * grants, so the client can compute the permissions effective in a given
     * school/branch context from the memberships that apply there.
     *
     * @return array<string, list<string>>
     */
    public function rolePermissionsMap(): array
    {
        return PermissionCatalog::mapForRoles($this->roleNames());
    }

    /**
     * Role names of active PLATFORM memberships.
     *
     * @return list<string>
     */
    protected function platformRoleNames(): array
    {
        $names = [];

        foreach ($this->memberships as $membership) {
            $role = $this->membershipRole($membership);

            if ($membership->is_active && $role !== null && $role->isPlatform()) {
                $names[$role->value] = true;
            }
        }

        return array_keys($names);
    }

    public function isSuperAdmin(): bool
    {
        return $this->isSuperAdminCache ??= collect($this->platformRoleNames())
            ->contains(Role::SuperAdmin->value);
    }

    /**
     * Temari.et staff who operate across all schools (super admin, support, …):
     * anyone holding an active platform-scope membership.
     */
    public function isPlatformUser(): bool
    {
        return $this->isPlatformUserCache ??= $this->platformRoleNames() !== [];
    }

    protected function membershipRole(Membership $membership): ?Role
    {
        return $membership->role instanceof Role
            ? $membership->role
            : Role::tryFrom((string) $membership->role);
    }

    /*
    |--------------------------------------------------------------------------
    | Scope helpers (visibility, hierarchy)
    |--------------------------------------------------------------------------
    */

    /**
     * True when the user holds a school-level role (principal/school_admin) for
     * the given school — i.e. can administer every branch under it.
     */
    public function managesSchool(int $schoolId): bool
    {
        return $this->memberships()
            ->where('is_active', true)
            ->where('school_id', $schoolId)
            ->whereNull('branch_id')
            ->whereIn('role', [Role::Principal->value, Role::SchoolAdmin->value])
            ->exists();
    }

    /**
     * True when the user has any active membership tied to the given school.
     */
    public function relatedToSchool(int $schoolId): bool
    {
        return $this->memberships()
            ->where('is_active', true)
            ->where('school_id', $schoolId)
            ->exists();
    }

    public function canAccessBranch(Branch $branch): bool
    {
        return $this->managesSchool($branch->school_id)
            || $this->memberships()
                ->where('is_active', true)
                ->where('branch_id', $branch->id)
                ->exists();
    }

    /**
     * True when the user may operate on branch-scoped data for this branch —
     * platform staff, the school's managers, or a member of the branch itself.
     * NOTE: this answers "may they be here at all", not "may they do X here";
     * pair it with (or prefer) hasPermissionForScope for concrete actions.
     */
    public function operatesInBranch(Branch $branch): bool
    {
        return $this->isPlatformUser() || $this->canAccessBranch($branch);
    }

    /**
     * School ids the user has any active membership for.
     *
     * @return list<int>
     */
    public function accessibleSchoolIds(): array
    {
        return $this->memberships()
            ->where('is_active', true)
            ->whereNotNull('school_id')
            ->pluck('school_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Branch ids the user has an active branch membership for.
     *
     * @return list<int>
     */
    public function accessibleBranchIds(): array
    {
        return $this->accessibleBranchIdsCache ??= $this->memberships()
            ->where('is_active', true)
            ->whereNotNull('branch_id')
            ->pluck('branch_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * School ids the user manages at the school level (principal/school_admin) —
     * i.e. authority over every branch beneath them.
     *
     * @return list<int>
     */
    public function managedSchoolIds(): array
    {
        return $this->managedSchoolIdsCache ??= $this->memberships()
            ->where('is_active', true)
            ->whereNull('branch_id')
            ->whereIn('role', [Role::Principal->value, Role::SchoolAdmin->value])
            ->pluck('school_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Managed-school ids narrowed to the ACTIVE context. When a concrete context is
     * resolved, only the active school counts — so a principal at School B does not
     * carry that authority while operating as a director at School A. With no
     * context resolved (context-less requests) this falls back to the global set;
     * that is safe because these ids only ever NARROW visibility after a permission
     * gate has already passed — they never grant.
     *
     * @return list<int>
     */
    public function managedSchoolIdsForContext(): array
    {
        $all = $this->managedSchoolIds();

        $schoolId = $this->activeSchoolId();
        $branchId = $this->activeBranchId();

        if ($schoolId === null && $branchId === null) {
            return $all;
        }

        return array_values(array_filter($all, fn (int $id): bool => $id === $schoolId));
    }

    /**
     * Accessible-branch ids narrowed to the ACTIVE context: only the active branch
     * counts (a branch admin's authority does not span other branches they happen
     * to belong to). With no context resolved, falls back to the global set.
     *
     * @return list<int>
     */
    public function accessibleBranchIdsForContext(): array
    {
        $all = $this->accessibleBranchIds();

        $schoolId = $this->activeSchoolId();
        $branchId = $this->activeBranchId();

        if ($schoolId === null && $branchId === null) {
            return $all;
        }

        return $branchId !== null
            ? array_values(array_filter($all, fn (int $id): bool => $id === $branchId))
            : [];
    }

    /**
     * The actor's strongest authority (lower = more authority) that actually
     * applies WITHIN the given school/branch scope, based on active memberships.
     * Platform staff outrank everyone (0). A school-level membership
     * (principal / school_admin, `branch_id` null) confers authority over every
     * branch of that school; a branch membership confers authority only within
     * that branch. Returns null when the actor holds no authority in that scope.
     *
     * This is deliberately scope-aware: a role the user happens to hold at an
     * unrelated school must never inflate (nor deflate) their standing here.
     * Authority is always judged in the scope being acted upon — never as a
     * single global "max role", which silently leaks power across tenants and
     * blocks legitimate management of in-scope memberships.
     *
     * $includeInactive is used when ranking a TARGET whose membership may have just
     * been deactivated: a deactivated principal is still a principal for hierarchy
     * purposes, and a director must remain able to REACTIVATE someone they already
     * outrank (otherwise deactivation is a one-way trip). The acting user is always
     * ranked on active memberships only.
     *
     * $platformOutranks controls the platform short-circuit. For the ACTOR it stays
     * true (Temari staff outrank everyone). For a TARGET it must be false: an
     * incidental platform hat (e.g. a support agent who also teaches) must NOT
     * inflate their standing over the principal who manages their school role.
     */
    public function authorityLevelFor(?int $schoolId, ?int $branchId, bool $includeInactive = false, bool $platformOutranks = true): ?int
    {
        if ($platformOutranks && $this->isPlatformUser()) {
            return 0;
        }

        $levels = [];

        foreach ($this->memberships as $membership) {
            if (! $includeInactive && ! $membership->is_active) {
                continue;
            }

            $role = $this->membershipRole($membership);

            if ($role === null) {
                continue;
            }

            // School-level membership → authority over the whole school.
            $isSchoolManager = $membership->branch_id === null
                && $schoolId !== null
                && (int) $membership->school_id === $schoolId;

            // Branch membership → authority only within that specific branch.
            $isBranchMember = $membership->branch_id !== null
                && $branchId !== null
                && (int) $membership->branch_id === $branchId;

            if ($isSchoolManager || $isBranchMember) {
                $levels[] = $role->hierarchyLevel();
            }
        }

        return $levels === [] ? null : min($levels);
    }

    /**
     * True when this actor shares an administrative scope with the target — a
     * school they manage, or a branch they belong to.
     */
    public function sharesScopeWith(User $target): bool
    {
        $schoolIds = $this->managedSchoolIdsForContext();
        $branchIds = $this->accessibleBranchIdsForContext();

        if ($schoolIds === [] && $branchIds === []) {
            return false;
        }

        // Deliberately not filtered to active memberships: an admin must retain
        // authority over someone whose membership they just deactivated, or
        // deactivating becomes a one-way trip with no way to reactivate.
        $shares = $target->memberships()
            ->where(function ($q) use ($schoolIds, $branchIds): void {
                $q->whereRaw('1 = 0');
                if ($schoolIds !== []) {
                    $q->orWhereIn('school_id', $schoolIds);
                }
                if ($branchIds !== []) {
                    $q->orWhereIn('branch_id', $branchIds);
                }
            })
            ->exists();

        // Relationship lane (ADR-012): a student actively enrolled in scope —
        // or a parent of one — is part of the school's community and therefore
        // visible (read-only) for as long as the enrollment lasts.
        if (! $shares) {
            $shares = $target->studentProfile()
                ->whereHas('enrollments', self::activeEnrollmentInScope($schoolIds, $branchIds))
                ->exists()
                || $target->parentProfile()
                    ->whereHas('guardianships', function ($q) use ($schoolIds, $branchIds): void {
                        $q->where('is_active', true)
                            ->whereHas('student.enrollments', self::activeEnrollmentInScope($schoolIds, $branchIds));
                    })
                    ->exists();
        }

        if (! $shares) {
            return false;
        }

        // Rank gate: never allow opening someone who OUTRANKS the actor in the active
        // context — a director must not view a principal of their school. Skipped
        // when no context is resolved (context-less requests).
        $actorLevel = $this->authorityLevelFor($this->activeSchoolId(), $this->activeBranchId());
        $targetLevel = $target->authorityLevelFor($this->activeSchoolId(), $this->activeBranchId(), includeInactive: true, platformOutranks: false);

        return $actorLevel === null || $targetLevel === null || $targetLevel >= $actorLevel;
    }

    /**
     * Constrain a users query to those the actor is permitted to see. Platform
     * staff see everyone; school managers see users in their managed schools;
     * branch admins see users in the branches they belong to.
     *
     * @param  Builder<User>  $query
     */
    public function scopeManageableBy(Builder $query, User $actor): void
    {
        if ($actor->isPlatformUser()) {
            return;
        }

        $schoolIds = $actor->managedSchoolIdsForContext();
        $branchIds = $actor->accessibleBranchIdsForContext();

        // Same reasoning as sharesScopeWith(): a deactivated-but-not-removed
        // membership must still keep the user visible to the admin who scoped
        // them, otherwise there is no way back in from the Users list.
        // Relationship-lane users (ADR-012) are included too: a student
        // actively enrolled in scope, or a parent of one, belongs to the
        // school's community DIRECTORY (read-only) exactly while enrolled —
        // derived at read time, so leaving the school removes them for free.
        $query->where(function (Builder $outer) use ($schoolIds, $branchIds): void {
            $outer
                ->whereHas('memberships', function ($q) use ($schoolIds, $branchIds): void {
                    $q->where(function ($q2) use ($schoolIds, $branchIds): void {
                        $q2->whereRaw('1 = 0');
                        if ($schoolIds !== []) {
                            $q2->orWhereIn('school_id', $schoolIds);
                        }
                        if ($branchIds !== []) {
                            $q2->orWhereIn('branch_id', $branchIds);
                        }
                    });
                })
                ->orWhereHas('studentProfile.enrollments', self::activeEnrollmentInScope($schoolIds, $branchIds))
                ->orWhereHas('parentProfile.guardianships', function ($q) use ($schoolIds, $branchIds): void {
                    $q->where('is_active', true)
                        ->whereHas('student.enrollments', self::activeEnrollmentInScope($schoolIds, $branchIds));
                });
        });

        // Rank gate: never LIST users who outrank the actor in the active context —
        // a director must not see a principal of their school at all. Peers (equal
        // rank) remain listed but read-only. Skipped when no context is resolved
        // (context-less requests), matching the ForContext helpers above.
        $activeSchoolId = $actor->activeSchoolId();
        $activeBranchId = $actor->activeBranchId();
        $actorLevel = $actor->authorityLevelFor($activeSchoolId, $activeBranchId);

        if ($actorLevel === null) {
            return;
        }

        $superiorRoles = collect(Role::cases())
            ->reject(fn (Role $r): bool => $r->isPlatform())
            ->filter(fn (Role $r): bool => $r->hierarchyLevel() < $actorLevel)
            ->map(fn (Role $r): string => $r->value)
            ->all();

        if ($superiorRoles === []) {
            return;
        }

        $query->whereDoesntHave('memberships', function ($q) use ($superiorRoles, $activeSchoolId, $activeBranchId): void {
            $q->whereIn('role', $superiorRoles)
                ->where(function ($q2) use ($activeSchoolId, $activeBranchId): void {
                    $q2->whereRaw('1 = 0');
                    // A school-level superior (principal / school_admin) of the active
                    // school outranks the actor in every branch beneath it.
                    if ($activeSchoolId !== null) {
                        $q2->orWhere(function ($q3) use ($activeSchoolId): void {
                            $q3->whereNull('branch_id')->where('school_id', $activeSchoolId);
                        });
                    }
                    // A superior sharing the active branch.
                    if ($activeBranchId !== null) {
                        $q2->orWhere('branch_id', $activeBranchId);
                    }
                });
        });
    }

    /**
     * Constraint for a LIVE (pending or active) student_enrollments row inside
     * the given school/branch scope — the read-time rule that puts a student
     * (and their parents) in a school's directory exactly while the school has
     * custody of them. PENDING counts: a fee-gated enrollment is already the
     * school's person to manage (ADR-017 live custody) — finance must be able
     * to find the parent to record the registration payment that activates it.
     * No grants are stored: when the enrollment ends, visibility ends with it.
     *
     * @param  list<int>  $schoolIds
     * @param  list<int>  $branchIds
     */
    public static function activeEnrollmentInScope(array $schoolIds, array $branchIds): \Closure
    {
        return function ($q) use ($schoolIds, $branchIds): void {
            $q->whereIn('status', ['pending', 'active'])
                ->where(function ($q2) use ($schoolIds, $branchIds): void {
                    $q2->whereRaw('1 = 0');
                    if ($schoolIds !== []) {
                        $q2->orWhereIn('student_enrollments.school_id', $schoolIds);
                    }
                    if ($branchIds !== []) {
                        $q2->orWhereIn('student_enrollments.branch_id', $branchIds);
                    }
                });
        };
    }
}
