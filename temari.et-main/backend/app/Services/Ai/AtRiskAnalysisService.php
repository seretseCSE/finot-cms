<?php

namespace App\Services\Ai;

use App\Models\AttendanceRecord;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\StudentTermResult;
use Illuminate\Support\Collection;

/**
 * The early-warning engine behind the AtRiskStudents tool and the weekly
 * leadership briefing. Pure reads, three cheap aggregate queries:
 *
 *  - academic: latest computed term average low (< 50) or dropped ≥ 10
 *    points vs the previous computed term;
 *  - attendance: ≥ 5 absences in the last 30 recorded days;
 *  - arrears (only when the caller may see fees): overdue balance > 0.
 *
 * A student appears once with every reason that fired; ordering is most
 * reasons first, then worst average. This is decision SUPPORT for helping
 * students — presentation must never read as a blame list.
 */
class AtRiskAnalysisService
{
    private const LOW_AVERAGE = 50.0;

    private const DROP_POINTS = 10.0;

    private const ABSENCE_COUNT = 5;

    /**
     * @param  Collection<int, int>  $branchIds
     * @return array{thresholds: array<string, mixed>, students: list<array<string, mixed>>}
     */
    public function analyze(Collection $branchIds, bool $includeArrears, int $limit = 15): array
    {
        $latestTermId = StudentTermResult::query()->whereIn('branch_id', $branchIds)->max('term_id');

        $reasons = [];

        if ($latestTermId !== null) {
            $current = StudentTermResult::query()
                ->whereIn('branch_id', $branchIds)
                ->where('term_id', $latestTermId)
                ->whereNotNull('average')
                ->get(['student_id', 'average', 'section_id', 'term_id'])
                ->keyBy('student_id');

            $previous = StudentTermResult::query()
                ->whereIn('student_id', $current->keys())
                ->where('term_id', '<', $latestTermId)
                ->whereNotNull('average')
                ->orderByDesc('term_id')
                ->get(['student_id', 'average', 'term_id'])
                ->unique('student_id')
                ->keyBy('student_id');

            foreach ($current as $studentId => $row) {
                $average = (float) $row->average;

                if ($average < self::LOW_AVERAGE) {
                    $reasons[$studentId][] = 'term average '.$average.' (below '.self::LOW_AVERAGE.')';
                }

                $prior = $previous->get($studentId);
                if ($prior !== null && ((float) $prior->average - $average) >= self::DROP_POINTS) {
                    $reasons[$studentId][] = 'average dropped '.round((float) $prior->average - $average, 1).' points since the previous term';
                }
            }
        }

        $absences = AttendanceRecord::query()
            ->whereIn('branch_id', $branchIds)
            ->where('status', 'absent')
            ->where('date', '>=', now()->subDays(30)->toDateString())
            ->selectRaw('student_id, count(*) as absences')
            ->groupBy('student_id')
            ->havingRaw('count(*) >= ?', [self::ABSENCE_COUNT])
            ->pluck('absences', 'student_id');

        foreach ($absences as $studentId => $count) {
            $reasons[$studentId][] = $count.' absences in the last 30 days';
        }

        if ($includeArrears) {
            $arrears = Invoice::query()
                ->whereIn('branch_id', $branchIds)
                ->whereIn('status', ['unpaid', 'partial'])
                ->whereNotNull('due_date')
                ->where('due_date', '<', now()->toDateString())
                ->selectRaw('student_id, sum(amount - amount_paid) as balance')
                ->groupBy('student_id')
                ->havingRaw('sum(amount - amount_paid) > 0')
                ->pluck('balance', 'student_id');

            foreach ($arrears as $studentId => $balance) {
                $reasons[$studentId][] = 'overdue fees of '.round((float) $balance, 2).' ETB';
            }
        }

        if ($reasons === []) {
            return ['thresholds' => $this->thresholds($includeArrears), 'students' => []];
        }

        $ranked = collect($reasons)
            ->map(fn (array $list, int $studentId): array => ['student_id' => $studentId, 'reasons' => $list])
            ->sortByDesc(fn (array $row) => count($row['reasons']))
            ->take($limit)
            ->values();

        $students = Student::query()
            ->whereIn('id', $ranked->pluck('student_id'))
            ->with(['currentEnrollment.section:id,name', 'currentEnrollment.gradeLevel:id,name'])
            ->get()
            ->keyBy('id');

        return [
            'thresholds' => $this->thresholds($includeArrears),
            'students' => $ranked->map(function (array $row) use ($students): array {
                $student = $students->get($row['student_id']);

                return [
                    'student' => $student?->full_name,
                    'link' => '/students/'.$row['student_id'],
                    'grade' => $student?->currentEnrollment?->gradeLevel?->name,
                    'section' => $student?->currentEnrollment?->section?->name,
                    'reasons' => $row['reasons'],
                ];
            })->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function thresholds(bool $includeArrears): array
    {
        return [
            'low_average' => self::LOW_AVERAGE,
            'drop_points' => self::DROP_POINTS,
            'absences_in_30_days' => self::ABSENCE_COUNT,
            'arrears_included' => $includeArrears,
        ];
    }
}
