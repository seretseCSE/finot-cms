<?php

namespace App\Services\Reports;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\StudentTermResult;
use App\Models\Term;
use App\Support\Ranking;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The classic Ethiopian roster sheets, read from the FROZEN
 * student_term_results rows so every number matches the issued report cards.
 * Term roster: students × subjects for one semester/quarter with total,
 * average and section rank. Year roster: the same students across every term
 * of the year with semester sub-averages (quarters grouped by terms.semester)
 * and a read-time yearly average + rank (mean of term averages — the same
 * convention as the transcript). Authorization is the caller's job.
 */
class RosterReportService
{
    /**
     * @param  list<int>|null  $allowedSectionIds  null = unrestricted (supervisory)
     * @return array{data: array<string, mixed>, meta: array<string, mixed>}
     */
    public function termRoster(Term $term, ?int $sectionId, ?int $gradeLevelId, ?array $allowedSectionIds): array
    {
        $results = $this->resultsFor($allowedSectionIds, $sectionId, $gradeLevelId)
            ->where('term_id', $term->id)
            ->limit(4000)
            ->get();

        $sorted = $this->sortForSheet($results);

        return [
            'data' => [
                'columns' => $this->subjectColumns($results),
                'rows' => $sorted->map(fn (StudentTermResult $r): array => [
                    ...collect($this->studentCells($r))->except('grade_sort')->all(),
                    'student_enrollment_id' => $r->student_enrollment_id,
                    'scores' => $this->scoreMap($r),
                    'total' => $r->total !== null ? (float) $r->total : null,
                    'average' => $r->average !== null ? (float) $r->average : null,
                    'rank' => $r->rank,
                    'rank_of' => $r->rank_of,
                    'absence_days' => $r->absence_days,
                    'conduct' => $r->conduct,
                    'comment' => $r->comment,
                    'skills' => $r->skills,
                ])->values()->all(),
            ],
            'meta' => [
                'term' => [
                    'id' => $term->id,
                    'name' => $term->name,
                    'status' => $term->status->value,
                    'is_quarter' => $term->is_quarter,
                    'semester' => $term->semester,
                ],
                'students' => $results->count(),
                'computed_at' => $results->max('computed_at')?->toIso8601String(),
                'report_card' => $this->reportCardMeta($term->branch),
            ],
        ];
    }

