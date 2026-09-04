<?php

namespace App\Actions;

use App\Enums\PromotionDecision;
use App\Models\AcademicYear;
use App\Models\StudentEnrollment;
use App\Models\StudentPromotion;
use App\Models\StudentTermResult;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Upserts promotion-board decisions (one row per source enrollment). Nothing
 * moves yet — execution is RolloverPromotionsAction's job. Passing a null
 * decision clears an un-executed row; executed rows are immutable history.
 */
class SavePromotionDecisionsAction
{
    /**
     * @param  list<array{enrollment_id: int, decision: ?string, notes?: ?string}>  $decisions
     * @return int rows saved
     */
    public function execute(AcademicYear $year, array $decisions, User $decider): int
    {
        return DB::transaction(function () use ($year, $decisions, $decider): int {
            $enrollments = StudentEnrollment::query()
                ->where('academic_year_id', $year->id)
                ->whereIn('id', collect($decisions)->pluck('enrollment_id'))
                ->live()
                ->get()
                ->keyBy('id');

            $averages = StudentTermResult::query()
                ->whereIn('student_enrollment_id', $enrollments->keys())
                ->whereNotNull('average')
                ->selectRaw('student_enrollment_id, AVG(average) AS annual')
                ->groupBy('student_enrollment_id')
                ->pluck('annual', 'student_enrollment_id');

            $saved = 0;

            foreach ($decisions as $row) {
                $enrollment = $enrollments->get((int) $row['enrollment_id']);

                if ($enrollment === null) {
                    throw ValidationException::withMessages([
                        'decisions' => ["Enrollment {$row['enrollment_id']} is not a live enrollment of this academic year."],
                    ]);
                }

                $existing = StudentPromotion::query()
                    ->where('from_enrollment_id', $enrollment->id)
                    ->first();

                if ($existing?->executed_at !== null) {
                    throw ValidationException::withMessages([
                        'decisions' => ["The decision for enrollment {$enrollment->id} was already executed."],
                    ]);
                }

                if (empty($row['decision'])) {
                    $existing?->delete();

                    continue;
                }

                $decision = PromotionDecision::from($row['decision']);

                if ($decision === PromotionDecision::Transferred) {
                    throw ValidationException::withMessages([
                        'decisions' => ['Transfers are recorded through the transfer workflow, not the promotion board.'],
                    ]);
                }

                StudentPromotion::updateOrCreate(
                    ['from_enrollment_id' => $enrollment->id],
                    [
                        'student_id' => $enrollment->student_id,
                        'academic_year_id' => $year->id,
                        'from_grade_level_id' => $enrollment->grade_level_id,
                        'from_branch_id' => $enrollment->branch_id,
                        'decision' => $decision,
                        'average' => isset($averages[$enrollment->id]) ? round((float) $averages[$enrollment->id], 2) : null,
                        'decided_by' => $decider->id,
                        'decided_at' => now(),
                        'notes' => $row['notes'] ?? null,
                    ],
                );
                $saved++;
            }

            return $saved;
        });
    }
}
