<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContinuousAssessmentResource;
use App\Models\AssessmentResult;
use App\Models\Branch;
use App\Models\ContinuousAssessment;
use App\Models\Section;
use App\Models\Term;
use App\Services\ContinuousAssessmentConflicts;
use App\Support\ContinuousAssessmentTargetPresenter;
use App\Support\TermGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Grade book templates — the assessment plan a principal/director defines per
 * branch + term + grade window (weights sum to exactly 100). Supervisory lane
 * only (`grades.manage`): teachers RECEIVE the plan in their marklists, they
 * never define structure. School-wide workspace writes name their target
 * branch explicitly (targetBranch pattern).
 */
class ContinuousAssessmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $branch = $this->activeBranchOrNull($request);
        $schoolId = $branch?->school_id ?? $this->activeSchoolScopeId($request);

        abort_if($schoolId === null && $branch === null, 422, 'Select a school context.');
        abort_unless(
            $request->user()->hasPermissionForScope('grades.view', $branch?->school_id ?? $schoolId, $branch?->id),
            403,
        );

        $books = ContinuousAssessment::query()
            ->when($branch !== null, fn ($q) => $q->where('branch_id', $branch->id))
            ->when($branch === null, fn ($q) => $q->where('school_id', $schoolId))
            ->when($this->branchFilterId($request, $branch), fn ($q, $id) => $q->where('branch_id', $id))
            ->when($request->filled('term_id'), fn ($q) => $q->where('term_id', $request->integer('term_id')))
            ->with(['items', 'targets.gradeLevel', 'term:id,name', 'branch:id,name', 'creator:id,name'])
            ->orderByDesc('id')
            ->paginate(min($request->integer('per_page', 25), 100));

        ContinuousAssessmentTargetPresenter::attach($books->getCollection());

        return ContinuousAssessmentResource::collection($books)->response();
    }

    public function store(Request $request, ContinuousAssessmentConflicts $conflicts): JsonResponse
    {
        $branch = $this->targetBranch($request);

        abort_unless(
            $request->user()->hasPermissionForScope('grades.manage', $branch->school_id, $branch->id),
            403,
        );

        $data = $this->validated($request, $branch);

        $term = Term::findOrFail($data['term_id']);
        abort_unless((int) $term->branch_id === (int) $branch->id, 422, 'Term does not belong to the selected branch.');
        TermGate::assertWritable($term);

        $this->assertNoDuplicateTargeting($conflicts, $branch->id, $term->id, $data['targets']);

        $strategy = $data['conflict_strategy'] ?? null;

        // Overlapping structures never stack: the office either confirms a
        // strategy up front or gets the conflict sheet back (409).
        if ($strategy === null) {
            $found = $conflicts->detect($branch->id, $term->id, $data['targets']);

            if (! $conflicts->isClean($found)) {
                return $this->conflictResponse($found);
            }
        }

        $book = DB::transaction(function () use ($data, $branch, $term, $request, $conflicts, $strategy): ContinuousAssessment {
            $book = ContinuousAssessment::create([
                'school_id' => $branch->school_id,
                'branch_id' => $branch->id,
                'term_id' => $term->id,
                'name' => $data['name'],
                'is_active' => $data['is_active'] ?? true,
                'created_by' => $request->user()->id,
            ]);

            $this->syncTargets($book, $data['targets']);
            $this->syncItems($book, $data['items']);

            if ($strategy !== null) {
                $conflicts->resolve($book->refresh(), $strategy);
            }

            return $book;
        });

        return $this->respondWith($book, 'Grade book created.')->setStatusCode(201);
    }

    public function update(Request $request, ContinuousAssessment $continuousAssessment, ContinuousAssessmentConflicts $conflicts): JsonResponse
    {
        abort_unless(
            $request->user()->hasPermissionForScope('grades.manage', $continuousAssessment->school_id, $continuousAssessment->branch_id),
            403,
        );
        TermGate::assertWritable($continuousAssessment->term);

        $branch = Branch::findOrFail($continuousAssessment->branch_id);
        $data = $this->validated($request, $branch, $continuousAssessment);

        $this->assertNoDuplicateTargeting(
            $conflicts,
            $continuousAssessment->branch_id,
            $continuousAssessment->term_id,
            $data['targets'],
            $continuousAssessment->id,
        );

        $strategy = $data['conflict_strategy'] ?? null;

        if ($strategy === null) {
            $found = $conflicts->detect(
                $continuousAssessment->branch_id,
                $continuousAssessment->term_id,
                $data['targets'],
                $continuousAssessment->id,
            );

            if (! $conflicts->isClean($found)) {
                return $this->conflictResponse($found);
            }
        }

        DB::transaction(function () use ($continuousAssessment, $data, $conflicts, $strategy): void {
            $continuousAssessment->update([
                'name' => $data['name'],
                'is_active' => $data['is_active'] ?? $continuousAssessment->is_active,
            ]);

            $this->syncTargets($continuousAssessment, $data['targets']);
            $this->syncItems($continuousAssessment, $data['items']);

            if ($strategy !== null) {
                $conflicts->resolve($continuousAssessment->refresh(), $strategy);
            }
        });

        return $this->respondWith($continuousAssessment);
    }

    /** Reload a saved plan with its targets, resolve names, and wrap it. */
    private function respondWith(ContinuousAssessment $book, ?string $message = null): JsonResponse
    {
        $book->load(['items', 'targets.gradeLevel', 'term:id,name', 'branch:id,name']);
        ContinuousAssessmentTargetPresenter::attach([$book]);

        $resource = new ContinuousAssessmentResource($book);

        if ($message !== null) {
            $resource->additional(['message' => $message]);
        }

        return $resource->response();
    }

    /**
     * A grade → section → subject slot may belong to only ONE active plan per
     * term. Reject a plan whose targeting duplicates another's (422), naming
     * the clash — layering a specific override over a general plan is allowed.
     *
     * @param  list<array<string, mixed>>  $targets
     */
    private function assertNoDuplicateTargeting(
        ContinuousAssessmentConflicts $conflicts,
        int $branchId,
        int $termId,
        array $targets,
        ?int $ignoreBookId = null,
    ): void {
        $clash = $conflicts->firstDuplicateTargeting($branchId, $termId, $targets, $ignoreBookId);

        if ($clash !== null) {
            throw ValidationException::withMessages([
                'targets' => ["{$clash['scope']} is already covered by the plan “{$clash['plan']}”. Combine them into one plan or change the targeting."],
            ]);
        }
    }

    /**
     * @param  array{books: list<array<string, mixed>>, free_form: array{assessments: int, marks_count: int}}  $found
     */
    private function conflictResponse(array $found): JsonResponse
    {
        return response()->json([
            'message' => 'This plan overlaps existing continuous assessments for some of its grades and subjects.',
            'code' => 'plan_conflict',
            'conflicts' => $found,
        ], 409);
    }

    public function destroy(Request $request, ContinuousAssessment $continuousAssessment): JsonResponse
    {
        abort_unless(
            $request->user()->hasPermissionForScope('grades.manage', $continuousAssessment->school_id, $continuousAssessment->branch_id),
            403,
        );
        TermGate::assertWritable($continuousAssessment->term);

        if ($this->itemsHaveMarks($continuousAssessment, $continuousAssessment->items->pluck('id')->all())) {
            throw ValidationException::withMessages([
                'continuous_assessment' => ['Marks are already recorded against this grade book — deactivate it instead.'],
            ]);
        }

        DB::transaction(function () use ($continuousAssessment): void {
            // Remove the materialised (still unmarked) assessments, then the plan.
            foreach ($continuousAssessment->items as $item) {
                $item->assessments()->delete();
            }
            $continuousAssessment->targets()->delete();
            $continuousAssessment->items()->delete();
            $continuousAssessment->delete();
        });

        return response()->json(['message' => 'Grade book deleted.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, Branch $branch, ?ContinuousAssessment $book = null): array
    {
        $data = $request->validate([
            'term_id' => [$book === null ? 'required' : 'sometimes', 'integer', 'exists:terms,id'],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            // Confirmed overlap resolution: replace (start fresh, marks in the
            // overlap are deleted) or migrate (marks move to same-type slots).
            'conflict_strategy' => ['nullable', Rule::in(ContinuousAssessmentConflicts::STRATEGIES)],
            // WHERE the plan applies — one row per grade (null = all grades),
            // optionally narrowed to that grade's sections and/or subjects.
            'targets' => ['required', 'array', 'min:1'],
            'targets.*.grade_level_id' => ['nullable', 'integer', 'exists:grade_levels,id'],
            'targets.*.section_ids' => ['nullable', 'array'],
            'targets.*.section_ids.*' => ['integer'],
            'targets.*.subject_ids' => ['nullable', 'array'],
            'targets.*.subject_ids.*' => ['integer', 'exists:subjects,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.type' => ['required', Rule::in(['quiz', 'test', 'assignment', 'project', 'mid_exam', 'final_exam'])],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.weight' => ['required', 'numeric', 'min:0.5', 'max:100'],
            'items.*.max_score' => ['required', 'numeric', 'min:1', 'max:1000'],
            'items.*.due_on' => ['nullable', 'date', 'after:2000-01-01', 'before:2100-01-01'],
        ]);

        $this->validateTargets($data['targets'], $branch);

        $total = round((float) collect($data['items'])->sum('weight'), 2);

        if ($total !== 100.0) {
            throw ValidationException::withMessages([
                'items' => ["Assessment weights must add up to exactly 100 (currently {$total})."],
            ]);
        }

        return $data;
    }

    /**
     * Targeting rules the request array can't express: grades are unique across
     * rows, an all-grades row is exclusive, sections require a grade and must
     * belong to the branch + that grade.
     *
     * @param  list<array<string, mixed>>  $targets
     */
    private function validateTargets(array $targets, Branch $branch): void
    {
        $grades = array_map(fn (array $t): ?int => isset($t['grade_level_id']) ? (int) $t['grade_level_id'] : null, $targets);

        if (count($grades) !== count(array_unique($grades, SORT_REGULAR))) {
            throw ValidationException::withMessages([
                'targets' => ['Each grade can only be targeted once — combine its sections and subjects in one row.'],
            ]);
        }

        if (in_array(null, $grades, true) && count($targets) > 1) {
            throw ValidationException::withMessages([
                'targets' => ['An "all grades" row already covers everything — remove the other rows or pick specific grades.'],
            ]);
        }

        foreach ($targets as $target) {
            $gradeId = $target['grade_level_id'] ?? null;
            $sectionIds = array_values(array_unique(array_map('intval', $target['section_ids'] ?? [])));

            if ($sectionIds === []) {
                continue;
            }

            if ($gradeId === null) {
                throw ValidationException::withMessages([
                    'targets' => ['Pick a grade before choosing its sections.'],
                ]);
            }

            $valid = Section::query()
                ->where('branch_id', $branch->id)
                ->where('grade_level_id', (int) $gradeId)
                ->whereIn('id', $sectionIds)
                ->count();

            if ($valid !== count($sectionIds)) {
                throw ValidationException::withMessages([
                    'targets' => ['One of the selected sections does not belong to its grade in this branch.'],
                ]);
            }
        }
    }

    /**
     * Replace the plan's targeting rows. Targets hold no marks, so a clean
     * delete-and-recreate keeps the resolver logic simple.
     *
     * @param  list<array<string, mixed>>  $targets
     */
    private function syncTargets(ContinuousAssessment $book, array $targets): void
    {
        $book->targets()->delete();

        foreach ($targets as $row) {
            $sectionIds = array_values(array_unique(array_map('intval', $row['section_ids'] ?? [])));
            $subjectIds = array_values(array_unique(array_map('intval', $row['subject_ids'] ?? [])));

            $book->targets()->create([
                'grade_level_id' => $row['grade_level_id'] ?? null,
                'section_ids' => $sectionIds === [] ? null : $sectionIds,
                'subject_ids' => $subjectIds === [] ? null : $subjectIds,
            ]);
        }
    }

    /**
     * Replace the plan's items in place: rows carrying an id are updated (and
     * their materialised assessments re-sync lazily on next marklist open),
     * new rows are created, and removed rows are deleted — refused when marks
     * already hang off them.
     *
     * @param  list<array<string, mixed>>  $items
     */
    private function syncItems(ContinuousAssessment $book, array $items): void
    {
        $existing = $book->items()->get()->keyBy('id');
        $keptIds = collect($items)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
        $removed = $existing->keys()->diff($keptIds)->all();

        if ($removed !== [] && $this->itemsHaveMarks($book, $removed)) {
            throw ValidationException::withMessages([
                'items' => ['Cannot remove an assessment that already has marks recorded.'],
            ]);
        }

        foreach ($removed as $id) {
            $existing[$id]->assessments()->delete();
            $existing[$id]->delete();
        }

        foreach (array_values($items) as $i => $row) {
            $values = [
                'type' => $row['type'],
                'name' => $row['name'],
                'weight' => $row['weight'],
                'max_score' => $row['max_score'],
                'due_on' => $row['due_on'] ?? null,
                'sort_order' => $i + 1,
            ];

            $id = isset($row['id']) ? (int) $row['id'] : null;

            if ($id !== null && $existing->has($id)) {
                $existing[$id]->update($values);
            } else {
                $book->items()->create($values);
            }
        }
    }

    /**
     * @param  list<int>  $itemIds
     */
    private function itemsHaveMarks(ContinuousAssessment $book, array $itemIds): bool
    {
        if ($itemIds === []) {
            return false;
        }

        return AssessmentResult::query()
            ->whereHas('assessment', fn ($q) => $q->whereIn('continuous_assessment_item_id', $itemIds))
            ->exists();
    }
}
