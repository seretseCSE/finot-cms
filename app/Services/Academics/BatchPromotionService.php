<?php

namespace App\Services\Academics;

use App\Models\AcademicYear;
use App\Models\BatchYear;
use App\Models\ClassModel;
use App\Models\StudentEnrollment;
use App\Models\StudentTermResult;
use App\Models\SubjectCredit;
use App\Models\SubjectOffering;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class BatchPromotionService
{
    /**
     * Promote an enrolled student to the next program year in the same batch.
     */
    public function promote(
        StudentEnrollment $enrollment,
        int $targetClassId,
        ?User $actor = null,
        ?string $notes = null,
        ?int $academicYearId = null,
    ): StudentEnrollment {
        if ($enrollment->status !== 'Enrolled' || $enrollment->removed_at) {
            throw ValidationException::withMessages(['enrollment' => 'Only active enrollments can be promoted.']);
        }

        if (! $enrollment->batch_id || ! $enrollment->batch_year_id) {
            throw ValidationException::withMessages(['batch' => 'Enrollment is not linked to a batch year.']);
        }

        $currentYear = BatchYear::query()->findOrFail($enrollment->batch_year_id);
        $nextYear = BatchYear::query()
            ->where('batch_id', $enrollment->batch_id)
            ->where('program_year', $currentYear->program_year + 1)
            ->first();

        if (! $nextYear) {
            throw ValidationException::withMessages(['batch_year' => 'No next program year in this batch (tenure may be complete).']);
        }

        $targetClass = ClassModel::query()->findOrFail($targetClassId);
        $expectedProgramYear = (int) $currentYear->program_year + 1;
        if ((int) $targetClass->program_year !== $expectedProgramYear) {
            throw ValidationException::withMessages([
                'class' => "Target class must be program year {$expectedProgramYear}.",
            ]);
        }

        $yearId = $academicYearId ?? $this->resolveTargetAcademicYearId($enrollment);

        if ($this->hasActiveEnrollment($enrollment->member_id, $yearId, $targetClassId, $enrollment->id)) {
            throw ValidationException::withMessages([
                'enrollment' => 'Student already has an active enrollment in that class for the target year.',
            ]);
        }

        return DB::transaction(function () use ($enrollment, $nextYear, $targetClassId, $yearId, $actor) {
            $enrollment->update([
                'status' => 'Promoted',
                'completion_date' => now()->toDateString(),
                'completed_by' => $actor?->id,
            ]);

            $new = StudentEnrollment::query()->create([
                'member_id' => $enrollment->member_id,
                'class_id' => $targetClassId,
                'academic_year_id' => $yearId,
                'batch_id' => $enrollment->batch_id,
                'batch_year_id' => $nextYear->id,
                'enrolled_date' => now()->toDateString(),
                'status' => 'Enrolled',
                'enrolled_by' => $actor?->id,
            ]);

            $enrollment->update(['promoted_to_enrollment_id' => $new->id]);

            return $new;
        });
    }

    /**
     * Fail/transfer: move to another batch at the same program_year; keep passed subject credits.
     */
    public function failTransfer(
        StudentEnrollment $enrollment,
        int $targetBatchYearId,
        int $targetClassId,
        ?User $actor = null,
    ): StudentEnrollment {
        if ($enrollment->status !== 'Enrolled' || $enrollment->removed_at) {
            throw ValidationException::withMessages(['enrollment' => 'Only active enrollments can be transferred.']);
        }

        $sourceYear = BatchYear::query()->find($enrollment->batch_year_id);
        $targetYear = BatchYear::query()->with('batch')->findOrFail($targetBatchYearId);

        if ($sourceYear && (int) $sourceYear->program_year !== (int) $targetYear->program_year) {
            throw ValidationException::withMessages(['batch_year' => 'Target must be the same program year (e.g. Year 2 → Year 2).']);
        }

        if ((int) $targetYear->batch_id === (int) $enrollment->batch_id) {
            throw ValidationException::withMessages(['batch' => 'Choose a different batch.']);
        }

        $targetClass = ClassModel::query()->findOrFail($targetClassId);
        if ($sourceYear && (int) $targetClass->program_year !== (int) $sourceYear->program_year) {
            throw ValidationException::withMessages([
                'class' => 'Target class must stay at the same program year.',
            ]);
        }

        $yearId = $this->resolveTargetAcademicYearId($enrollment);
        if ($this->hasActiveEnrollment($enrollment->member_id, $yearId, $targetClassId, $enrollment->id)) {
            throw ValidationException::withMessages([
                'enrollment' => 'Student already has an active enrollment in that class for the target year.',
            ]);
        }

        return DB::transaction(function () use ($enrollment, $targetYear, $targetClassId, $yearId, $actor) {
            $this->capturePassedCredits($enrollment, $actor);

            $enrollment->update([
                'status' => 'Promoted',
                'completion_date' => now()->toDateString(),
                'completed_by' => $actor?->id,
            ]);

            $new = StudentEnrollment::query()->create([
                'member_id' => $enrollment->member_id,
                'class_id' => $targetClassId,
                'academic_year_id' => $yearId,
                'batch_id' => $targetYear->batch_id,
                'batch_year_id' => $targetYear->id,
                'enrolled_date' => now()->toDateString(),
                'status' => 'Enrolled',
                'enrolled_by' => $actor?->id,
            ]);

            $enrollment->update(['promoted_to_enrollment_id' => $new->id]);

            $targetSubjectIds = SubjectOffering::query()
                ->where('batch_year_id', $targetYear->id)
                ->pluck('subject_id');

            SubjectCredit::query()
                ->where('member_id', $enrollment->member_id)
                ->whereIn('subject_id', $targetSubjectIds)
                ->where('status', 'passed')
                ->update(['status' => 'transferred']);

            return $new;
        });
    }

    /**
     * @param  array<int, string|null>  $decisions  enrollment_id => pass|fail|null
     * @return array{passed: int, failed: int, skipped: int, errors: list<array{enrollment_id: int, name: string, message: string}>}
     */
    public function applyBoard(
        array $decisions,
        int $passTargetClassId,
        ?int $failTargetBatchYearId,
        ?int $failTargetClassId,
        ?User $actor = null,
    ): array {
        $passed = 0;
        $failed = 0;
        $skipped = 0;
        $errors = [];

        $hasFailers = collect($decisions)->contains(fn ($d) => $d === 'fail');
        if ($hasFailers && (! $failTargetBatchYearId || ! $failTargetClassId)) {
            throw ValidationException::withMessages([
                'fail_target' => 'Choose a target batch and class for students who fail.',
            ]);
        }

        foreach ($decisions as $enrollmentId => $decision) {
            if (! in_array($decision, ['pass', 'fail'], true)) {
                $skipped++;

                continue;
            }

            /** @var StudentEnrollment|null $enrollment */
            $enrollment = StudentEnrollment::query()
                ->with('member')
                ->find($enrollmentId);

            if (! $enrollment || $enrollment->status !== 'Enrolled' || $enrollment->removed_at) {
                $skipped++;

                continue;
            }

            $name = $enrollment->student_full_name;

            try {
                DB::transaction(function () use ($decision, $enrollment, $passTargetClassId, $failTargetBatchYearId, $failTargetClassId, $actor): void {
                    if ($decision === 'pass') {
                        $this->promote(
                            $enrollment->fresh(),
                            $passTargetClassId,
                            $actor,
                            null,
                            $this->resolveTargetAcademicYearId($enrollment),
                        );
                    } else {
                        $this->failTransfer(
                            $enrollment->fresh(),
                            (int) $failTargetBatchYearId,
                            (int) $failTargetClassId,
                            $actor,
                        );
                    }
                });

                if ($decision === 'pass') {
                    $passed++;
                } else {
                    $failed++;
                }
            } catch (ValidationException $e) {
                $errors[] = [
                    'enrollment_id' => (int) $enrollmentId,
                    'name' => $name,
                    'message' => collect($e->errors())->flatten()->first() ?? 'Validation failed.',
                ];
            } catch (Throwable $e) {
                $errors[] = [
                    'enrollment_id' => (int) $enrollmentId,
                    'name' => $name,
                    'message' => 'Unexpected error — student skipped.',
                ];
                report($e);
            }
        }

        return [
            'passed' => $passed,
            'failed' => $failed,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    public function resolveTargetAcademicYearId(StudentEnrollment $enrollment): int
    {
        return AcademicYear::nextYear()?->id ?? (int) $enrollment->academic_year_id;
    }

    protected function hasActiveEnrollment(int $memberId, int $academicYearId, int $classId, ?int $ignoreEnrollmentId = null): bool
    {
        return StudentEnrollment::query()
            ->where('member_id', $memberId)
            ->where('academic_year_id', $academicYearId)
            ->where('class_id', $classId)
            ->where('status', 'Enrolled')
            ->whereNull('removed_at')
            ->when($ignoreEnrollmentId, fn ($q) => $q->whereKeyNot($ignoreEnrollmentId))
            ->exists();
    }

    protected function capturePassedCredits(StudentEnrollment $enrollment, ?User $actor): void
    {
        $passMark = (float) config('finot.promotion_pass_mark', 50);

        $results = StudentTermResult::query()
            ->where('member_id', $enrollment->member_id)
            ->when($enrollment->batch_year_id, fn ($q) => $q->where('batch_year_id', $enrollment->batch_year_id))
            ->get();

        foreach ($results as $result) {
            foreach ($result->breakdown ?? [] as $row) {
                $total = $row['total'] ?? null;
                $subjectId = $row['subject_id'] ?? null;
                if (! $subjectId || $total === null) {
                    continue;
                }

                if ((float) $total < $passMark) {
                    continue;
                }

                SubjectCredit::query()->updateOrCreate(
                    [
                        'member_id' => $enrollment->member_id,
                        'subject_id' => $subjectId,
                        'source_batch_year_id' => $enrollment->batch_year_id,
                    ],
                    [
                        'source_term_id' => $result->term_id,
                        'score' => $total,
                        'max_score' => 100,
                        'status' => 'passed',
                        'created_by' => $actor?->id,
                    ]
                );
            }
        }
    }
}
