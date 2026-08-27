<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateBranchAction;
use App\Http\Controllers\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreBranchRequest;
use App\Http\Requests\Api\V1\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use App\Models\School;
use App\Services\OrgStatsService;
use App\Support\GradeOffering;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;

class BranchController extends Controller
{
    use HandlesListQueries;

    /** Maximum rows returned by a single export request. */
    private const EXPORT_LIMIT = 10000;

    /**
     * All branches across schools the user may see — platform staff see every
     * branch; school managers see branches in their managed schools; branch
     * members see only the branches they belong to.
     */
    public function indexAll(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Branch::class);

        $query = $this->baseQuery($request);
        $this->applySort(
            $query,
            $request,
            ['name', 'code', 'students_count', 'teachers_count', 'is_active', 'created_at'],
            'created_at',
        );

        return BranchResource::collection($query->paginate($this->perPage($request))->withQueryString());
    }

    public function exportAll(Request $request): AnonymousResourceCollection
    {
        $this->authorize('export', Branch::class);

        $branches = $this->baseQuery($request)
            ->orderBy('name')
            ->limit(self::EXPORT_LIMIT)
            ->get();

        return BranchResource::collection($branches);
    }

    public function index(Request $request, School $school): AnonymousResourceCollection
    {
        // Only Temari.et staff or a manager of this school (principal /
        // school_admin) pass SchoolPolicy@view, and both see every branch of
        // the school — no per-branch narrowing is required here.
        $this->authorize('view', $school);

        $branches = $school->branches()
            ->withListStats()
            ->with('directorMembership.user')
            ->latest()
            ->paginate((int) min($request->integer('per_page', 25), 100));

        return BranchResource::collection($branches);
    }

    public function store(StoreBranchRequest $request, School $school, CreateBranchAction $action): JsonResponse
    {
        $this->authorize('createBranch', $school);

        $branch = $action->execute($school, $request->validated());

        return (new BranchResource($branch->load('directorMembership.user', 'programs.gradeLevels:grade_levels.id')))
            ->additional(['message' => 'Branch created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Branch $branch): BranchResource
    {
        $this->authorize('view', $branch);

        // Re-query with list stats so the profile header shows the same
        // vitals (grade span, counts) as the branch tables.
        $branch = Branch::query()->withListStats()->findOrFail($branch->id);
        $branch->load('directorMembership.user', 'programs.gradeLevels:grade_levels.id', 'school:id,name');

        // Live usage per grade so the branch editor can disable matrix cells
        // that would fail the GradeOffering removal guards.
        return (new BranchResource($branch))
            ->additional(['meta' => ['grade_usage' => $this->gradeUsage($branch)]]);
    }

    /**
     * Aggregated profile vitals for one branch (students, guardians, workforce
     * by job title, subjects taught, per-grade picture). Visible to whoever may
     * open the branch.
     */
    public function stats(Branch $branch, OrgStatsService $stats): JsonResponse
    {
        $this->authorize('view', $branch);

        return response()->json(['data' => $stats->forBranch($branch)]);
    }

    public function update(UpdateBranchRequest $request, Branch $branch): BranchResource
    {
        $this->authorize('update', $branch);

        $data = $request->validated();
        $branch->update(Arr::except($data, ['programs']));

        // Grade × program matrix sync: additive for programs (removing one is
        // deliberately not done here — enrollments/terms may anchor to them),
        // guarded for grades (in-use cells reject with usage counts).
        if (array_key_exists('programs', $data)) {
            GradeOffering::sync($branch, $data['programs'] ?? []);
        }

        return new BranchResource($branch->load('directorMembership.user', 'programs.gradeLevels:grade_levels.id'));
    }

    public function destroy(Branch $branch): JsonResponse
    {
        $this->authorize('delete', $branch);

        $branch->delete();

        return response()->json(['message' => 'Branch deleted.']);
    }

    /**
     * Live usage per grade level at this branch: active section counts plus
     * live enrollment counts per (grade, program) cell — mirrors exactly what
     * the GradeOffering removal guards check.
     *
     * @return array<int, array{sections?: int, enrollments?: array<int, int>}>
     */
    private function gradeUsage(Branch $branch): array
    {
        $usage = [];

        $sections = $branch->sections()
            ->where('is_active', true)
            ->selectRaw('grade_level_id, count(*) as total')
            ->groupBy('grade_level_id')
            ->pluck('total', 'grade_level_id');

        foreach ($sections as $gradeId => $total) {
            $usage[(int) $gradeId]['sections'] = (int) $total;
        }

        $enrollments = $branch->enrollments()
            ->live()
            ->selectRaw('grade_level_id, school_program_id, count(*) as total')
            ->groupBy('grade_level_id', 'school_program_id')
            ->get();

        foreach ($enrollments as $row) {
            $usage[(int) $row->grade_level_id]['enrollments'][(int) $row->school_program_id] = (int) $row->total;
        }

        return $usage;
    }

    /**
     * Base query for the cross-school branch list, with visibility scoping and
     * all list filters applied.
     *
     * @return Builder<Branch>
     */
    private function baseQuery(Request $request): Builder
    {
        $user = $request->user();
        $activeSchoolId = $user->activeSchoolId();

        $query = Branch::query()
            ->withListStats()
            ->with(['school:id,name', 'directorMembership.user', 'programs'])
            // Branch Management is scoped to the schools the user actually manages
            // (principal / school_admin). Branches they only belong to as branch
            // staff (e.g. a director's own branch) are deliberately excluded —
            // those are reached through operational modules, not this module.
            ->when(! $user->isPlatformUser(), function (Builder $query) use ($user, $activeSchoolId): void {
                $managedSchoolIds = $user->managedSchoolIds();

                // Honour the active workspace: when a school is selected, narrow
                // to it; otherwise fall back to every school the user manages.
                $schoolIds = $activeSchoolId !== null
                    ? array_values(array_intersect($managedSchoolIds, [$activeSchoolId]))
                    : $managedSchoolIds;

                $query->whereIn('school_id', $schoolIds ?: [0]);
            })
            // Platform staff see every branch, but still respect an explicitly
            // selected school workspace.
            ->when($user->isPlatformUser() && $activeSchoolId !== null, function (Builder $query) use ($activeSchoolId): void {
                $query->where('school_id', $activeSchoolId);
            });

        $this->applySearch($query, $request, fn ($q, string $n) => $q
            ->where('name', 'ilike', $this->needle($n))
            ->orWhere('code', 'ilike', $this->needle($n))
            ->orWhere('city', 'ilike', $this->needle($n)));

        if ($schoolIds = $this->csvIds($request, 'school_id')) {
            $query->whereIn('school_id', $schoolIds);
        }

        $this->applyBooleanFilter($query, $request, 'is_active', 'is_active');
        $this->applyDateRange($query, $request, 'created_at', 'created_from', 'created_to');

        if ($user->hasPlatformPermission('branches.delete')) {
            $this->applyTrashedFilter($query, $request);
        }

        return $query;
    }
}
