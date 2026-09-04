<?php

namespace App\Services\Academics;

use App\Models\Assessment;
use App\Models\StudentEnrollment;
use App\Models\StudentTermResult;
use App\Models\SubjectOffering;
use App\Models\Term;
use Illuminate\Support\Collection;

class AcademicReportService
{
    public function __construct(private AssessmentScoreService $scores)
    {
    }

    /**
     * Marklist-style report for a term (assessment columns + subject totals).
     *
     * @return array{term: Term, offerings: Collection, rows: list<array>}
     */
    public function marklistReport(Term $term, ?int $classId = null, ?int $subjectId = null): array
    {
        $offerings = SubjectOffering::query()
            ->with(['subject', 'assessments' => fn ($q) => $q->orderBy('sort_order'), 'assessments.scores', 'class'])
            ->where('term_id', $term->id)
            ->when($classId, fn ($q) => $q->where(function ($inner) use ($classId) {
                $inner->whereNull('class_id')->orWhere('class_id', $classId);
            }))
            ->when($subjectId, fn ($q) => $q->where('subject_id', $subjectId))
            ->get();

        $members = StudentEnrollment::query()
            ->with('member')
            ->where('status', 'Enrolled')
            ->whereNull('removed_at')
            ->when($term->batch_year_id, fn ($q) => $q->where('batch_year_id', $term->batch_year_id))
            ->when($classId, fn ($q) => $q->where('class_id', $classId))
            ->get();

        $rows = [];
        foreach ($members as $enrollment) {
            $cells = [];
            foreach ($offerings as $offering) {
                if ($offering->class_id && (int) $offering->class_id !== (int) $enrollment->class_id) {
                    continue;
                }

                foreach ($offering->assessments as $assessment) {
                    $score = $assessment->scores->firstWhere('member_id', $enrollment->member_id);
                    $cells['a_'.$assessment->id] = $score?->is_absent ? 'Abs' : $score?->score;
                }
                $cells['s_'.$offering->subject_id] = $this->scores->subjectTotal($offering, (int) $enrollment->member_id);
            }

            $rows[] = [
                'member_id' => $enrollment->member_id,
                'name' => $enrollment->member?->full_name,
                'code' => $enrollment->member?->member_code,
                'class_id' => $enrollment->class_id,
                'cells' => $cells,
            ];
        }

        return [
            'term' => $term,
            'offerings' => $offerings,
            'rows' => $rows,
        ];
    }

    /**
     * Roster report from latest computed snapshot (fallback to live totals).
     *
     * @return array{subjects: list<array{id:int,name:string}>, rows: list<array>}
     */
    public function rosterReport(Term $term, ?int $batchYearId = null, ?int $classId = null): array
    {
        $batchYearId = $batchYearId ?: $term->batch_year_id;

        $results = StudentTermResult::query()
            ->with('member')
            ->where('term_id', $term->id)
            ->when($batchYearId, fn ($q) => $q->where('batch_year_id', $batchYearId))
            ->when($classId, fn ($q) => $q->where('class_id', $classId))
            ->orderBy('rank')
            ->get();

        if ($results->isEmpty()) {
            return ['subjects' => [], 'rows' => [], 'needs_compute' => true];
        }

        $subjects = [];
        foreach ($results as $result) {
            foreach ($result->breakdown ?? [] as $b) {
                $subjects[(int) $b['subject_id']] = [
                    'id' => (int) $b['subject_id'],
                    'name' => $b['subject_name'] ?? ('Subject '.$b['subject_id']),
                ];
            }
        }

        $rows = $results->map(function (StudentTermResult $result) {
            $bySubject = [];
            foreach ($result->breakdown ?? [] as $b) {
                $bySubject[(int) $b['subject_id']] = $b;
            }

            return [
                'member_id' => $result->member_id,
                'name' => $result->member?->full_name,
                'code' => $result->member?->member_code,
                'subjects' => $bySubject,
                'total' => $result->total,
                'average' => $result->average,
                'rank' => $result->rank,
                'rank_of' => $result->rank_of,
                'computed_at' => $result->computed_at?->toDateTimeString(),
            ];
        })->all();

        return [
            'subjects' => array_values($subjects),
            'rows' => $rows,
            'needs_compute' => false,
        ];
    }
}
