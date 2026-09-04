<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\GenerateInvoicesAction;
use App\Enums\FeeType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\GenerateInvoicesRequest;
use App\Http\Requests\Api\V1\StoreFeeStructureRequest;
use App\Http\Requests\Api\V1\UpdateFeeStructureRequest;
use App\Http\Resources\FeeStructureResource;
use App\Jobs\SendFeeNotifications;
use App\Models\AcademicYear;
use App\Models\FeeStructure;
use App\Models\Term;
use App\Services\FeeNotifier;
use App\Services\RecurringBillingService;
use App\Support\Ethiopia;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FeeStructureController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', FeeStructure::class);
        $branch = $this->activeBranchOrNull($request);
        $schoolScopeId = $this->activeSchoolScopeId($request);

        $structures = FeeStructure::query()
            ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
            ->when(! $branch && $schoolScopeId, fn ($q) => $q->where('school_id', $schoolScopeId))
            ->when($this->branchFilterId($request, $branch), fn ($q, int $id) => $q->where('branch_id', $id))
            ->when($this->schoolFilterId($request, $branch), fn ($q, int $id) => $q->where('school_id', $id))
            ->when(
                $request->filled('academic_year_id'),
                fn ($q) => $q->where('academic_year_id', $request->integer('academic_year_id')),
            )
            ->when(
                $request->filled('type'),
                fn ($q) => $q->where('type', $request->string('type')->value()),
            )
            ->with($branch
                ? ['gradeLevels', 'academicYear:id,name,status', 'bankAccounts.bank:id,code,name,type,logo']
                : ['gradeLevels', 'academicYear:id,name,status', 'branch.school', 'bankAccounts.bank:id,code,name,type,logo'])
            ->withCount('invoices')
            ->latest()
            ->paginate((int) min($request->integer('per_page', 25), 100));

        return FeeStructureResource::collection($structures);
    }

    /**
     * The active fee structures that APPLY to one enrollment (year + grade):
     * a structure applies when its grade pivot is empty (all grades) or
     * contains the grade. Feeds the registration wizard's fee picker.
     */
    public function applicable(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', FeeStructure::class);

        $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'grade_level_id' => ['required', 'integer', 'exists:grade_levels,id'],
        ]);

        $gradeLevelId = $request->integer('grade_level_id');

        $structures = FeeStructure::query()
            ->where('academic_year_id', $request->integer('academic_year_id'))
            ->where('is_active', true)
            ->where(function ($q) use ($gradeLevelId): void {
                $q->whereDoesntHave('gradeLevels')
                    ->orWhereHas('gradeLevels', fn ($g) => $g->where('grade_levels.id', $gradeLevelId));
            })
            ->with('gradeLevels')
            ->orderBy('name')
            ->get();

        return FeeStructureResource::collection($structures);
    }

    public function store(StoreFeeStructureRequest $request): JsonResponse
    {
        $branch = $this->targetBranch($request);

        abort_unless(
            $request->user()->hasPermissionForScope('fees.manage', $branch->school_id, $branch->id),
            403,
        );

        $this->assertYearInBranch($request->integer('academic_year_id'), $branch->id);

        $data = $this->normalize($request->validated());

        $structure = DB::transaction(function () use ($data, $branch): FeeStructure {
            $structure = FeeStructure::create([
                ...Arr::except($data, ['grade_level_ids', 'bank_account_ids']),
                'school_id' => $branch->school_id,
                'branch_id' => $branch->id,
            ]);

            $structure->gradeLevels()->sync($data['grade_level_ids'] ?? []);
            $structure->bankAccounts()->sync($data['bank_account_ids'] ?? []);

            return $structure;
        });

        return (new FeeStructureResource($structure->load([
            'gradeLevels',
            'academicYear:id,name,status',
            'bankAccounts.bank:id,code,name,type,logo',
        ])))
            ->additional(['message' => 'Fee created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateFeeStructureRequest $request, FeeStructure $feeStructure): FeeStructureResource
    {
        $this->authorize('update', $feeStructure);

        $data = $this->normalize($request->validated());

        DB::transaction(function () use ($feeStructure, $data): void {
            $feeStructure->update(Arr::except($data, ['grade_level_ids', 'bank_account_ids']));

            if (array_key_exists('grade_level_ids', $data)) {
                $feeStructure->gradeLevels()->sync($data['grade_level_ids'] ?? []);
            }

            if (array_key_exists('bank_account_ids', $data)) {
                $feeStructure->bankAccounts()->sync($data['bank_account_ids'] ?? []);
            }
        });

        return new FeeStructureResource($feeStructure->load([
            'gradeLevels',
            'academicYear:id,name,status',
            'bankAccounts.bank:id,code,name,type,logo',
        ]));
    }

    /**
     * Flip the SMS reminder flags in place (the table toggles) — deliberately
     * lightweight so it needs none of the full fee payload. Registration fees
     * carry no notifications at all.
     */
    public function setNotifications(Request $request, FeeStructure $feeStructure): FeeStructureResource
    {
        $this->authorize('update', $feeStructure);

        abort_if(
            $feeStructure->type === FeeType::Registration,
            422,
            'Registration fees have no notifications.',
        );

        $data = $request->validate([
            'notify_parents' => ['sometimes', 'boolean'],
            'notify_students' => ['sometimes', 'boolean'],
        ]);

        $feeStructure->update($data);

        return new FeeStructureResource($feeStructure->load([
            'gradeLevels',
            'academicYear:id,name,status',
            'bankAccounts.bank:id,code,name,type,logo',
        ]));
    }

    /**
     * Who would hear about this fee's open invoices — recipient counts per
     * audience and channel, for the send-notifications confirmation dialog.
     */
    public function notifyPreview(Request $request, FeeStructure $feeStructure, FeeNotifier $notifier): JsonResponse
    {
        $this->authorize('update', $feeStructure);

        $data = $request->validate([
            'parents' => ['sometimes', 'boolean'],
            'students' => ['sometimes', 'boolean'],
        ]);

        return response()->json([
            'data' => $notifier->preview(
                $feeStructure,
                (bool) ($data['parents'] ?? false),
                (bool) ($data['students'] ?? false),
            ),
        ]);
    }

    /**
     * Send the payment notice for this fee's open invoices now (SMS + email,
     * queued) — the catch-up lane for fees billed while notifications were off.
     */
    public function notify(Request $request, FeeStructure $feeStructure): JsonResponse
    {
        $this->authorize('update', $feeStructure);

        $data = $request->validate([
            'parents' => ['sometimes', 'boolean'],
            'students' => ['sometimes', 'boolean'],
        ]);

        $parents = (bool) ($data['parents'] ?? false);
        $students = (bool) ($data['students'] ?? false);

        abort_unless($parents || $students, 422, 'Pick at least one audience.');

        SendFeeNotifications::dispatch($feeStructure->id, $parents, $students);

        return response()->json(['message' => 'Notifications queued.']);
    }

    public function destroy(FeeStructure $feeStructure): JsonResponse
    {
        $this->authorize('delete', $feeStructure);

        $feeStructure->delete();

        return response()->json(['message' => 'Fee deleted.']);
    }

    public function generateInvoices(
        GenerateInvoicesRequest $request,
        FeeStructure $feeStructure,
        GenerateInvoicesAction $action,
        RecurringBillingService $billing,
    ): JsonResponse {
        $this->authorize('update', $feeStructure);

        // Recurring fees generate PER BILLING PERIOD (Ethiopian months) —
        // the manual button runs the same engine as the daily scheduler, so
        // it back-fills every period that has started.
        if ($feeStructure->monthStride() !== null) {
            $feeStructure->loadMissing(['academicYear', 'branch.school']);
            $created = 0;

            foreach ($billing->duePeriods($feeStructure, CarbonImmutable::parse(Ethiopia::today())) as $period) {
                $created += $action->execute($feeStructure, null, $period);
            }

            return response()->json([
                'message' => 'Invoices generated.',
                'meta' => ['created' => $created],
            ]);
        }

        $termId = $request->integer('term_id') ?: null;

        if ($termId !== null && ! Term::where('id', $termId)->where('branch_id', $feeStructure->branch_id)->exists()) {
            throw ValidationException::withMessages(['term_id' => ['The term must belong to this branch.']]);
        }

        $created = $action->execute($feeStructure, $termId);

        return response()->json([
            'message' => 'Invoices generated.',
            'meta' => ['created' => $created],
        ]);
    }

    /**
     * Registration fees carry no schedule, notifications, or penalty — clear
     * them so a type switch can never leave stale billing config behind.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        if (($data['type'] ?? null) === FeeType::Registration->value) {
            $data = [
                ...$data,
                'starts_on' => null,
                'due_on' => null,
                'notify_parents' => false,
                'notify_students' => false,
                'penalty_type' => null,
                'penalty_amount' => null,
                'penalty_increment_days' => null,
            ];
        }

        // The recurring engine only serves monthly/quarterly fees — switching
        // a fee to any other type must never leave a stale auto-generate
        // switch or Ethiopian due day behind.
        if (isset($data['type']) && ! in_array($data['type'], [FeeType::Monthly->value, FeeType::Quarterly->value], true)) {
            $data['billing_day'] = null;
            $data['auto_generate'] = false;
        }

        // A penalty without a type is meaningless; incremental days only apply
        // to incremental penalties.
        if (empty($data['penalty_type'])) {
            $data['penalty_amount'] = null;
            $data['penalty_increment_days'] = null;
        } elseif ($data['penalty_type'] === 'fixed') {
            $data['penalty_increment_days'] = null;
        }

        return $data;
    }

    private function assertYearInBranch(int $yearId, int $branchId): void
    {
        if (! AcademicYear::where('id', $yearId)->where('branch_id', $branchId)->exists()) {
            throw ValidationException::withMessages([
                'academic_year_id' => ['The academic year must belong to this branch.'],
            ]);
        }
    }
}
