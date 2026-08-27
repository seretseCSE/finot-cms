<?php

namespace App\Support;

use App\Models\ContinuousAssessment;
use App\Models\ContinuousAssessmentTarget;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Subject;
use Illuminate\Support\Collection;

/**
 * Resolves the grade / section / subject NAMES behind a plan's targeting rows
 * in a single batch, attaching a ready-to-serialize `presented_targets` array
 * onto each book. Shared by the API resource and the plan-conflict payload so
 * the frontend renders identical scope chips ("Grade 5 · A, B · Math"),
 * localising only the "All …" fallbacks itself.
 */
class ContinuousAssessmentTargetPresenter
{
    /**
     * @param  iterable<int, ContinuousAssessment>  $books  Each must have `targets` loaded.
     */
    public static function attach(iterable $books): void
    {
        /** @var Collection<int, ContinuousAssessment> $books */
        $books = collect($books);

        /** @var Collection<int, ContinuousAssessmentTarget> $targets */
        $targets = $books->flatMap(fn (ContinuousAssessment $b): Collection => $b->targets ?? collect());

        if ($targets->isEmpty()) {
            $books->each(fn (ContinuousAssessment $b) => $b->setAttribute('presented_targets', []));

            return;
        }

        $gradeIds = $targets->pluck('grade_level_id')->filter()->unique()->values();
        $sectionIds = $targets->flatMap(fn (ContinuousAssessmentTarget $t): array => $t->sectionIds())->unique()->values();
        $subjectIds = $targets->flatMap(fn (ContinuousAssessmentTarget $t): array => $t->subjectIds())->unique()->values();

        $grades = $gradeIds->isEmpty() ? collect() : GradeLevel::query()->whereIn('id', $gradeIds)->pluck('name', 'id');
        $sections = $sectionIds->isEmpty() ? collect() : Section::query()->whereIn('id', $sectionIds)->pluck('name', 'id');
        $subjects = $subjectIds->isEmpty() ? collect() : Subject::query()->whereIn('id', $subjectIds)->pluck('name', 'id');

        foreach ($books as $book) {
            $presented = ($book->targets ?? collect())
                ->map(fn (ContinuousAssessmentTarget $t): array => [
                    'grade_level_id' => $t->grade_level_id,
                    'grade_name' => $t->grade_level_id !== null ? ($grades[$t->grade_level_id] ?? null) : null,
                    'section_ids' => $t->sectionIds(),
                    'section_names' => collect($t->sectionIds())->map(fn (int $id) => $sections[$id] ?? null)->filter()->values()->all(),
                    'subject_ids' => $t->subjectIds(),
                    'subject_names' => collect($t->subjectIds())->map(fn (int $id) => $subjects[$id] ?? null)->filter()->values()->all(),
                ])
                ->values()
                ->all();

            $book->setAttribute('presented_targets', $presented);
        }
    }
}
