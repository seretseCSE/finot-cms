<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateEmployeeAction;
use App\Actions\SetMembershipStatusAction;
use App\Actions\SyncPositionMembershipsAction;
use App\Enums\EmploymentType;
use App\Http\Controllers\Concerns\HandlesBulkActions;
use App\Http\Controllers\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreEmployeeRequest;
use App\Http\Requests\Api\V1\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use App\Models\Membership;
use App\Models\User;
use App\Services\EmployeeAccountProvisioner;
use App\Support\JobTitles;
use App\Support\PhoneNumber;
use App\Support\PublicId;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class EmployeeController extends Controller
{
    use HandlesBulkActions;
    use HandlesListQueries;

    /** Maximum rows returned by a single export request. */
    private const EXPORT_LIMIT = 10000;

    /** Child collections handled by dedicated sync methods, not mass update. */
    private const CHILD_KEYS = ['positions', 'qualifications', 'allowances', 'deductions', 'teacher_subjects', 'create_user_account'];

    /**
     * The account policy the hire form needs BEFORE saving: which job titles
     * come with a portal account at the target branch, and which always must
     * (role-mapped). Resolved like every branch-anchored write — explicit
     * `branch_id` wins, else the validated X-Branch-Id context.
     */
    public function accountPolicy(Request $request): JsonResponse
    {
        $branch = $this->targetBranch($request);

        abort_unless(
            $request->user()->hasPermissionForScope('employees.view', $branch->school_id, $branch->id),
            403,
        );

        return response()->json(['data' => [
            'branch_id' => $branch->id,
            'account_job_titles' => $branch->effectiveEmployeeAccountJobTitles(),
            'required_job_titles' => JobTitles::roleMapped(),
        ]]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Employee::class);

        $query = $this->baseQuery($request);
        $this->applySort($query, $request, ['first_name', 'is_active', 'created_at', 'phone'], 'created_at');

        return EmployeeResource::collection($query->paginate($this->perPage($request))->withQueryString());
    }

    public function export(Request $request): AnonymousResourceCollection
    {
        $this->authorize('export', Employee::class);

        $employees = $this->baseQuery($request)
            ->orderBy('first_name')
            ->limit(self::EXPORT_LIMIT)
            ->get();

        return EmployeeResource::collection($employees);
    }

    public function store(StoreEmployeeRequest $request, CreateEmployeeAction $action): JsonResponse
    {
        $branch = $this->targetBranch($request);

        abort_unless(
            $request->user()->hasPermissionForScope('employees.create', $branch->school_id, $branch->id),
            403,
        );

        $employee = $action->execute($branch, $request->validated());

        return (new EmployeeResource($employee->load($this->detailRelations())))
            ->additional(['message' => 'Employee added.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Employee $employee): EmployeeResource
    {
        $this->authorize('view', $employee);

        return new EmployeeResource($employee->load($this->detailRelations()));
    }

    public function update(
        UpdateEmployeeRequest $request,
        Employee $employee,
        SetMembershipStatusAction $membershipStatus,
        SyncPositionMembershipsAction $syncMemberships,
    ): EmployeeResource {
        $this->authorize('update', $employee);

        $data = $request->validated();
        $this->guardPhoneChange($employee, $data);
        $employee->update(Arr::except($data, self::CHILD_KEYS));

        if (array_key_exists('positions', $data)) {
            $employee->syncPositions($data['positions'] ?? []);
        }
        if (array_key_exists('qualifications', $data)) {
            $employee->syncQualifications($data['qualifications'] ?? []);
        }
        if (array_key_exists('allowances', $data)) {
            $employee->syncAllowances($data['allowances'] ?? []);
        }
        if (array_key_exists('deductions', $data)) {
            $employee->syncDeductions($data['deductions'] ?? []);
        }
        if (array_key_exists('teacher_subjects', $data)) {
            $employee->syncTeacherSubjects($data['teacher_subjects'] ?? []);
        }

        // Keep branch access in lockstep: toggling "active" on the Employees page
        // must revoke/restore the matching branch membership too, otherwise
        // this page and the Users page disagree about the person's access.
        if (array_key_exists('is_active', $data) && $employee->user_id !== null && $employee->branch_id !== null) {
            Membership::where('user_id', $employee->user_id)
                ->where('branch_id', $employee->branch_id)
                ->get()
                ->each(fn (Membership $membership) => $membershipStatus->execute($membership, $employee->is_active, $request->user()));
        }

        // Late account provisioning: an account-less employee whose positions
        // now carry a role-mapped title MUST get a user (memberships need
        // one); the office may also grant access explicitly via the checkbox.
        if ($employee->user_id === null && $employee->branch_id !== null && $employee->phone) {
            $provisioner = app(EmployeeAccountProvisioner::class);
            $branch = $employee->branch;
            $activeTitles = $employee->activePositions()->pluck('job_title')->all();
            $requested = array_key_exists('create_user_account', $data) && (bool) $data['create_user_account'];

            if ($provisioner->accountRequired($activeTitles)
                || ($requested && $provisioner->accountEligible($branch, $activeTitles))) {
                $provisioner->provisionFor($employee);
            }
        }

        // Role-mapped job titles drive memberships (director/teacher/…).
        if (array_key_exists('positions', $data)) {
            $syncMemberships->execute($employee);
        }

        return new EmployeeResource($employee->load($this->detailRelations()));
    }

    /**
     * A phone edit must stay unique within the branch (same person at another
     * branch is fine — one HR file per branch, ADR-011), and when the person's
     * LOGIN is keyed by the old number the account follows: correcting a typo
     * in HR must not strand the portal login on the wrong phone.
     *
     * @param  array<string, mixed>  $data
     */
    private function guardPhoneChange(Employee $employee, array $data): void
    {
        if (! array_key_exists('phone', $data)) {
            return;
        }

        $newPhone = PhoneNumber::normalize((string) $data['phone']) ?? trim((string) $data['phone']);
        $oldPhone = $employee->phone;

        $duplicate = Employee::where('branch_id', $employee->branch_id)
            ->where('phone', $newPhone)
            ->whereKeyNot($employee->id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'phone' => ['Another employee at this branch already uses this phone number.'],
            ]);
        }

        if ($newPhone === $oldPhone || $employee->user === null || $employee->user->phone !== $oldPhone) {
            return;
        }

        $phoneTaken = User::withTrashed()
            ->where('phone', $newPhone)
            ->whereKeyNot($employee->user->id)
            ->exists();

        if ($phoneTaken) {
            throw ValidationException::withMessages([
                'phone' => ['This phone number already belongs to another Temari.et account, so the login cannot follow it. Use a different number.'],
            ]);
        }

        $employee->user->forceFill(['phone' => $newPhone])->save();
    }

    /**
     * Relations the sheet needs on a single-employee response.
     *
     * @return list<string>
     */
    private function detailRelations(): array
    {
        return [
            'branch.school', 'positions', 'qualifications', 'allowances', 'deductions',
            'teacherSubjects.subject:id,code,name', 'teacherSubjects.gradeLevel:id,code,name,sort_order',
            'attachments', 'user.memberships.school:id,name', 'user.memberships.branch:id,name',
        ];
    }

    public function destroy(Request $request, Employee $employee, SetMembershipStatusAction $action): JsonResponse
    {
        $this->authorize('delete', $employee);

        if ($employee->user_id !== null && $employee->branch_id !== null) {
            Membership::where('user_id', $employee->user_id)
                ->where('branch_id', $employee->branch_id)
                ->get()
                ->each(fn (Membership $membership) => $action->execute($membership, false, $request->user()));
        }

        $employee->delete();

        return response()->json(['message' => 'Employee removed.']);
    }

    /**
     * Remove a selection of employees — end of contract season. Each row is
     * policy-checked in its own branch and its branch access is withdrawn the
     * same way `destroy()` does it, so a sweep and a single removal leave the
     * HR file and the memberships in exactly the same state.
     */
    public function bulkDestroy(Request $request, SetMembershipStatusAction $action): JsonResponse
    {
        $data = $request->validate(self::bulkIdRules());

        $actor = $request->user();
        $deleted = 0;
        $skipped = [];

        $rows = $this->bulkRows($data['ids'], Employee::query(), $skipped);

        foreach ($rows as $employee) {
            if ($actor->cannot('delete', $employee)) {
                $skipped[] = self::skipRow($employee, $employee->full_name, 'not_permitted');

                continue;
            }

            // Removing your own HR file would strip your own branch access mid-sweep.
            if ($employee->user_id !== null && $employee->user_id === $actor->id) {
                $skipped[] = self::skipRow($employee, $employee->full_name, 'self');

                continue;
            }

            if ($employee->user_id !== null && $employee->branch_id !== null) {
                Membership::where('user_id', $employee->user_id)
                    ->where('branch_id', $employee->branch_id)
                    ->get()
                    ->each(fn (Membership $membership) => $action->execute($membership, false, $actor));
            }

            $employee->delete();
            $deleted++;
        }

        return response()->json([
            'message' => "{$deleted} employee(s) removed.",
            'meta' => ['deleted' => $deleted, 'requested' => count($data['ids']), 'skipped' => $skipped],
        ]);
    }

    /**
     * Put removed employees back on the register. Branch access is NOT restored
     * automatically — that is a deliberate second decision (Manage branches),
     * because coming back on the HR register and regaining the keys to a branch
     * are different things.
     */
    public function bulkRestore(Request $request): JsonResponse
    {
        $data = $request->validate(self::bulkIdRules());

        $actor = $request->user();
        $restored = 0;
        $skipped = [];

        $rows = $this->bulkRows($data['ids'], Employee::onlyTrashed(), $skipped);

        foreach ($rows as $employee) {
            if ($actor->cannot('delete', $employee)) {
                $skipped[] = self::skipRow($employee, $employee->full_name, 'not_permitted');

                continue;
            }

            $employee->restore();
            $restored++;
        }

        return response()->json([
            'message' => "{$restored} employee(s) restored.",
            'meta' => ['restored' => $restored, 'requested' => count($data['ids']), 'skipped' => $skipped],
        ]);
    }

    /**
     * Scoped base query for the employee list. In a branch context it is limited to
     * that branch; in a no-branch context platform staff see every employee and
     * school managers see their school's. All list filters are then applied.
     *
     * @return Builder<Employee>
     */
    private function baseQuery(Request $request): Builder
    {
        $branch = $this->activeBranchOrNull($request);
        $schoolScopeId = $this->activeSchoolScopeId($request);

        $query = Employee::query()
            // user.memberships powers the Access column + branch management on the
            // employees table (same tree as the Users page); positions/allowances/
            // qualifications feed the table chips and the sheet.
            ->with([
                'branch.school',
                'positions',
                'qualifications',
                'allowances',
                'deductions',
                'teacherSubjects.subject:id,code,name',
                'teacherSubjects.gradeLevel:id,code,name,sort_order',
                'attachments',
                'user.memberships.school:id,name',
                'user.memberships.branch:id,name',
            ])
            ->when($branch, fn (Builder $q) => $q->where('branch_id', $branch->id))
            ->when($branch === null && $schoolScopeId !== null, fn (Builder $q) => $q->where('school_id', $schoolScopeId))
            ->when($this->branchFilterId($request, $branch), fn (Builder $q, int $id) => $q->where('branch_id', $id))
            // Platform staff narrowing (school → branch cascade in the table toolbar).
            ->when($branch === null && $schoolScopeId === null && $request->filled('school_id'), fn (Builder $q) => $q->where('school_id', $request->integer('school_id')));

        // `search_text` spans every name part, the phone (raw + digits-only)
        // and the email behind one trigram index — a full name matches it
        // where the separate name columns never could.
        $this->applySearch($query, $request, fn ($q, string $n) => $q
            ->where('search_text', 'ilike', $this->needle($n))
            ->orWhereHas('user', fn (Builder $u) => $u->where('public_id', PublicId::normalize($n)))
            ->orWhereHas('positions', fn (Builder $p) => $p->where('job_title', 'ilike', $this->needle($n))));

        $this->applyBooleanFilter($query, $request, 'is_active', 'is_active');

        $types = array_values(array_intersect(
            $this->csvValues($request, 'employment_type'),
            array_map(fn (EmploymentType $t) => $t->value, EmploymentType::cases()),
        ));
        if ($types !== []) {
            $query->whereHas('positions', fn (Builder $p) => $p->whereNull('ended_on')->whereIn('employment_type', $types));
        }

        $jobTitles = array_values(array_intersect($this->csvValues($request, 'job_title'), JobTitles::ALL));
        if ($jobTitles !== []) {
            $query->whereHas('positions', fn (Builder $p) => $p->whereNull('ended_on')->whereIn('job_title', $jobTitles));
        }

        if ($branchIds = $this->csvIds($request, 'branch_id')) {
            $query->whereIn('branch_id', $branchIds);
        }

        if ($request->filled('hired_from') || $request->filled('hired_to')) {
            $query->whereHas('positions', function (Builder $p) use ($request): void {
                $this->applyDateRange($p, $request, 'hired_on', 'hired_from', 'hired_to');
            });
        }
        $this->applyDateRange($query, $request, 'created_at', 'created_from', 'created_to');

        // Trashed rows are a platform-only view: school/branch admins never see
        // deleted records, even when they hold employees.delete for live rows.
        if ($request->user()->hasPlatformPermission('employees.delete')) {
            $this->applyTrashedFilter($query, $request);
        }

        return $query;
    }
}
