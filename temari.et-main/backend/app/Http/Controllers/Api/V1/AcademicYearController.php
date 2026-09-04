<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\SaveAcademicYearAction;
use App\Enums\AcademicYearStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAcademicYearRequest;
use App\Http\Requests\Api\V1\UpdateAcademicYearRequest;
use App\Http\Resources\AcademicYearResource;
use App\Models\AcademicYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AcademicYearController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AcademicYear::class);
        $branch = $this->activeBranchOrNull($request);
        $schoolScopeId = $this->activeSchoolScopeId($request);

        $query = $branch
            ? $branch->academicYears()->with('terms.program')
            : AcademicYear::query()
                ->when($schoolScopeId, fn ($q) => $q->where('school_id', $schoolScopeId))
                ->when($this->branchFilterId($request, $branch), fn ($q, int $id) => $q->where('branch_id', $id))
                ->when($this->schoolFilterId($request, $branch), fn ($q, int $id) => $q->where('school_id', $id))
                ->with(['terms.program', 'branch.school']);

        $years = $query
            // Fee data rides along only for fee-authorized staff — holding
            // academic_years.view alone never exposes pricing.
            ->when(
                $request->user()->hasContextPermission('fees.view'),
                fn ($q) => $q->withCount('fees'),
            )
            ->orderByRaw("(status = 'active') desc")
            ->latest()
            ->paginate((int) min($request->integer('per_page', 25), 100));

        return AcademicYearResource::collection($years);
    }

    public function store(StoreAcademicYearRequest $request, SaveAcademicYearAction $action): JsonResponse
    {
        $branch = $this->targetBranch($request);

        abort_unless(
            $request->user()->hasPermissionForScope('academic_years.create', $branch->school_id, $branch->id),
            403,
        );

        $year = $action->execute($branch, $request->validated());

        return (new AcademicYearResource($year))
            ->additional(['message' => 'Academic year created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, AcademicYear $academicYear): AcademicYearResource
    {
        $this->authorize('view', $academicYear);

        $academicYear->load('terms.program');

        // The year's fee structures are embedded only for fee-authorized staff.
        // Full fee shape (accounts + invoice counts): the edit sheet seeds its
        // collection-account selection from these rows — omitting the relation
        // made saving from this page silently clear a fee's accounts.
        if ($request->user()->hasPermissionForScope('fees.view', $academicYear->school_id, $academicYear->branch_id)) {
            $academicYear->load([
                'fees' => fn ($q) => $q->withCount('invoices'),
                'fees.gradeLevels',
                'fees.bankAccounts.bank:id,code,name,type,logo',
            ])->loadCount('fees');
        }

        return new AcademicYearResource($academicYear);
    }

    public function update(
        UpdateAcademicYearRequest $request,
        AcademicYear $academicYear,
        SaveAcademicYearAction $action,
    ): AcademicYearResource {
        $this->authorize('update', $academicYear);

        $year = $action->execute($academicYear->branch, $request->validated(), $academicYear);

        return new AcademicYearResource($year);
    }

    /**
     * Switch the year's lifecycle status (planned / active / completed /
     * archived). Activating a year demotes the branch's previous active year to
     * completed — one operating year per branch, always.
     */
    public function setStatus(Request $request, AcademicYear $academicYear): AcademicYearResource
    {
        $this->authorize('update', $academicYear);

        $data = $request->validate([
            'status' => ['required', Rule::enum(AcademicYearStatus::class)],
        ]);

        DB::transaction(function () use ($academicYear, $data): void {
            $academicYear->update(['status' => $data['status']]);

            if ($academicYear->status === AcademicYearStatus::Active) {
                SaveAcademicYearAction::demoteOtherActiveYears($academicYear);
            }
        });

        return new AcademicYearResource($academicYear->refresh()->load('terms.program'));
    }

    public function destroy(AcademicYear $academicYear): JsonResponse
    {
        $this->authorize('delete', $academicYear);

        $academicYear->delete();

        return response()->json(['message' => 'Academic year deleted.']);
    }
}
