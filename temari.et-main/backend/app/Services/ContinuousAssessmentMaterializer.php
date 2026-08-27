<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\ContinuousAssessment;
use App\Models\SubjectAssignment;

/**
 * Turns the applicable grade-book TEMPLATE into concrete `assessments` rows
 * for one subject assignment — invoked whenever a marklist is opened, so a
 * teacher always finds the principal's plan waiting. Idempotent: one row per
 * template item (unique subject_assignment_id × continuous_assessment_item_id); reruns
 * re-sync name/type/weight/max from the template, keeping the plan
 * authoritative even after edits.
 */
class ContinuousAssessmentMaterializer
{
    /**
     * The grade book that governs this assignment, or null when the branch
     * runs free-form continuous assessments. Deterministic precedence: a
     * subject-specific target beats a general one, a section-specific target
     * beats an all-sections one, a grade-specific target beats an all-grades
     * one, and the newest book wins any remaining tie — so exactly ONE plan
     * ever governs an assignment.
     */
    public function bookFor(SubjectAssignment $assignment): ?ContinuousAssessment
    {
        $assignment->loadMissing(['section.gradeLevel', 'subject']);

        if ($assignment->section?->gradeLevel === null) {
            return null;
        }

        $plans = ContinuousAssessment::query()
            ->where('branch_id', $assignment->branch_id)
            ->where('term_id', $assignment->term_id)
            ->where('is_active', true)
            ->with(['items', 'targets'])
            ->get();

        return $this->governingBook($assignment, $plans);
    }

    /**
     * Of the given candidate plans, the one whose most specific matching
     * target governs this assignment — subject > section > grade specificity,
     * newest book breaking ties. Callers pass a pre-loaded plan set (each with
     * its `targets`) so this can run over many assignments without re-querying.
     *
     * @param  iterable<int, ContinuousAssessment>  $candidatePlans
     */
    public function governingBook(SubjectAssignment $assignment, iterable $candidatePlans): ?ContinuousAssessment
    {
        $assignment->loadMissing('section.gradeLevel');

        $gradeLevelId = $assignment->section?->gradeLevel?->id;

        if ($gradeLevelId === null) {
            return null;
        }

        $sectionId = (int) $assignment->section_id;
        $subjectId = (int) $assignment->subject_id;

        $best = null;
        $bestKey = null;

        foreach ($candidatePlans as $plan) {
            if ((int) $plan->branch_id !== (int) $assignment->branch_id
                || (int) $plan->term_id !== (int) $assignment->term_id
                || ! $plan->is_active) {
                continue;
            }

            $target = $plan->matchingTarget((int) $gradeLevelId, $sectionId, $subjectId);

            if ($target === null) {
                continue;
            }

            // [subject, section, grade, id] — higher wins; newest id breaks ties.
            $key = [...$target->specificity(), (int) $plan->id];

            if ($bestKey === null || $key > $bestKey) {
                $bestKey = $key;
                $best = $plan;
            }
        }

        return $best;
    }

    /**
     * Ensure every template item exists as an assessment on the assignment,
     * synced to the template's current definition. Assessments materialised
     * from a book that no longer governs are removed while still unmarked,
     * so a plan change never leaves stacked duplicates behind — marked
     * strays are resolved through the plan-save conflict flow instead.
     */
    public function materialize(SubjectAssignment $assignment, ContinuousAssessment $book): void
    {
        $existing = $assignment->assessments()
            ->whereNotNull('continuous_assessment_item_id')
            ->get()
            ->keyBy('continuous_assessment_item_id');

        // NB: judged on the ITEM id, never Eloquent's except() — that method
        // filters by model primary key and would match the wrong rows.
        $newItemIds = $book->items->pluck('id')->map(fn ($id) => (int) $id)->all();
        $stale = $existing->filter(
            fn (Assessment $a): bool => ! in_array((int) $a->continuous_assessment_item_id, $newItemIds, true),
        );

        foreach ($stale as $assessment) {
            if (! $assessment->results()->exists()) {
                $assessment->delete();
            }
        }

        foreach ($book->items as $item) {
            $values = [
                'type' => $item->type,
                'name' => $item->name,
                'max_score' => $item->max_score,
                'weight' => $item->weight,
                'conducted_on' => $item->due_on,
            ];

            /** @var ?Assessment $assessment */
            $assessment = $existing->get($item->id);

            if ($assessment === null) {
                $assignment->assessments()->create([...$values, 'continuous_assessment_item_id' => $item->id]);
            } else {
                $assessment->fill($values);

                if ($assessment->isDirty()) {
                    $assessment->save();
                }
            }
        }
    }
}
