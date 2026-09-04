<?php

namespace App\Actions;

use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Applies a reviewed section mapping in one transaction: every target section
 * must belong to the year's branch and the enrollment's grade, and no section
 * may end past its capacity. `null` section returns a student to the pool.
 */
class CommitSectionAssignmentsAction
{
    /**
     * @param  list<array{enrollment_id: int, section_id: ?int}>  $assignments
     * @return int enrollments updated
     */
    public function execute(AcademicYear $year, array $assignments, User $actor): int
    {
        return DB::transaction(function () use ($year, $assignments, $actor): int {
            $enrollments = StudentEnrollment::query()
                ->where('academic_year_id', $year->id)
                ->whereIn('id', collect($assignments)->pluck('enrollment_id'))
                ->live()
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $sections = Section::query()
                ->where('branch_id', $year->branch_id)
                ->whereIn('id', collect($assignments)->pluck('section_id')->filter()->unique())
                ->get()
                ->keyBy('id');

            $updated = 0;

            foreach ($assignments as $row) {
                $enrollment = $enrollments->get((int) $row['enrollment_id']);

                if ($enrollment === null) {
                    throw ValidationException::withMessages([
                        'assignments' => ["Enrollment {$row['enrollment_id']} is not a live enrollment of this year."],
                    ]);
                }

                $sectionId = $row['section_id'] ?? null;

                if ($sectionId !== null) {
                    $section = $sections->get((int) $sectionId);

                    if ($section === null) {
                        throw ValidationException::withMessages([
                            'assignments' => ["Section {$sectionId} does not belong to this branch."],
                        ]);
                    }

                    if ($section->grade_level_id !== $enrollment->grade_level_id) {
                        throw ValidationException::withMessages([
                            'assignments' => ["Section {$section->name} is a different grade than the student's enrollment."],
                        ]);
                    }
                }

                if ($enrollment->section_id !== ($sectionId !== null ? (int) $sectionId : null)) {
                    $enrollment->update(['section_id' => $sectionId]);
                    $updated++;
                }
            }

            // Capacity judged on the FINAL state, after all moves in the batch.
            foreach ($sections as $section) {
                if ($section->capacity === null) {
                    continue;
                }

                $count = StudentEnrollment::query()
                    ->where('academic_year_id', $year->id)
                    ->where('section_id', $section->id)
                    ->live()
                    ->count();

                if ($count > $section->capacity) {
                    throw ValidationException::withMessages([
                        'assignments' => ["Section {$section->name} would exceed its capacity ({$count}/{$section->capacity})."],
                    ]);
                }
            }

            ActivityLogger::log(
                actor: $actor,
                action: 'sections.assignments_committed',
                subject: $year,
                properties: ['updated' => $updated],
                schoolId: $year->school_id,
                branchId: $year->branch_id,
            );

            return $updated;
        });
    }
}
