<?php

namespace App\Actions;

use App\Enums\EnrollmentStatus;
use App\Models\GradeLevel;
use App\Models\SchoolProgram;
use App\Models\Section;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\GradeOffering;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Corrects a LIVE enrollment in place — the "assigned to the wrong grade"
 * fix. This edits the existing row (same year, same branch, same fee trail)
 * rather than withdraw + re-enroll, so IDs, invoices and guardian links all
 * survive the correction.
 *
 * Guard rails:
 *  - only pending/active enrollments — completed/withdrawn/transferred rows
 *    are history and never rewritten;
 *  - a grade change is refused once the enrollment has FROZEN term results
 *    (student_term_results) — report cards were issued for the old grade;
 *  - the branch's grade × program offering gates the new grade like every
 *    enrollment path;
 *  - a section must match the (new) grade and have capacity; when the grade
 *    changes and no matching section is named, the section is CLEARED for
 *    reassignment (an old-grade section on a new-grade enrollment is exactly
 *    the corruption School-X suffered).
 */
class UpdateEnrollmentAction
{
    /**
     * @param  array{
     *     grade_level_id?: int,
     *     section_id?: ?int,
     *     school_program_id?: ?int,
     *     enrolled_on?: ?string,
     * }  $data
     */
    public function execute(StudentEnrollment $enrollment, array $data, User $actor): StudentEnrollment
    {
        return DB::transaction(function () use ($enrollment, $data, $actor): StudentEnrollment {
            if (! in_array($enrollment->status, [EnrollmentStatus::Pending, EnrollmentStatus::Active], true)) {
                throw ValidationException::withMessages([
                    'enrollment' => ['Only pending or active enrollments can be edited — this one is closed history.'],
                ]);
            }

            $gradeLevelId = isset($data['grade_level_id'])
                ? GradeLevel::findOrFail($data['grade_level_id'])->id
                : $enrollment->grade_level_id;
            $gradeChanged = $gradeLevelId !== $enrollment->grade_level_id;

            if ($gradeChanged && $enrollment->termResults()->exists()) {
                throw ValidationException::withMessages([
                    'grade_level_id' => ['This enrollment already has frozen semester results for its current grade, so the grade can no longer be changed.'],
                ]);
            }

            $branch = $enrollment->branch;

            $program = ! empty($data['school_program_id'])
                ? SchoolProgram::where('branch_id', $branch->id)->findOrFail($data['school_program_id'])
                : $enrollment->schoolProgram;

            if ($program !== null) {
                GradeOffering::assertOffered($branch, $gradeLevelId, $program);
            }

            if ($program !== null && $program->id !== $enrollment->school_program_id) {
                $duplicate = StudentEnrollment::query()
                    ->where('student_id', $enrollment->student_id)
                    ->where('academic_year_id', $enrollment->academic_year_id)
                    ->where('school_program_id', $program->id)
                    ->whereKeyNot($enrollment->id)
                    ->live()
                    ->exists();

                if ($duplicate) {
                    throw ValidationException::withMessages([
                        'school_program_id' => ['This student already has a live enrollment for this academic year and program.'],
                    ]);
                }
            }

            $section = $this->resolveSection($enrollment, $data, $gradeLevelId, $gradeChanged);

            $before = $enrollment->only(['grade_level_id', 'section_id', 'school_program_id', 'enrolled_on']);

            $enrollment->update([
                'grade_level_id' => $gradeLevelId,
                'section_id' => $section?->id,
                'school_program_id' => $program?->id ?? $enrollment->school_program_id,
                'enrolled_on' => $data['enrolled_on'] ?? $enrollment->enrolled_on,
            ]);

            ActivityLogger::log(
                $actor,
                'enrollment.updated',
                $enrollment,
                ['before' => $before, 'after' => $enrollment->only(array_keys($before))],
                $enrollment->school_id,
                $enrollment->branch_id,
            );

            return $enrollment;
        });
    }

    /**
     * The section that should end up on the corrected row. Explicit input
     * wins; otherwise a grade change clears a now-mismatched section.
     */
    private function resolveSection(
        StudentEnrollment $enrollment,
        array $data,
        int $gradeLevelId,
        bool $gradeChanged,
    ): ?Section {
        if (! array_key_exists('section_id', $data)) {
            if ($gradeChanged) {
                return null;
            }

            return $enrollment->section_id !== null ? Section::find($enrollment->section_id) : null;
        }

        if (empty($data['section_id'])) {
            return null;
        }

        $section = Section::findOrFail($data['section_id']);

        if ($section->branch_id !== $enrollment->branch_id) {
            throw ValidationException::withMessages([
                'section_id' => ['The section must belong to the enrollment\'s branch.'],
            ]);
        }

        if ($section->grade_level_id !== $gradeLevelId) {
            throw ValidationException::withMessages([
                'section_id' => ['The section does not belong to the selected grade level.'],
            ]);
        }

        if ($section->id !== $enrollment->section_id && $section->capacity !== null) {
            $liveCount = StudentEnrollment::query()
                ->where('section_id', $section->id)
                ->where('academic_year_id', $enrollment->academic_year_id)
                ->whereKeyNot($enrollment->id)
                ->live()
                ->count();

            if ($liveCount >= $section->capacity) {
                throw ValidationException::withMessages([
                    'section_id' => ['This section is full.'],
                ]);
            }
        }

        return $section;
    }
}
