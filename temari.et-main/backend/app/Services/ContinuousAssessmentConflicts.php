<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentResult;
use App\Models\ContinuousAssessment;
use App\Models\ContinuousAssessmentTarget;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Subject;
use App\Models\SubjectAssignment;
use App\Support\ContinuousAssessmentTargetPresenter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Saving a continuous-assessment plan whose targeting (grade / sections /
 * subjects) overlaps existing assessments must never STACK the two structures
 * (4 planned + 5 planned = 9 columns). This service detects such overlaps up
 * front — as the set of subject assignments the plans share — and, once the
 * office confirms, resolves them with one of two strategies:
 *
 *  - `replace`  — the new plan starts fresh: overlapping assessments (and any
 *                 marks on them) inside the new plan's scope are deleted.
 *  - `migrate`  — marks are carried over: old assessments are matched to the
 *                 new plan's slots BY TYPE (quiz→quiz, mid_exam→mid_exam …),
 *                 scores rescale proportionally when the max changes, and
 *                 only unmatched leftovers are dropped.
 *
 * A more specific plan silently out-precedes a general one while nothing is
 * marked yet (the materializer self-heals unmarked strays) — a prompt is only
 * raised when recorded marks are at stake or governance would be ambiguous.
 */
class ContinuousAssessmentConflicts
{
    public const array STRATEGIES = ['replace', 'migrate'];

    public function __construct(private readonly ContinuousAssessmentMaterializer $materializer) {}

    /**
     * Everything the new targeting collides with. Empty `books` + zero
     * free-form assessments means the save may proceed without confirmation.
     *
     * @param  list<array<string, mixed>>  $targets  Validated targeting rows.
     * @return array{books: list<array<string, mixed>>, free_form: array{assessments: int, marks_count: int}}
     */
    public function detect(
        int $branchId,
        int $termId,
        array $targets,
        ?int $ignoreBookId = null,
    ): array {
        $newTargets = $this->buildTargets($targets);
        $scope = $this->assignmentsInScope($branchId, $termId, $newTargets);
        $scopeIds = $scope->pluck('id');

        $conflicting = $this->activeBooks($branchId, $termId, $ignoreBookId)
            ->map(function (ContinuousAssessment $other) use ($scope, $newTargets): ?array {
                $shared = $scope->filter(fn (SubjectAssignment $a): bool => $this->bookApplies($other, $a));

                if ($shared->isEmpty()) {
                    return null;
                }

                $marks = $this->marksOnItems($other->items->pluck('id')->all(), $shared->pluck('id')->all());

                // The new plan cleanly out-precedes this one everywhere they
                // meet and nothing is marked — no confirmation needed, the
                // materializer swaps structures on the next marklist open.
                if ($marks === 0 && $shared->every(fn (SubjectAssignment $a): bool => $this->newWins($newTargets, $other, $a))) {
                    return null;
                }

                return ['book' => $other, 'marks' => $marks];
            })
            ->filter()
            ->values();

        // Batch-resolve target names for the surviving conflicts' scope labels.
        ContinuousAssessmentTargetPresenter::attach($conflicting->pluck('book'));

        $books = $conflicting->map(fn (array $row): array => [
            'id' => $row['book']->id,
            'name' => $row['book']->name,
            'targets' => $row['book']->presented_targets ?? [],
            'items_count' => $row['book']->items->count(),
            'marks_count' => $row['marks'],
        ])->all();

        $freeForm = Assessment::query()
            ->whereNull('continuous_assessment_item_id')
            ->whereIn('subject_assignment_id', $scopeIds)
            ->pluck('id');

        return [
            'books' => $books,
            'free_form' => [
                'assessments' => $freeForm->count(),
                'marks_count' => $freeForm->isEmpty()
                    ? 0
                    : AssessmentResult::query()->whereIn('assessment_id', $freeForm)->count(),
            ],
        ];
    }

    /** @param array{books: list<array<string, mixed>>, free_form: array{assessments: int, marks_count: int}} $conflicts */
    public function isClean(array $conflicts): bool
    {
        return $conflicts['books'] === [] && $conflicts['free_form']['assessments'] === 0;
    }