    /**
     * @param  list<int>|null  $allowedSectionIds  null = unrestricted (supervisory)
     * @return array{data: array<string, mixed>, meta: array<string, mixed>}
     */
    public function yearRoster(AcademicYear $year, ?int $sectionId, ?int $gradeLevelId, ?array $allowedSectionIds): array
    {
        $terms = Term::query()
            ->where('academic_year_id', $year->id)
            ->orderBy('sequence')
            ->get(['id', 'name', 'sequence', 'is_quarter', 'semester', 'status']);

        $results = $this->resultsFor($allowedSectionIds, $sectionId, $gradeLevelId)
            ->where('academic_year_id', $year->id)
            ->limit(8000)
            ->get();

        // Quarter terms grouped by their semester tag — the yearly sheet only
        // shows semester sub-averages when the school actually filed them.
        $semesterGroups = $terms
            ->filter(fn (Term $t) => $t->is_quarter && $t->semester !== null)
            ->groupBy('semester');

        $students = $results
            ->groupBy('student_id')
            ->map(function (Collection $rows) use ($terms, $semesterGroups): array {
                // The latest term the student has a row for anchors their
                // placement (section moves mid-year land on the newest one).
                $byTerm = $rows->keyBy('term_id');
                $latest = $terms->reverse()->first(fn (Term $t) => $byTerm->has($t->id));
                $latestRow = $byTerm[$latest->id];

                $termLines = $terms
                    ->filter(fn (Term $t) => $byTerm->has($t->id))
                    ->map(fn (Term $t): array => [
                        'term_id' => $t->id,
                        'student_enrollment_id' => $byTerm[$t->id]->student_enrollment_id,
                        'scores' => $this->scoreMap($byTerm[$t->id]),
                        'total' => $byTerm[$t->id]->total !== null ? (float) $byTerm[$t->id]->total : null,
                        'average' => $byTerm[$t->id]->average !== null ? (float) $byTerm[$t->id]->average : null,
                        'rank' => $byTerm[$t->id]->rank,
                        'rank_of' => $byTerm[$t->id]->rank_of,
                        'absence_days' => $byTerm[$t->id]->absence_days,
                        'conduct' => $byTerm[$t->id]->conduct,
                        'comment' => $byTerm[$t->id]->comment,
                        'skills' => $byTerm[$t->id]->skills,
                    ])
                    ->values()
                    ->all();

                $semesters = $semesterGroups
                    ->map(function (Collection $group, int $semester) use ($byTerm): ?array {
                        $averages = $group
                            ->map(fn (Term $t) => $byTerm->get($t->id)?->average)
                            ->filter(fn ($avg) => $avg !== null)
                            ->map(fn ($avg) => (float) $avg);

                        return $averages->isEmpty() ? null : [
                            'semester' => $semester,
                            'average' => round((float) $averages->avg(), 2),
                        ];
                    })
                    ->filter()
                    ->sortBy('semester')
                    ->values()
                    ->all();

                // Yearly average = mean of term averages (transcript convention).
                $termAverages = collect($termLines)->pluck('average')->filter(fn ($avg) => $avg !== null);

                return [
                    ...$this->studentCells($latestRow),
                    'terms' => $termLines,
                    'semesters' => $semesters,
                    'year' => [
                        'average' => $termAverages->isEmpty() ? null : round((float) $termAverages->avg(), 2),
                        'rank' => null,
                        'rank_of' => null,
                    ],
                ];
            })
            ->values();

        // Yearly rank at read time, within each student's section cohort.
        $students = $students
            ->groupBy('section_id')
            ->flatMap(function (Collection $group): Collection {
                $ranks = Ranking::competition(
                    $group->keyBy('student_id'),
                    fn (array $s): ?float => $s['year']['average'],
                );

                return $group->map(function (array $s) use ($ranks): array {
                    $s['year']['rank'] = $ranks[$s['student_id']] ?? null;
                    $s['year']['rank_of'] = $s['year']['average'] !== null ? count($ranks) : null;

                    return $s;
                });
            });

        $sorted = $students
            ->sortBy([
                ['grade_sort', 'asc'],
                ['section_name', 'asc'],
                fn (array $a, array $b) => ($a['year']['rank'] ?? PHP_INT_MAX) <=> ($b['year']['rank'] ?? PHP_INT_MAX),
                // Deterministic tiebreak — rank ties must never shuffle between fetches.
                fn (array $a, array $b) => strcmp((string) $a['full_name'], (string) $b['full_name']),
            ])
            ->map(fn (array $s): array => collect($s)->except('grade_sort')->all())
            ->values();

        return [
            'data' => [
                'columns' => $this->subjectColumns($results),
                'students' => $sorted->all(),
            ],
            'meta' => [
                'year' => ['id' => $year->id, 'name' => $year->name, 'status' => $year->status->value],
                'terms' => $terms->map(fn (Term $t): array => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'sequence' => $t->sequence,
                    'is_quarter' => $t->is_quarter,
                    'semester' => $t->semester,
                    'status' => $t->status->value,
                ])->values()->all(),
                'has_semester_groups' => $semesterGroups->isNotEmpty(),
                'students' => $sorted->count(),
                'computed_at' => $results->max('computed_at')?->toIso8601String(),
                'report_card' => $this->reportCardMeta($year->branch),
            ],
        ];
    }

    /**
     * The branch-effective report-card policy the roster UI needs: the skill
     * checklist behind the Extra-assessment modal and the print options.
     *
     * @return array<string, mixed>
     */
    private function reportCardMeta(?Branch $branch): array
    {
        return [
            'skills' => $branch?->effectiveReportCardSkills() ?? [],
            'per_page' => $branch?->effectiveReportCardPerPage() ?? 1,
            'subject_ranks' => $branch?->effectiveReportCardSubjectRanks() ?? false,
            'grading_criteria' => $branch?->effectiveReportCardGradingCriteria() ?? false,
        ];
    }

    /**
     * The narrowed frozen-results query every roster reads. Grade narrowing
     * uses the denormalized grade_level_id on the results row itself.
     *
     * @param  list<int>|null  $allowedSectionIds
     * @return Builder<StudentTermResult>
     */
    private function resultsFor(?array $allowedSectionIds, ?int $sectionId, ?int $gradeLevelId)
    {
        return StudentTermResult::query()
            ->when($allowedSectionIds !== null, fn ($q) => $q->whereIn('section_id', $allowedSectionIds))
            ->when($sectionId !== null, fn ($q) => $q->where('section_id', $sectionId))
            ->when($sectionId === null && $gradeLevelId !== null, fn ($q) => $q->where('grade_level_id', $gradeLevelId))
            ->with([
                'student:id,first_name,father_name,grandfather_name,gender,public_id,photo_path',
                'section:id,name,grade_level_id',
                'section.gradeLevel:id,name,sort_order',
            ]);
    }

    /**
     * Ordered union of the subjects appearing in the frozen breakdowns — the
     * single column model shared by the desktop matrix, mobile cards and CSV.
     *
     * @param  Collection<int, StudentTermResult>  $results
     * @return list<array{subject_id: int, code: ?string, name: string}>
     */
    private function subjectColumns(Collection $results): array
    {
        return $results
            ->flatMap(fn (StudentTermResult $r) => collect($r->breakdown))
            ->unique('subject_id')
            ->map(fn (array $line): array => [
                'subject_id' => (int) $line['subject_id'],
                'code' => $line['code'] ?? null,
                'name' => $line['name'] ?? '—',
            ])
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * subject_id → the frozen mark cell (keyed as strings by JSON anyway).
     *
     * @return array<int, array{total: ?float, letter: ?string, is_passing: ?bool}>
     */
    private function scoreMap(StudentTermResult $result): array
    {
        return collect($result->breakdown)
            ->mapWithKeys(fn (array $line): array => [
                (int) $line['subject_id'] => [
                    'total' => isset($line['total']) ? (float) $line['total'] : null,
                    'letter' => $line['letter'] ?? null,
                    'is_passing' => $line['is_passing'] ?? null,
                ],
            ])
            ->all();
    }

    /**
     * The identity cells shared by both sheets.
     *
     * @return array<string, mixed>
     */
    private function studentCells(StudentTermResult $result): array
    {
        return [
            'student_id' => $result->student_id,
            'public_id' => $result->student?->public_id,
            'full_name' => $result->student?->full_name,
            'photo_url' => $result->student?->photo_url,
            'gender' => $result->student?->gender?->value,
            'section_id' => $result->section_id,
            'section_name' => $result->section !== null
                ? trim(($result->section->gradeLevel?->name ?? '').' '.$result->section->name)
                : null,
            'grade_sort' => $result->section?->gradeLevel?->sort_order ?? 0,
        ];
    }

    /**
     * Sheet order: grade, then section, then rank (unranked last), then name.
     * The NAME tiebreak matters: rank ties (and wholly unranked sheets) would
     * otherwise keep the database's arbitrary order, and rows would shuffle
     * between fetches — maddening while entering conduct down a class list.
     *
     * @param  Collection<int, StudentTermResult>  $results
     * @return Collection<int, StudentTermResult>
     */
    private function sortForSheet(Collection $results): Collection
    {
        return $results->sortBy([
            fn (StudentTermResult $a, StudentTermResult $b) => ($a->section?->gradeLevel?->sort_order ?? 0) <=> ($b->section?->gradeLevel?->sort_order ?? 0),
            fn (StudentTermResult $a, StudentTermResult $b) => strcmp((string) $a->section?->name, (string) $b->section?->name),
            fn (StudentTermResult $a, StudentTermResult $b) => ($a->rank ?? PHP_INT_MAX) <=> ($b->rank ?? PHP_INT_MAX),
            fn (StudentTermResult $a, StudentTermResult $b) => strcmp((string) $a->student?->full_name, (string) $b->student?->full_name),
        ])->values();
    }
}
