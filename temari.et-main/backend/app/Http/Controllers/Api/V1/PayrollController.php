<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ComputePayrollAction;
use App\Enums\PayrollStatus;
use App\Http\Controllers\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Http\Resources\PayrollRunResource;
use App\Models\PayrollRun;
use App\Services\Notify\Notifier;
use App\Support\ActivityLogger;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Monthly payroll runs. A run is created as DRAFT and computed immediately from
 * current HR data; while draft it can be recomputed/edited/deleted freely.
 * APPROVE freezes the numbers, MARK PAID closes it. Frozen runs never change —
 * payslips are historical documents.
 */
class PayrollController extends Controller
{
    use HandlesListQueries;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PayrollRun::class);

        $branch = $this->activeBranchOrNull($request);
        $schoolScopeId = $this->activeSchoolScopeId($request);

        $query = PayrollRun::query()
            ->withCount('items')
            ->when($branch, fn (Builder $q) => $q->where('branch_id', $branch->id))
            ->when($branch === null && $schoolScopeId !== null, fn (Builder $q) => $q->where('school_id', $schoolScopeId))
            ->when($this->branchFilterId($request, $branch), fn (Builder $q, int $id) => $q->where('branch_id', $id))
            // Platform staff may narrow the cross-school list to one school.
            ->when($branch === null && $schoolScopeId === null && $request->filled('school_id'), fn (Builder $q) => $q->where('school_id', $request->integer('school_id')))
            // Cross-branch views label each run with its branch.
            ->when($branch === null, fn (Builder $q) => $q->with('branch.school'));

        $this->applySearch($query, $request, fn ($q, string $n) => $q
            ->where('name', 'ilike', $this->needle($n)));

        if ($statuses = array_intersect($this->csvValues($request, 'status'), array_column(PayrollStatus::cases(), 'value'))) {
            $query->whereIn('status', $statuses);
        }

        $this->applySort($query, $request, ['name', 'period_start', 'status', 'net_total', 'created_at'], 'period_start');

        return PayrollRunResource::collection($query->paginate($this->perPage($request))->withQueryString());
    }

    public function store(Request $request, ComputePayrollAction $compute): JsonResponse
    {
        $this->authorize('viewAny', PayrollRun::class);
        $branch = $this->targetBranch($request);

        abort_unless(
            $request->user()->hasPermissionForScope('payroll.manage', $branch->school_id, $branch->id),
            403,
        );

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'period_start' => ['required', 'date', 'after:2000-01-01', 'before:2100-01-01'],
            'period_end' => ['required', 'date', 'after:period_start'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $exists = PayrollRun::where('branch_id', $branch->id)
            ->where('period_start', '<=', $data['period_end'])
            ->where('period_end', '>=', $data['period_start'])
            ->exists();
        abort_if($exists, 422, 'A payroll run already covers part of this period.');

        $run = PayrollRun::create([
            ...$data,
            'school_id' => $branch->school_id,
            'branch_id' => $branch->id,
            'status' => PayrollStatus::Draft->value,
            'created_by' => $request->user()->id,
        ]);

        $compute->execute($run);

        return (new PayrollRunResource($run->load('items.employee')->loadCount('items')))
            ->additional(['message' => 'Payroll run created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(PayrollRun $payrollRun): PayrollRunResource
    {
        $this->authorize('view', $payrollRun);

        return new PayrollRunResource($payrollRun->load('items.employee')->loadCount('items'));
    }

    public function update(Request $request, PayrollRun $payrollRun): PayrollRunResource
    {
        $this->authorize('update', $payrollRun);
        abort_unless($payrollRun->isDraft(), 422, 'Only draft payroll runs can be edited.');

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $payrollRun->update($data);

        return new PayrollRunResource($payrollRun->load('items.employee')->loadCount('items'));
    }

    /** Re-pull current HR data into the draft run. */
    public function recompute(PayrollRun $payrollRun, ComputePayrollAction $compute): PayrollRunResource
    {
        $this->authorize('update', $payrollRun);
        abort_unless($payrollRun->isDraft(), 422, 'Approved payroll runs are frozen.');

        $compute->execute($payrollRun);

        return new PayrollRunResource($payrollRun->load('items.employee')->loadCount('items'));
    }

    public function approve(Request $request, PayrollRun $payrollRun): PayrollRunResource
    {
        $this->authorize('approve', $payrollRun);
        abort_unless($payrollRun->isDraft(), 422, 'Only draft payroll runs can be approved.');

        $payrollRun->forceFill([
            'status' => PayrollStatus::Approved->value,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ])->save();

        ActivityLogger::log($request->user(), 'payroll.approved', $payrollRun, [
            'net_total' => (string) $payrollRun->net_total,
        ], $payrollRun->school_id, $payrollRun->branch_id);

        // Approval freezes the run — each employee's payslip is now final.
        $payrollRun->load('items.employee.user');
        app(Notifier::class)->toUsers(
            $payrollRun->items->map(fn ($item) => $item->employee?->user)->filter(),
            'hr.payslip_ready',
            ['period' => $payrollRun->name],
            [
                'link' => '/hr/me?tab=payslips',
                'schoolId' => $payrollRun->school_id,
                'branchId' => $payrollRun->branch_id,
            ],
        );

        return new PayrollRunResource($payrollRun->load('items.employee')->loadCount('items'));
    }

    public function markPaid(Request $request, PayrollRun $payrollRun): PayrollRunResource
    {
        $this->authorize('approve', $payrollRun);
        abort_unless($payrollRun->status === PayrollStatus::Approved, 422, 'Approve the payroll run before marking it paid.');

        $payrollRun->forceFill([
            'status' => PayrollStatus::Paid->value,
            'paid_by' => $request->user()->id,
            'paid_at' => now(),
        ])->save();

        ActivityLogger::log($request->user(), 'payroll.paid', $payrollRun, [
            'net_total' => (string) $payrollRun->net_total,
        ], $payrollRun->school_id, $payrollRun->branch_id);

        return new PayrollRunResource($payrollRun->load('items.employee')->loadCount('items'));
    }

    public function destroy(PayrollRun $payrollRun): JsonResponse
    {
        $this->authorize('delete', $payrollRun);
        abort_unless($payrollRun->isDraft(), 422, 'Approved payroll runs cannot be deleted.');

        $payrollRun->delete();

        return response()->json(['message' => 'Payroll run deleted.']);
    }
}
