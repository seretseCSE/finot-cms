<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ComputeLeaveBalancesAction;
use App\Enums\LeaveRequestStatus;
use App\Http\Controllers\Concerns\HandlesBulkActions;
use App\Http\Controllers\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Http\Resources\LeaveRequestResource;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Notify\Notifier;
use App\Support\ActivityLogger;
use App\Support\Ethiopia;
use App\Support\LeavePolicy;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Leave workflow: any staff member submits for THEMSELVES
 * (`leave.request_own`); HR/managers (`leave.manage`) submit on behalf of
 * anyone in scope and decide requests. Days are always computed server-side
 * (working days: weekends + the school holiday calendar don't consume leave),
 * and approval enforces the balance unless explicitly overridden.
 */
class LeaveRequestController extends Controller
{
    use HandlesBulkActions;
    use HandlesListQueries;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', LeaveRequest::class);

        $branch = $this->activeBranchOrNull($request);
        $schoolScopeId = $this->activeSchoolScopeId($request);

        $query = LeaveRequest::query()
            ->with(['employee:id,first_name,father_name,grandfather_name', 'leaveType:id,name,code,is_paid', 'requestedBy:id,name', 'decidedBy:id,name'])
            ->when($branch, fn (Builder $q) => $q->where('branch_id', $branch->id))
            ->when($branch === null && $schoolScopeId !== null, fn (Builder $q) => $q->where('school_id', $schoolScopeId))
            ->when($this->branchFilterId($request, $branch), fn (Builder $q, int $id) => $q->where('branch_id', $id));

        // Staff without supervisory leave.view only ever see their own requests
        // — the same endpoint powers the self-service page.
        $user = $request->user();
        $supervisory = $user->hasContextPermission('leave.view');
        if (! $supervisory || $request->boolean('mine')) {
            $query->whereIn('employee_id', Employee::where('user_id', $user->id)->select('id'));
        }

        $this->applySearch($query, $request, fn ($q, string $n) => $q
            ->whereHas('employee', fn (Builder $e) => $e->where('search_text', 'ilike', $this->needle($n))));

        if ($statuses = array_intersect($this->csvValues($request, 'status'), array_column(LeaveRequestStatus::cases(), 'value'))) {
            $query->whereIn('status', $statuses);
        }
        if ($typeIds = $this->csvIds($request, 'leave_type_id')) {
            $query->whereIn('leave_type_id', $typeIds);
        }
        if ($employeeIds = $this->csvIds($request, 'employee_id')) {
            $query->whereIn('employee_id', $employeeIds);
        }
        $this->applyDateRange($query, $request, 'start_date', 'start_from', 'start_to');

        $this->applySort($query, $request, ['start_date', 'days', 'status', 'created_at'], 'created_at');

        return LeaveRequestResource::collection($query->paginate($this->perPage($request))->withQueryString());
    }

    public function store(Request $request): JsonResponse
    {
        $branch = $this->targetBranch($request);
        $user = $request->user();

        $canManage = $user->hasPermissionForScope('leave.manage', $branch->school_id, $branch->id);
        $canRequestOwn = $user->hasPermissionForScope('leave.request_own', $branch->school_id, $branch->id);
        abort_unless($canManage || $canRequestOwn, 403);

        // Self-service requests ask for UPCOMING leave — today at the
        // earliest (Addis wall clock). The on-behalf lane stays free to
        // backdate: HR recording sick leave after the fact is normal.
        $onBehalf = $canManage && $request->filled('employee_id');

        $data = $request->validate([
            // Nullable even for managers: self-service (My HR) submits without
            // an employee_id and always resolves to the requester's own profile.
            'employee_id' => [
                'nullable', 'integer',
                Rule::exists('employees', 'id')->where('branch_id', $branch->id)->whereNull('deleted_at'),
            ],
            'leave_type_id' => [
                'required', 'integer',
                Rule::exists('leave_types', 'id')->where('school_id', $branch->school_id)->where('is_active', true)->whereNull('deleted_at'),
            ],
            'start_date' => [
                'required', 'date', 'after:2000-01-01', 'before:2100-01-01',
                ...($onBehalf ? [] : ['after_or_equal:'.Ethiopia::today()]),
            ],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_half_day' => ['sometimes', 'boolean'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ], [
            'start_date.after_or_equal' => __('dates.leave_start_past'),
        ]);

        // Self-service always resolves to the requester's own HR record.
        if ($canManage && ! empty($data['employee_id'])) {
            $employee = Employee::findOrFail($data['employee_id']);
        } else {
            $employee = Employee::where('user_id', $user->id)->where('branch_id', $branch->id)->first();
            abort_if($employee === null, 422, 'No employee profile found for your account in this branch.');
        }

        $type = LeaveType::findOrFail($data['leave_type_id']);

        abort_if(
            $type->applicable_gender !== null && $employee->gender !== null && $employee->gender !== $type->applicable_gender,
            422,
            "{$type->name} does not apply to this staff member.",
        );
        abort_if(
            $type->requires_note && blank($data['reason'] ?? null),
            422,
            "{$type->name} requires a note (e.g. a medical certificate reference).",
        );

        $start = CarbonImmutable::parse($data['start_date']);
        $end = CarbonImmutable::parse($data['end_date']);
        $halfDay = (bool) ($data['is_half_day'] ?? false);
        abort_if($halfDay && ! $start->isSameDay($end), 422, 'A half-day request must start and end on the same day.');

        $days = $halfDay ? 0.5 : LeavePolicy::workingDays($start, $end, $branch->school_id, $branch->id);
        abort_if($days <= 0, 422, 'The selected window has no working days.');

        $overlaps = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', [LeaveRequestStatus::Pending->value, LeaveRequestStatus::Approved->value])
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->exists();
        abort_if($overlaps, 422, 'An open or approved leave request already covers part of this window.');

        $leaveRequest = LeaveRequest::create([
            'school_id' => $branch->school_id,
            'branch_id' => $branch->id,
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'days' => $days,
            'is_half_day' => $halfDay,
            'reason' => $data['reason'] ?? null,
            'status' => LeaveRequestStatus::Pending->value,
            'requested_by' => $user->id,
        ]);

        app(Notifier::class)->toStaff($branch->school_id, $branch->id, 'leave.manage', 'hr.leave_submitted', [
            'name' => $employee->full_name,
            'type' => $type->name,
            'days' => (string) $days,
        ], [
            'link' => '/hr/leave',
            'exceptUserId' => $user->id,
        ]);

        return (new LeaveRequestResource($leaveRequest->load(['employee', 'leaveType', 'requestedBy'])))
            ->additional(['message' => 'Leave request submitted.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(LeaveRequest $leaveRequest): LeaveRequestResource
    {
        $this->authorize('view', $leaveRequest);

        return new LeaveRequestResource($leaveRequest->load(['employee', 'leaveType', 'requestedBy', 'decidedBy']));
    }

    public function approve(Request $request, LeaveRequest $leaveRequest, ComputeLeaveBalancesAction $balances): LeaveRequestResource
    {
        $this->authorize('decide', $leaveRequest);
        abort_unless($leaveRequest->isPending(), 422, 'Only pending requests can be approved.');

        $data = $request->validate([
            'decision_note' => ['nullable', 'string', 'max:2000'],
            // Explicit override for exceptional cases (e.g. negotiated extra days).
            'allow_exceeding_balance' => ['sometimes', 'boolean'],
        ]);

        if (! ($data['allow_exceeding_balance'] ?? false)) {
            $this->assertWithinBalance($leaveRequest, $balances);
        }

        $leaveRequest->update([
            'status' => LeaveRequestStatus::Approved->value,
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
            'decision_note' => $data['decision_note'] ?? null,
        ]);

        ActivityLogger::log($request->user(), 'leave.approved', $leaveRequest, [
            'employee_id' => $leaveRequest->employee_id, 'days' => (string) $leaveRequest->days,
        ], $leaveRequest->school_id, $leaveRequest->branch_id);

        $this->notifyDecision($request, $leaveRequest, 'approved');

        return new LeaveRequestResource($leaveRequest->load(['employee', 'leaveType', 'requestedBy', 'decidedBy']));
    }

    public function reject(Request $request, LeaveRequest $leaveRequest): LeaveRequestResource
    {
        $this->authorize('decide', $leaveRequest);
        abort_unless($leaveRequest->isPending(), 422, 'Only pending requests can be rejected.');

        $data = $request->validate(['decision_note' => ['required', 'string', 'max:2000']]);

        $leaveRequest->update([
            'status' => LeaveRequestStatus::Rejected->value,
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
            'decision_note' => $data['decision_note'],
        ]);

        ActivityLogger::log($request->user(), 'leave.rejected', $leaveRequest, [
            'employee_id' => $leaveRequest->employee_id,
        ], $leaveRequest->school_id, $leaveRequest->branch_id);

        $this->notifyDecision($request, $leaveRequest, 'rejected');

        return new LeaveRequestResource($leaveRequest->load(['employee', 'leaveType', 'requestedBy', 'decidedBy']));
    }

    /**
     * Decide a whole stack of pending requests at once — the HR inbox after a
     * holiday week. Each row is policy-checked on its own; already-decided rows,
     * rows outside the decider's scope, and (on approval) rows that would
     * overdraw the employee's balance are skipped and reported.
     *
     * The balance override is deliberately opt-in here too: a sweep must not
     * quietly grant days nobody has left.
     */
    public function bulkDecide(Request $request, ComputeLeaveBalancesAction $balances): JsonResponse
    {
        $data = $request->validate([
            ...self::bulkIdRules(),
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            // A rejection always says why — same rule as the single-row endpoint.
            'decision_note' => [Rule::requiredIf($request->input('decision') === 'rejected'), 'nullable', 'string', 'max:2000'],
            'allow_exceeding_balance' => ['sometimes', 'boolean'],
        ]);

        $actor = $request->user();
        $approving = $data['decision'] === 'approved';
        $override = (bool) ($data['allow_exceeding_balance'] ?? false);
        $decided = 0;
        $skipped = [];

        $rows = $this->bulkRows(
            $data['ids'],
            // `branch` comes along for LeaveRequestPolicy@decide's scope check.
            // `branch` for LeaveRequestPolicy@decide's scope check; `employee.user`
            // for the decision notification.
            LeaveRequest::with(['employee.user', 'leaveType', 'requestedBy', 'decidedBy', 'branch']),
            $skipped,
        );

        foreach ($rows as $leaveRequest) {
            $name = $leaveRequest->employee?->full_name;

            if ($actor->cannot('decide', $leaveRequest)) {
                $skipped[] = self::skipRow($leaveRequest, $name, 'not_permitted');

                continue;
            }

            if (! $leaveRequest->isPending()) {
                $skipped[] = self::skipRow($leaveRequest, $name, 'already_decided');

                continue;
            }

            if ($approving && ! $override && $this->exceedsBalance($leaveRequest, $balances)) {
                $skipped[] = self::skipRow($leaveRequest, $name, 'exceeds_balance');

                continue;
            }

            $leaveRequest->update([
                'status' => $approving ? LeaveRequestStatus::Approved->value : LeaveRequestStatus::Rejected->value,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'decision_note' => $data['decision_note'] ?? null,
            ]);

            ActivityLogger::log(
                $actor,
                $approving ? 'leave.approved' : 'leave.rejected',
                $leaveRequest,
                ['employee_id' => $leaveRequest->employee_id, 'days' => (string) $leaveRequest->days],
                $leaveRequest->school_id,
                $leaveRequest->branch_id,
            );

            $this->notifyDecision($request, $leaveRequest, $approving ? 'approved' : 'rejected');
            $decided++;
        }

        return response()->json([
            'message' => "{$decided} request(s) decided.",
            'meta' => ['decided' => $decided, 'requested' => count($data['ids']), 'skipped' => $skipped],
        ]);
    }

    /**
     * Owners withdraw their own pending requests; managers can also cancel an
     * approved future leave (plans change).
     */
    public function cancel(Request $request, LeaveRequest $leaveRequest): LeaveRequestResource
    {
        $this->authorize('cancel', $leaveRequest);
        abort_unless(
            $leaveRequest->isPending()
            || ($leaveRequest->status === LeaveRequestStatus::Approved && $leaveRequest->start_date->isFuture()),
            422,
            'Only pending requests or approved future leave can be cancelled.',
        );

        $leaveRequest->update([
            'status' => LeaveRequestStatus::Cancelled->value,
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
        ]);

        return new LeaveRequestResource($leaveRequest->load(['employee', 'leaveType', 'requestedBy', 'decidedBy']));
    }

    public function destroy(LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('delete', $leaveRequest);

        $leaveRequest->delete();

        return response()->json(['message' => 'Leave request deleted.']);
    }

    /** Tell the employee (their own user account) how their request landed. */
    private function notifyDecision(Request $request, LeaveRequest $leaveRequest, string $status): void
    {
        $employeeUser = $leaveRequest->employee?->user;

        app(Notifier::class)->toUser($employeeUser, 'hr.leave_decided', [
            'type' => $leaveRequest->leaveType?->name ?? 'leave',
            'status' => $status,
        ], [
            'link' => '/hr/me',
            'schoolId' => $leaveRequest->school_id,
            'branchId' => $leaveRequest->branch_id,
            'exceptUserId' => $request->user()->id,
        ]);
    }

    /**
     * Balances per employee × type for the Ethiopian leave year containing
     * `date` (default today). Supervisors see the whole branch; everyone else
     * sees their own row.
     */
    public function balances(Request $request, ComputeLeaveBalancesAction $action): JsonResponse
    {
        $branch = $this->targetBranch($request);
        $user = $request->user();

        $supervisory = $user->hasPermissionForScope('leave.view', $branch->school_id, $branch->id);
        abort_unless(
            $supervisory || $user->hasPermissionForScope('leave.request_own', $branch->school_id, $branch->id),
            403,
        );

        LeavePolicy::provisionDefaults($branch->school);

        $asOf = $request->date('date') !== null
            ? CarbonImmutable::parse($request->date('date'))
            : CarbonImmutable::now();

        $employees = Employee::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->when(! $supervisory || $request->boolean('mine'), fn (Builder $q) => $q->where('user_id', $user->id))
            ->with('positions')
            ->orderBy('first_name')
            ->get();

        $types = LeaveType::where('school_id', $branch->school_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $year = LeavePolicy::leaveYear($asOf);

        return response()->json([
            'data' => $action->execute($employees, $types, $asOf),
            'meta' => [
                'year_start' => $year['start']->toDateString(),
                'year_end' => $year['end']->toDateString(),
            ],
        ]);
    }

    /** Whether approving this request would overdraw the employee's balance. */
    private function exceedsBalance(LeaveRequest $leaveRequest, ComputeLeaveBalancesAction $balances): bool
    {
        $employee = $leaveRequest->employee()->with('positions')->first();
        $type = $leaveRequest->leaveType;

        if ($employee === null || $type === null || $type->days_per_year === null) {
            return false;
        }

        $rows = $balances->execute(collect([$employee]), collect([$type]), CarbonImmutable::parse($leaveRequest->start_date));
        $balance = $rows[0]['balances'][0] ?? null;

        return $balance !== null
            && $balance['remaining'] !== null
            && $leaveRequest->days > $balance['remaining'];
    }

    private function assertWithinBalance(LeaveRequest $leaveRequest, ComputeLeaveBalancesAction $balances): void
    {
        $employee = $leaveRequest->employee()->with('positions')->first();
        $type = $leaveRequest->leaveType;

        if ($employee === null || $type === null || $type->days_per_year === null) {
            return;
        }

        $rows = $balances->execute(collect([$employee]), collect([$type]), CarbonImmutable::parse($leaveRequest->start_date));
        $balance = $rows[0]['balances'][0] ?? null;

        if ($balance !== null && $balance['remaining'] !== null && $leaveRequest->days > $balance['remaining']) {
            abort(422, sprintf(
                'This request (%.1f days) exceeds the remaining %s balance (%.1f days). Enable the override to approve anyway.',
                $leaveRequest->days,
                $type->name,
                $balance['remaining'],
            ));
        }
    }
}
