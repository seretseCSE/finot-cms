<?php

namespace App\Actions;

use App\Enums\EnrollmentStatus;
use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\SchoolProgram;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Services\Analytics\Analytics;
use App\Services\ConcessionSuggestionService;
use App\Services\EnrollmentGate;
use App\Support\GradeOffering;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Enrolls a student for an academic year + program. The ENROLLMENT (not the
 * student) carries the school/branch — students are global persons (ADR-011),
 * so this is the moment a student becomes attached to a branch for a year.
 *
 * The section is optional at enrollment time: many schools register into a
 * grade first and assign sections later. With a section, branch integrity,
 * capacity and the section's grade level are enforced; without one, the grade
 * level is required directly and the school/branch derive from the year.
 * Always enforces one LIVE (pending/active) enrollment per (student, year,
 * program) — dual enrollment across programs is allowed.
 *
 * Applicable registration fees are auto-invoiced here, and they decide the
 * starting status: `pending` until settled (see EnrollmentGate), `active`
 * when none apply.
 */
class EnrollStudentAction
{
    public function __construct(
        private readonly GenerateInvoicesAction $generateInvoices,
        private readonly EnrollmentGate $gate,
        private readonly ConcessionSuggestionService $concessionSuggestions,
    ) {}

    /**
     * @param  array{
     *     academic_year_id: int,
     *     section_id?: ?int,
     *     grade_level_id?: ?int,
     *     school_program_id?: ?int,
     *     previous_school_id?: ?int,
     *     enrolled_on?: ?string,
     * }  $data
     */
    public function execute(Student $student, array $data): StudentEnrollment
    {
        return DB::transaction(function () use ($student, $data): StudentEnrollment {
            $year = AcademicYear::findOrFail($data['academic_year_id']);
            $section = ! empty($data['section_id']) ? Section::findOrFail($data['section_id']) : null;

            if ($section !== null && $year->branch_id !== $section->branch_id) {
                throw ValidationException::withMessages([
                    'section_id' => ['The section and academic year must belong to the same branch.'],
                ]);
            }

            if ($section === null && empty($data['grade_level_id'])) {
                throw ValidationException::withMessages([
                    'grade_level_id' => ['A grade level is required when no section is chosen.'],
                ]);
            }

            $gradeLevelId = $section !== null
                ? $section->grade_level_id
                : GradeLevel::findOrFail($data['grade_level_id'])->id;

            $branch = $section?->branch ?? $year->branch;

            $program = ! empty($data['school_program_id'])
                ? SchoolProgram::where('branch_id', $branch->id)->findOrFail($data['school_program_id'])
                : SchoolProgram::defaultFor($branch);

            // The branch's grade × program offering gates every enrollment
            // (manual, registration, transfers, promotion rollover).
            GradeOffering::assertOffered($branch, $gradeLevelId, $program);

            $alreadyEnrolled = $student->enrollments()
                ->where('academic_year_id', $year->id)
                ->where('school_program_id', $program->id)
                ->live()
                ->exists();

            if ($alreadyEnrolled) {
                throw ValidationException::withMessages([
                    'academic_year_id' => ['This student already has an active enrollment for this academic year and program.'],
                ]);
            }

            if ($section !== null && $section->capacity !== null && $this->liveCount($section, $year) >= $section->capacity) {
                throw ValidationException::withMessages([
                    'section_id' => ['This section is full.'],
                ]);
            }

            $enrollment = $student->enrollments()->create([
                'school_id' => $branch->school_id,
                'branch_id' => $branch->id,
                'academic_year_id' => $year->id,
                'school_program_id' => $program->id,
                'section_id' => $section?->id,
                'grade_level_id' => $gradeLevelId,
                'previous_school_id' => $data['previous_school_id'] ?? null,
                'status' => EnrollmentStatus::Pending->value,
                'enrolled_on' => $data['enrolled_on'] ?? now()->toDateString(),
            ]);

            // Bill every applicable registration fee (idempotent), then let the
            // gate decide: unsettled registration invoice ⇒ stays pending.
            foreach ($this->gate->applicableRegistrationFees($branch->id, $year->id, $gradeLevelId) as $fee) {
                $this->generateInvoices->executeForEnrollment($fee, $enrollment);
            }

            $enrollment->update(['status' => $this->gate->initialStatus($enrollment)]);

            // School concession policy (sibling / employee-child) may file
            // pending suggestions for the finance review queue.
            $this->concessionSuggestions->evaluate($student, $enrollment);

            Analytics::capture(Auth::user(), 'student.enrolled', [
                'enrollment_id' => $enrollment->id,
                'academic_year_id' => $year->id,
                'grade_level_id' => $gradeLevelId,
                'status' => $enrollment->status,
            ], $branch->school_id, $branch->id);

            return $enrollment;
        });
    }

    private function liveCount(Section $section, AcademicYear $year): int
    {
        return StudentEnrollment::query()
            ->where('section_id', $section->id)
            ->where('academic_year_id', $year->id)
            ->live()
            ->count();
    }
}
