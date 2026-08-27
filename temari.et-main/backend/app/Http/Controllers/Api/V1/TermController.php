<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CopyTermAssignmentsAction;
use App\Actions\GenerateTermAssignmentsAction;
use App\Enums\TermStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTermRequest;
use App\Http\Requests\Api\V1\UpdateTermRequest;
use App\Http\Resources\TermResource;
use App\Jobs\ComputeTermResultsJob;
use App\Models\AcademicYear;
use App\Models\SchoolProgram;
use App\Models\Term;
use App\Support\SearchTerm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Semesters/terms. Auto-provisioned with their academic year, but also fully
 * manageable on their own: the standalone Semesters page lists them, and new
 * ones can be added under any year (sequence auto-increments, max 5).
 */
class TermController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        // Semester METADATA (names, dates, status) is the branch's operational
        // clock — teachers need it for their marklist/timetable pickers, so
        // timetable.view also opens this list. Management of years/terms (and
        // everything hanging off them, e.g. fees) stays behind academic_years.*.
        abort_unless(
            $request->user()->hasContextPermission('academic_years.view')
            || $request->user()->hasContextPermission('timetable.view'),
            403,
        );

        $branch = $this->activeBranchOrNull($request);
        $schoolScopeId = $this->activeSchoolScopeId($request);

        $terms = Term::query()
            ->with(['academicYear:id,name,status', 'program:id,name,type'])
            ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
            ->when(! $branch && $schoolScopeId, fn ($q) => $q->where('school_id', $schoolScopeId))
            ->when($this->branchFilterId($request, $branch), fn ($q, int $id) => $q->where('branch_id', $id))
            ->when($this->schoolFilterId($request, $branch), fn ($q, int $id) => $q->where('school_id', $id))
            ->when(! $branch, fn ($q) => $q->with('branch.school'))
            ->when(
                $request->filled('academic_year_id'),
                fn ($q) => $q->where('academic_year_id', $request->integer('academic_year_id')),
            )
            ->tap(fn ($q) => SearchTerm::apply($q, $request->string('search')->trim()->value(), fn ($w, string $n) => $w
                ->where('name', 'ilike', SearchTerm::contains($n))))
            ->orderByDesc('is_current')
            ->latest()
            ->orderBy('sequence')
            ->paginate((int) min($request->integer('per_page', 25), 100));

        return TermResource::collection($terms);
    }

    public function show(Term $term): TermResource
    {
        $this->authorize('view', $term);

        return new TermResource($term->load(['academicYear:id,name,status', 'program:id,name,type', 'branch.school']));
    }

    public function store(StoreTermRequest $request, AcademicYear $academicYear): JsonResponse
    {
        $this->authorize('update', $academicYear);

        $data = $request->validated();

        // Semester grouping only makes sense on quarters.
        if (empty($data['is_quarter'])) {
            $data['semester'] = null;
        }

        $term = DB::transaction(function () use ($academicYear, $data): Term {
            $sequence = ((int) $academicYear->terms()->max('sequence')) + 1;
            abort_if($sequence > 5, 422, 'An academic year can hold at most 5 semesters.');

            $term = $academicYear->terms()->create([
                ...Arr::except($data, ['program_type', 'auto_generate_assignments']),
                'school_id' => $academicYear->school_id,
                'branch_id' => $academicYear->branch_id,
                'school_program_id' => $this->resolveProgramId($academicYear, $data),
                'sequence' => $sequence,
                'status' => TermStatus::Planned,
            ]);

            $this->syncCurrentFlag($term);

            // Opt-in only (checkbox, default off): pre-build the teaching grid.
            if (! empty($data['auto_generate_assignments'])) {
                app(GenerateTermAssignmentsAction::class)->execute($term);
            }

            return $term;
        });

        return (new TermResource($term->load(['academicYear:id,name,status', 'program:id,name,type'])))
            ->additional(['message' => 'Semester created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateTermRequest $request, Term $term): TermResource
    {
        $this->authorize('update', $term);

        $data = $request->validated();

        // Semester grouping only makes sense on quarters.
        if ($request->has('is_quarter') && empty($data['is_quarter'])) {
            $data['semester'] = null;
        }

        DB::transaction(function () use ($request, $term, $data): void {
            if ($request->has('program_type')) {
                $data['school_program_id'] = $this->resolveProgramId($term->academicYear, $data);
            }

            $term->update(Arr::except($data, ['program_type']));

            $this->syncCurrentFlag($term);
        });

        return new TermResource(
            $term->refresh()->load(['academicYear:id,name,status', 'program:id,name,type']),
        );
    }

    /**
     * Lifecycle transitions with the one-active rule. Activating a semester
     * CLOSES every other active semester of the same academic year + program
     * (they become read-only via TermGate); the frontend lists them in its
     * confirmation dialog before calling this. Closing freezes the semester;
     * reopening a closed one returns it to planned without activating it.
     */
    public function setStatus(Request $request, Term $term): TermResource
    {
        $this->authorize('update', $term);

        $data = $request->validate([
            'status' => ['required', Rule::enum(TermStatus::class)],
        ]);

        $target = TermStatus::from($data['status']);
        abort_if($target === $term->status, 422, 'The semester already has this status.');

        $closedSiblings = [];
        $closedTermIds = [];

        DB::transaction(function () use ($term, $target, &$closedSiblings, &$closedTermIds): void {
            if ($target === TermStatus::Active) {
                // One active semester per (year, program): close the others.
                $siblings = Term::query()
                    ->where('academic_year_id', $term->academic_year_id)
                    ->where('school_program_id', $term->school_program_id)
                    ->whereKeyNot($term->id)
                    ->where('status', TermStatus::Active->value)
                    ->get();

                foreach ($siblings as $sibling) {
                    $sibling->update(['status' => TermStatus::Closed, 'is_current' => false]);
                    $closedSiblings[] = $sibling->name;
                    $closedTermIds[] = $sibling->id;
                }

                // is_current mirrors "active" within the branch + program.
                Term::query()
                    ->where('branch_id', $term->branch_id)
                    ->where('school_program_id', $term->school_program_id)
                    ->whereKeyNot($term->id)
                    ->where('is_current', true)
                    ->update(['is_current' => false]);

                $term->update(['status' => TermStatus::Active, 'is_current' => true, 'is_active' => true]);

                return;
            }

            if ($target === TermStatus::Closed) {
                $term->update(['status' => TermStatus::Closed, 'is_current' => false]);
                $closedTermIds[] = $term->id;

                return;
            }

            // Reopen: closed -> planned (activate explicitly afterwards).
            abort_unless($term->status === TermStatus::Closed, 422, 'Only a closed semester can be reset to planned.');
            $term->update(['status' => TermStatus::Planned, 'is_current' => false]);
        });

        // Closing freezes the report cards — semester averages, section ranks.
        foreach ($closedTermIds as $closedTermId) {
            ComputeTermResultsJob::dispatch($closedTermId);
        }

        return (new TermResource($term->refresh()->load(['academicYear:id,name,status', 'program:id,name,type'])))
            ->additional(['meta' => ['closed_terms' => $closedSiblings]]);
    }

    /**
     * Duplicate a semester inside its year: same program + schedule settings,
     * next sequence, planned status — and its section/subject/teacher grid.
     */
    public function clone(Request $request, Term $term, CopyTermAssignmentsAction $copy): JsonResponse
    {
        $this->authorize('update', $term);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $clone = DB::transaction(function () use ($term, $data, $copy): Term {
            $year = $term->academicYear;
            $sequence = ((int) $year->terms()->max('sequence')) + 1;
            abort_if($sequence > 5, 422, 'An academic year can hold at most 5 semesters.');

            $clone = $year->terms()->create([
                'school_id' => $term->school_id,
                'branch_id' => $term->branch_id,
                'school_program_id' => $term->school_program_id,
                'name' => $data['name'],
                'sequence' => $sequence,
                'class_starts_at' => $term->class_starts_at,
                'class_ends_at' => $term->class_ends_at,
                'period_minutes' => $term->period_minutes,
                'is_quarter' => $term->is_quarter,
                'status' => TermStatus::Planned,
                'is_current' => false,
                'is_active' => true,
            ]);

            $copy->execute($clone, $term);

            return $clone;
        });

        return (new TermResource($clone->load(['academicYear:id,name,status', 'program:id,name,type'])))
            ->additional(['message' => 'Semester cloned with its assignments.'])
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Term $term): JsonResponse
    {
        $this->authorize('update', $term);

        abort_if(
            $term->academicYear->terms()->count() <= 1,
            422,
            'An academic year needs at least one semester.',
        );

        $term->delete();

        return response()->json(['message' => 'Semester deleted.']);
    }

    /**
     * Resolve the branch program for a catalog type slug, creating it on the
     * branch when missing — picking a program the branch doesn't run yet
     * transparently updates the branch's settings (the UI warns beforehand).
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveProgramId(AcademicYear $year, array $data): ?int
    {
        $type = $data['program_type'] ?? null;

        if ($type === null || $type === '') {
            $first = SchoolProgram::query()
                ->where('branch_id', $year->branch_id)
                ->where('is_active', true)
                ->orderBy('id')
                ->first();

            return ($first ?? SchoolProgram::defaultFor($year->branch))->id;
        }

        return SchoolProgram::addToBranch($year->branch, $type)->id;
    }

    /** At most one current term per branch + program. */
    private function syncCurrentFlag(Term $term): void
    {
        if ($term->is_current) {
            Term::where('branch_id', $term->branch_id)
                ->where('school_program_id', $term->school_program_id)
                ->whereKeyNot($term->id)
                ->where('is_current', true)
                ->update(['is_current' => false]);
        }
    }
}
