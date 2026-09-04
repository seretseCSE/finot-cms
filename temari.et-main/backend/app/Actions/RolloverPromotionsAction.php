<?php

namespace App\Actions;

use App\Enums\EnrollmentStatus;
use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\StudentEnrollment;
use App\Models\StudentPromotion;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Executes confirmed promotion-board decisions into the next academic year —
 * PER STUDENT, each in its own transaction, so the rollover is re-runnable
 * and partial-safe (the PowerSchool all-or-nothing EOY dread, avoided).
 * Promoted students land one grade up, repeaters stay; both get a `pending`/
 * `active` enrollment in the target year (EnrollStudentAction applies the
 * registration-fee gate and bills the fee). Sections map by same letter when
 * one exists with room; otherwise the section pool (§section assignment)
 * picks them up later.
 */
class RolloverPromotionsAction
{
    public function __construct(private readonly EnrollStudentAction $enroll)
    {
    }

    /**
     * @return array{executed: int, skipped: int, errors: list<array{enrollment_id: int, student: string, message: string}>}
     */
    public function execute(AcademicYear $fromYear, AcademicYear $toYear, User $actor, ?int $gradeLevelId = null): array
    {
        if ($toYear->branch_id !== $fromYear->branch_id) {
            throw ValidationException::withMessages([
                'to_academic_year_id' => ['The target year must belong to the same branch.'],
            ]);
        }

        if ($toYear->id === $fromYear->id) {
            throw ValidationException::withMessages([
                'to_academic_year_id' => ['The target year must differ from the source year.'],
            ]);
        }

        $decisions = StudentPromotion::query()
            ->where('academic_year_id', $fromYear->id)
            ->whereNull('executed_at')
            ->when($gradeLevelId !== null, fn ($q) => $q->where('from_grade_level_id', $gradeLevelId))
            ->with(['fromEnrollment.section', 'fromEnrollment.gradeLevel', 'student:id,first_name,father_name'])
            ->get();

        $executed = 0;
        $errors = [];

        foreach ($decisions as $decision) {
            try {
                DB::transaction(fn () => $this->executeOne($decision, $toYear, $actor));
                $executed++;
            } catch (ValidationException $e) {
                $errors[] = [
                    'enrollment_id' => $decision->from_enrollment_id,
                    'student' => trim("{$decision->student->first_name} {$decision->student->father_name}"),
                    'message' => collect($e->errors())->flatten()->first() ?? 'Validation failed.',
                ];
            } catch (Throwable $e) {
                $errors[] = [
                    'enrollment_id' => $decision->from_enrollment_id,
                    'student' => trim("{$decision->student->first_name} {$decision->student->father_name}"),
                    'message' => 'Unexpected error — this student was skipped.',
                ];
                report($e);
            }
        }

        ActivityLogger::log(
            actor: $actor,
            action: 'promotion.rollover',
            subject: $fromYear,
            properties: ['to_year' => $toYear->name, 'executed' => $executed, 'errors' => count($errors)],
            schoolId: $fromYear->school_id,
            branchId: $fromYear->branch_id,
        );

        return ['executed' => $executed, 'skipped' => count($errors), 'errors' => $errors];
    }

    private function executeOne(StudentPromotion $decision, AcademicYear $toYear, User $actor): void
    {
        $from = $decision->fromEnrollment;

        // Idempotency guard: only live enrollments can be rolled.
        if (! in_array($from->status, [EnrollmentStatus::Pending, EnrollmentStatus::Active], true)) {
            throw ValidationException::withMessages([
                'enrollment' => ['The source enrollment is no longer live.'],
            ]);
        }

        $toEnrollment = null;
        $toGrade = null;

        if ($decision->decision->continues()) {
            $toGrade = $decision->decision->value === 'promoted'
                ? $this->nextGrade($from->gradeLevel)
                : $from->gradeLevel;

            $section = $this->matchingSection($from, $toGrade, $toYear);

            $toEnrollment = $this->enroll->execute($decision->student, [
                'academic_year_id' => $toYear->id,
                'section_id' => $section?->id,
                'grade_level_id' => $toGrade->id,
                'school_program_id' => $from->school_program_id,
            ]);
        }

        $from->update([
            'status' => $decision->decision->enrollmentStatus(),
            'exited_on' => now()->toDateString(),
        ]);

        $decision->update([
            'to_enrollment_id' => $toEnrollment?->id,
            'to_grade_level_id' => $toGrade?->id,
            'to_branch_id' => $toEnrollment?->branch_id,
            'executed_at' => now(),
        ]);
    }

    private function nextGrade(GradeLevel $grade): GradeLevel
    {
        $next = GradeLevel::query()
            ->where('sort_order', '>', $grade->sort_order)
            ->orderBy('sort_order')
            ->first();

        if ($next === null) {
            throw ValidationException::withMessages([
                'decision' => ["There is no grade above {$grade->name} — mark this student as graduated instead."],
            ]);
        }

        return $next;
    }

    /**
     * Same-letter section in the target grade with a free seat (3A → 4A),
     * else null — the section-assignment board distributes the pool later.
     */
    private function matchingSection(StudentEnrollment $from, GradeLevel $toGrade, AcademicYear $toYear): ?Section
    {
        $letter = $from->section?->name;

        if ($letter === null) {
            return null;
        }

        $section = Section::query()
            ->where('branch_id', $from->branch_id)
            ->where('grade_level_id', $toGrade->id)
            ->where('name', $letter)
            ->where('is_active', true)
            ->first();

        if ($section === null) {
            return null;
        }

        if ($section->capacity !== null) {
            $taken = $section->enrollments()
                ->where('academic_year_id', $toYear->id)
                ->live()
                ->count();

            if ($taken >= $section->capacity) {
                return null;
            }
        }

        return $section;
    }
}