    /**
     * A grade → section → subject slot may be governed by exactly ONE active
     * plan per term. Two plans that would TIE for governance on a shared slot
     * (same grade, section and subject specificity) are a duplicate — the
     * office must combine them or change the targeting. Layering a more
     * specific override over a general plan is NOT a duplicate (precedence
     * cleanly resolves it), so it stays allowed.
     *
     * @param  list<array<string, mixed>>  $targets
     * @return array{plan: string, scope: string}|null The first clash found.
     */
    public function firstDuplicateTargeting(
        int $branchId,
        int $termId,
        array $targets,
        ?int $ignoreBookId = null,
    ): ?array {
        $newTargets = $this->buildTargets($targets);

        foreach ($this->activeBooks($branchId, $termId, $ignoreBookId) as $other) {
            foreach ($newTargets as $newTarget) {
                foreach ($other->targets as $existing) {
                    if ($this->targetsTie($newTarget, $existing)) {
                        return ['plan' => $other->name, 'scope' => $this->describeScope($existing)];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Execute the confirmed strategy for a freshly saved plan. Runs inside
     * the caller's transaction.
     */
    public function resolve(ContinuousAssessment $book, string $strategy): void
    {
        $book->loadMissing(['items', 'targets']);

        $assignments = $this->assignmentsInScope($book->branch_id, $book->term_id, $book->targets);

        foreach ($assignments as $assignment) {
            $this->resolveAssignment($book, $assignment, $strategy);
        }

        $this->retireShadowedBooks($book, $strategy);
    }

    /**
     * Clear or migrate every assessment on one assignment that does not come
     * from the new plan, then leave the plan's own slots materialised.
     */
    private function resolveAssignment(ContinuousAssessment $book, SubjectAssignment $assignment, string $strategy): void
    {
        $newItemIds = $book->items->pluck('id')->all();

        /** @var Collection<int, Assessment> $old */
        $old = $assignment->assessments()
            ->where(fn ($q) => $q
                ->whereNull('continuous_assessment_item_id')
                ->orWhereNotIn('continuous_assessment_item_id', $newItemIds))
            ->withCount('results')
            ->orderBy('id')
            ->get();

        if ($old->isEmpty()) {
            return;
        }

        if ($strategy === 'migrate') {
            $this->materializer->materialize($assignment, $book);
            $this->migrateMarks($book, $assignment, $old);
        }

        foreach ($old as $assessment) {
            $assessment->results()->delete();
            $assessment->delete();
        }
    }

    /**
     * Move recorded marks from the old columns onto the new plan's columns,
     * matched by assessment TYPE in plan order; marked columns claim a slot
     * first. Scores rescale when the maximum changes (12/15 on a /15 quiz
     * becomes 8/10 on a /10 quiz).
     *
     * @param  Collection<int, Assessment>  $old
     */
    private function migrateMarks(ContinuousAssessment $book, SubjectAssignment $assignment, Collection $old): void
    {
        $targets = $assignment->assessments()
            ->whereIn('continuous_assessment_item_id', $book->items->pluck('id'))
            ->withCount('results')
            ->get()
            ->sortBy(fn (Assessment $a) => $book->items->firstWhere('id', $a->continuous_assessment_item_id)?->sort_order ?? 0)
            ->values();

        // Marked old columns pick their slot first so a quiz WITH marks never
        // loses its match to an empty duplicate.
        $candidates = $old
            ->sortBy([['results_count', 'desc'], ['id', 'asc']])
            ->values();

        $claimed = [];

        foreach ($candidates as $source) {
            if ($source->results_count === 0) {
                continue;
            }

            $target = $targets->first(fn (Assessment $t): bool => $t->type === $source->type
                && $t->results_count === 0
                && ! in_array($t->id, $claimed, true));

            if ($target === null) {
                continue;
            }

            $claimed[] = $target->id;

            $oldMax = (float) $source->max_score;
            $newMax = (float) $target->max_score;

            $update = ['assessment_id' => $target->id];

            if ($oldMax > 0 && abs($oldMax - $newMax) > 0.001) {
                $factor = $newMax / $oldMax;
                $update['score'] = DB::raw('ROUND(score * '.$factor.', 2)');
            }

            AssessmentResult::query()->where('assessment_id', $source->id)->update($update);
        }
    }

    /**
     * Overlapping books whose entire DECLARED scope now sits inside the new
     * plan govern nothing anymore: `replace` deletes them outright, `migrate`
     * deactivates them so the definition stays on record. Books that also cover
     * grades / sections / subjects outside the new plan's scope are left
     * untouched — the materializer's precedence keeps governance unambiguous.
     */
    private function retireShadowedBooks(ContinuousAssessment $book, string $strategy): void
    {
        foreach ($this->activeBooks($book->branch_id, $book->term_id, $book->id) as $other) {
            if (! $this->scopeContains($book, $other)) {
                continue;
            }

            if ($strategy === 'replace') {
                $this->deleteBook($other);
            } else {
                $other->update(['is_active' => false]);
            }
        }
    }

    /** Whether every one of $inner's targeting rows is covered by one of $outer's. */
    private function scopeContains(ContinuousAssessment $outer, ContinuousAssessment $inner): bool
    {
        return $inner->targets->every(
            fn (ContinuousAssessmentTarget $in): bool => $outer->targets->contains(
                fn (ContinuousAssessmentTarget $out): bool => $this->targetCovers($out, $in),
            ),
        );
    }

    /**
     * Whether two targeting rows would TIE for governance on some concrete
     * grade → section → subject slot — i.e. they share a slot AND have the same
     * "specific vs all" nature on every axis. Such a tie is a duplicate; a
     * difference on any axis means one out-precedes the other (allowed).
     */
    private function targetsTie(ContinuousAssessmentTarget $a, ContinuousAssessmentTarget $b): bool
    {
        return $this->axisTies($a->grade_level_id, $b->grade_level_id)
            && $this->setAxisTies($a->hasSections() ? $a->sectionIds() : null, $b->hasSections() ? $b->sectionIds() : null)
            && $this->setAxisTies($a->hasSubjects() ? $a->subjectIds() : null, $b->hasSubjects() ? $b->subjectIds() : null);
    }

    /** Grade axis: both "all grades", or both the same specific grade. */
    private function axisTies(?int $a, ?int $b): bool
    {
        if ($a === null && $b === null) {
            return true;
        }

        if ($a !== null && $b !== null) {
            return (int) $a === (int) $b;
        }

        return false;
    }

    /**
     * Section/subject axis: both "all" (null), or both specific with an
     * intersecting set. One "all" and one specific differ in specificity → no
     * tie.
     *
     * @param  list<int>|null  $a
     * @param  list<int>|null  $b
     */
    private function setAxisTies(?array $a, ?array $b): bool
    {
        if ($a === null && $b === null) {
            return true;
        }

        if ($a !== null && $b !== null) {
            return array_intersect($a, $b) !== [];
        }

        return false;
    }

    /** "Grade 9 · A · Mathematics" for the duplicate-targeting message. */
    private function describeScope(ContinuousAssessmentTarget $target): string
    {
        $grade = $target->grade_level_id === null
            ? 'All grades'
            : (GradeLevel::find($target->grade_level_id)?->name ?? "#{$target->grade_level_id}");

        $subjects = $target->hasSubjects()
            ? Subject::query()->whereIn('id', $target->subjectIds())->pluck('name')->implode(', ')
            : 'All subjects';

        if ($target->grade_level_id === null) {
            return "{$grade} · {$subjects}";
        }

        $sections = $target->hasSections()
            ? Section::query()->whereIn('id', $target->sectionIds())->pluck('name')->implode(', ')
            : 'All sections';

        return "{$grade} · {$sections} · {$subjects}";
    }

    /** Whether targeting row $a fully covers row $b (grade ⊇, sections ⊇, subjects ⊇). */
    private function targetCovers(ContinuousAssessmentTarget $a, ContinuousAssessmentTarget $b): bool
    {
        if ($a->grade_level_id !== null && (int) $a->grade_level_id !== (int) $b->grade_level_id) {
            return false;
        }

        // $a limited to some sections only covers $b if $b is within that set.
        if ($a->hasSections() && (! $b->hasSections() || array_diff($b->sectionIds(), $a->sectionIds()) !== [])) {
            return false;
        }

        if ($a->hasSubjects() && (! $b->hasSubjects() || array_diff($b->subjectIds(), $a->subjectIds()) !== [])) {
            return false;
        }

        return true;
    }

    private function deleteBook(ContinuousAssessment $book): void
    {
        $itemIds = $book->items()->pluck('id');

        $assessmentIds = Assessment::query()
            ->whereIn('continuous_assessment_item_id', $itemIds)
            ->pluck('id');

        AssessmentResult::query()->whereIn('assessment_id', $assessmentIds)->delete();
        Assessment::query()->whereKey($assessmentIds)->delete();
        $book->targets()->delete();
        $book->items()->delete();
        $book->delete();
    }

    /**
     * Whether the NEW targeting out-precedes $other on this assignment, per the
     * materializer's ordering (more specific wins; the new plan is always the
     * newer one, so it wins ties).
     *
     * @param  Collection<int, ContinuousAssessmentTarget>  $newTargets
     */
    private function newWins(Collection $newTargets, ContinuousAssessment $other, SubjectAssignment $assignment): bool
    {
        $new = $this->bestSpecificity($newTargets, $assignment);
        $old = $this->bestSpecificity($other->targets, $assignment);

        if ($new === null) {
            return false;
        }

        if ($old === null) {
            return true;
        }

        return $new >= $old;
    }

    /**
     * The highest specificity tuple among $targets that match this assignment,
     * or null when none apply.
     *
     * @param  iterable<int, ContinuousAssessmentTarget>  $targets
     * @return array{0: int, 1: int, 2: int}|null
     */
    private function bestSpecificity(iterable $targets, SubjectAssignment $assignment): ?array
    {
        $gradeLevelId = $assignment->section?->gradeLevel?->id;

        if ($gradeLevelId === null) {
            return null;
        }

        $matching = collect($targets)
            ->filter(fn (ContinuousAssessmentTarget $t): bool => $t->matches(
                (int) $gradeLevelId,
                (int) $assignment->section_id,
                (int) $assignment->subject_id,
            ));

        if ($matching->isEmpty()) {
            return null;
        }

        return $matching->map(fn (ContinuousAssessmentTarget $t): array => $t->specificity())
            ->sort()
            ->last();
    }

    private function bookApplies(ContinuousAssessment $book, SubjectAssignment $assignment): bool
    {
        return $this->bestSpecificity($book->targets, $assignment) !== null;
    }

    /**
     * Active subject assignments any of the given targets cover.
     *
     * @param  iterable<int, ContinuousAssessmentTarget>  $targets
     * @return Collection<int, SubjectAssignment>
     */
    private function assignmentsInScope(int $branchId, int $termId, iterable $targets): Collection
    {
        $targets = collect($targets);

        return SubjectAssignment::query()
            ->where('branch_id', $branchId)
            ->where('term_id', $termId)
            ->where('is_active', true)
            ->with('section.gradeLevel')
            ->get()
            ->filter(function (SubjectAssignment $a) use ($targets): bool {
                $gradeLevelId = $a->section?->gradeLevel?->id;

                if ($gradeLevelId === null) {
                    return false;
                }

                return $targets->contains(fn (ContinuousAssessmentTarget $t): bool => $t->matches(
                    (int) $gradeLevelId,
                    (int) $a->section_id,
                    (int) $a->subject_id,
                ));
            })
            ->values();
    }

    /**
     * Transient (unsaved) target models from validated payload rows, so the
     * new plan's scope can be matched before it exists.
     *
     * @param  list<array<string, mixed>>  $targets
     * @return Collection<int, ContinuousAssessmentTarget>
     */
    private function buildTargets(array $targets): Collection
    {
        return collect($targets)->map(fn (array $row): ContinuousAssessmentTarget => new ContinuousAssessmentTarget([
            'grade_level_id' => $row['grade_level_id'] ?? null,
            'section_ids' => $row['section_ids'] ?? null,
            'subject_ids' => $row['subject_ids'] ?? null,
        ]));
    }

    /**
     * @return Collection<int, ContinuousAssessment>
     */
    private function activeBooks(int $branchId, int $termId, ?int $ignoreBookId): Collection
    {
        return ContinuousAssessment::query()
            ->where('branch_id', $branchId)
            ->where('term_id', $termId)
            ->where('is_active', true)
            ->when($ignoreBookId !== null, fn ($q) => $q->whereKeyNot($ignoreBookId))
            ->with(['items', 'targets'])
            ->get();
    }

    /**
     * @param  list<int>  $itemIds
     * @param  list<int>  $assignmentIds
     */
    private function marksOnItems(array $itemIds, array $assignmentIds): int
    {
        if ($itemIds === [] || $assignmentIds === []) {
            return 0;
        }

        return AssessmentResult::query()
            ->whereHas('assessment', fn ($q) => $q
                ->whereIn('continuous_assessment_item_id', $itemIds)
                ->whereIn('subject_assignment_id', $assignmentIds))
            ->count();
    }
}
