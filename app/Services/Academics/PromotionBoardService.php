<?php

namespace App\Services\Academics;

use App\Models\ClassModel;
use App\Models\StudentEnrollment;
use App\Models\StudentTermResult;

class PromotionBoardService
{
    public function passMark(): float
    {
        return (float) config('finot.promotion_pass_mark', 50);
    }

    /**
     * @return array{
     *     rows: list<array{
     *         enrollment_id: int,
     *         member_id: int,
     *         name: string,
     *         code: string|null,
     *         average: float|null,
     *         suggestion: string|null,
     *     }>,
     *     pass_mark: float,
     *     next_class_options: array<int, string>,
     *     default_next_class_id: int|null,
     *     program_year: int|null,
     * }
     */
    public function build(int $academicYearId, int $batchId, int $classId): array
    {
        $enrollments = StudentEnrollment::query()
            ->with(['member', 'batchYear'])
            ->where('academic_year_id', $academicYearId)
            ->where('batch_id', $batchId)
            ->where('class_id', $classId)
            ->where('status', 'Enrolled')
            ->whereNull('removed_at')
            ->orderBy('id')
            ->get();

        $batchYearId = $enrollments->first()?->batch_year_id;
        $memberIds = $enrollments->pluck('member_id');

        $resultsByMember = StudentTermResult::query()
            ->whereIn('member_id', $memberIds)
            ->when($batchYearId, fn ($q) => $q->where('batch_year_id', $batchYearId))
            ->where('class_id', $classId)
            ->get()
            ->groupBy('member_id');

        $rows = [];
        foreach ($enrollments as $enrollment) {
            $termResults = $resultsByMember->get($enrollment->member_id, collect());
            $scored = $termResults->filter(fn (StudentTermResult $r) => $r->average !== null);
            $average = $scored->isEmpty()
                ? null
                : round((float) $scored->avg(fn (StudentTermResult $r) => (float) $r->average), 2);

            $rows[] = [
                'enrollment_id' => $enrollment->id,
                'member_id' => $enrollment->member_id,
                'name' => $enrollment->student_full_name,
                'code' => $enrollment->member?->member_code,
                'average' => $average,
                'suggestion' => $this->suggest($average),
            ];
        }

        $currentClass = ClassModel::query()->find($classId);
        $nextClassOptions = $this->nextClassOptions($currentClass);

        return [
            'rows' => $rows,
            'pass_mark' => $this->passMark(),
            'next_class_options' => $nextClassOptions,
            'default_next_class_id' => array_key_first($nextClassOptions) ?: null,
            'program_year' => $currentClass?->program_year,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function nextClassOptions(?ClassModel $currentClass): array
    {
        if (! $currentClass || $currentClass->program_year === null) {
            return ClassModel::query()
                ->active()
                ->orderBy('program_year')
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all();
        }

        return ClassModel::query()
            ->active()
            ->where('program_year', (int) $currentClass->program_year + 1)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, string> Classes at the same program year (for failers).
     */
    public function sameYearClassOptions(?int $programYear): array
    {
        if ($programYear === null) {
            return ClassModel::query()->active()->orderBy('name')->pluck('name', 'id')->all();
        }

        return ClassModel::query()
            ->active()
            ->where('program_year', $programYear)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function suggest(?float $average): ?string
    {
        if ($average === null) {
            return null;
        }

        return $average >= $this->passMark() ? 'pass' : 'fail';
    }

    /**
     * @param  list<array{suggestion: string|null}>  $rows
     * @return array<int, string>
     */
    public function decisionsFromSuggestions(array $rows): array
    {
        $decisions = [];
        foreach ($rows as $row) {
            $suggestion = $row['suggestion'] ?? null;
            if ($suggestion === 'pass' || $suggestion === 'fail') {
                $decisions[(int) $row['enrollment_id']] = $suggestion;
            }
        }

        return $decisions;
    }
}
