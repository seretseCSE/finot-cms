<?php

namespace App\Services\Academics;

use App\Models\StudentEnrollment;
use App\Models\StudentTermResult;
use App\Models\SubjectCredit;
use App\Models\SubjectOffering;
use App\Models\Term;
use App\Models\User;
use App\Support\Ranking;
use Illuminate\Support\Facades\DB;

class ComputeTermResultsService
{
    public function __construct(private AssessmentScoreService $scores)
    {
    }

    /**
     * Recompute student_term_results for a term (callable anytime by Head/Monitor).
     *
     * @return array{students: int, computed_at: string}
     */
    public function compute(Term $term, ?User $actor = null, ?int $classId = null): array
    {
        $offerings = SubjectOffering::query()
            ->with(['subject', 'assessments.scores', 'batchYear'])
            ->where('term_id', $term->id)
            ->when($classId, fn ($q) => $q->where(function ($inner) use ($classId) {
                $inner->whereNull('class_id')->orWhere('class_id', $classId);
            }))
            ->get();

        $memberIds = StudentEnrollment::query()
            ->where('status', 'Enrolled')
            ->whereNull('removed_at')
            ->when($term->batch_year_id, fn ($q) => $q->where('batch_year_id', $term->batch_year_id))
            ->when($classId, fn ($q) => $q->where('class_id', $classId))
            ->get(['id', 'member_id', 'class_id', 'batch_year_id']);

        $rows = [];

        foreach ($memberIds as $enrollment) {
            $breakdown = [];
            $subjectTotals = [];

            foreach ($offerings as $offering) {
                if ($offering->class_id && (int) $offering->class_id !== (int) $enrollment->class_id) {
                    continue;
                }

                $total = $this->scores->subjectTotal($offering, (int) $enrollment->member_id);
                if ($total === null) {
                    continue;
                }

                $subjectTotals[] = $total;
                $breakdown[] = [
                    'subject_id' => $offering->subject_id,
                    'subject_name' => $offering->subject?->name,
                    'total' => $total,
                    'transferred' => SubjectCredit::query()
                        ->where('member_id', $enrollment->member_id)
                        ->where('subject_id', $offering->subject_id)
                        ->whereIn('status', ['passed', 'transferred'])
                        ->exists(),
                ];
            }

            if ($subjectTotals === []) {
                continue;
            }

            $average = round(array_sum($subjectTotals) / count($subjectTotals), 2);
            $total = round(array_sum($subjectTotals), 2);

            $rows[] = [
                'member_id' => (int) $enrollment->member_id,
                'enrollment_id' => (int) $enrollment->id,
                'class_id' => $enrollment->class_id ? (int) $enrollment->class_id : null,
                'batch_year_id' => $enrollment->batch_year_id ? (int) $enrollment->batch_year_id : ($term->batch_year_id ? (int) $term->batch_year_id : null),
                'total' => $total,
                'average' => $average,
                'breakdown' => $breakdown,
            ];
        }

        // Per-subject ranks within cohort
        $subjectIds = collect($rows)->flatMap(fn ($r) => collect($r['breakdown'])->pluck('subject_id'))->unique()->values();
        foreach ($subjectIds as $subjectId) {
            $scored = [];
            foreach ($rows as $idx => $row) {
                foreach ($row['breakdown'] as $bIdx => $b) {
                    if ((int) $b['subject_id'] === (int) $subjectId) {
                        $scored[] = ['row' => $idx, 'b' => $bIdx, 'total' => (float) $b['total']];
                    }
                }
            }
            usort($scored, fn ($a, $b) => $b['total'] <=> $a['total']);
            $ranked = Ranking::competition(array_column($scored, 'total'));
            foreach ($scored as $i => $item) {
                $rows[$item['row']]['breakdown'][$item['b']]['rank'] = $ranked[$i];
                $rows[$item['row']]['breakdown'][$item['b']]['rank_of'] = count($scored);
            }
        }

        $averages = array_column($rows, 'average');
        $overallRanks = Ranking::competition($averages);
        $rankOf = count($rows);

        DB::transaction(function () use ($rows, $term, $actor, $overallRanks, $rankOf): void {
            foreach ($rows as $i => $row) {
                StudentTermResult::query()->updateOrCreate(
                    [
                        'member_id' => $row['member_id'],
                        'term_id' => $term->id,
                    ],
                    [
                        'batch_year_id' => $row['batch_year_id'],
                        'class_id' => $row['class_id'],
                        'enrollment_id' => $row['enrollment_id'],
                        'total' => $row['total'],
                        'average' => $row['average'],
                        'rank' => $overallRanks[$i] ?? null,
                        'rank_of' => $rankOf,
                        'breakdown' => $row['breakdown'],
                        'computed_at' => now(),
                        'computed_by' => $actor?->id,
                    ]
                );
            }
        });

        return [
            'students' => count($rows),
            'computed_at' => now()->toDateTimeString(),
        ];
    }
}
