<?php

namespace App\Services\Reports;

use App\Models\AcademicYear;
use App\Models\StudentPromotion;
use App\Models\StudentTermResult;
use App\Models\Term;
use App\Support\Ranking;
use Illuminate\Support\Collection;

/**
 * The yearly report card ("progress report card"): every term of one academic
 * year pivoted into a subject × term grid with per-subject year averages,
 * per-term totals/averages/ranks, semester sub-groups when the school runs
 * quarters, the read-time yearly average + section rank (the same convention
 * as the yearly roster/transcript), the behavioral skill panel (when the
 * school configured one) and the promotion outcome line. Reads ONLY the
 * frozen student_term_results rows. Authorization is the caller's job.
 */
class YearReportCardService
{
    /**
     * Cards for the requested students, in the given order, plus the ordered
     * term columns they share. Year ranks are computed over each student's
     * WHOLE section cohort (all frozen rows of the section), never just the
     * requested subset. Students with nothing frozen in the year are skipped.
     *
     * @param  list<int>  $studentIds
     * @return array{terms: list<array<string, mixed>>, cards: list<array<string, mixed>>}
     */
    public function cards(AcademicYear $year, array $studentIds): array
    {
        $terms = Term::query()
            ->where('academic_year_id', $year->id)
            ->orderBy('sequence')
            ->get(['id', 'name', 'sequence', 'is_quarter', 'semester', 'status']);

        $termColumns = $terms->map(fn (Term $t): array => [
            'id' => $t->id,
            'name' => $t->name,
            'is_quarter' => (bool) $t->is_quarter,
            'semester' => $t->semester,
        ])->values()->all();

        // The requested students' rows first — they tell us which section
        // cohorts the ranking must span.
        $requested = $this->rows($year)->whereIn('student_id', $studentIds)->get();

        if ($requested->isEmpty()) {
            return ['terms' => $termColumns, 'cards' => [], 'skills' => []];
        }

        $sectionIds = $requested->pluck('section_id')->filter()->unique()->values();

        $cohort = $this->rows($year)
            ->whereIn('section_id', $sectionIds)
            ->get()
            ->groupBy('student_id');

        // student_id → year average over term averages (transcript convention),
        // and the section anchoring the student's cohort (their latest term's
        // row — mid-year section moves land on the newest one, like the roster).
        $sequenceByTerm = $terms->pluck('sequence', 'id');
        $yearAverages = $cohort->map(function (Collection $rows): ?float {
            $averages = $rows->pluck('average')->filter()->map(fn ($v) => (float) $v);

            return $averages->isEmpty() ? null : round((float) $averages->avg(), 2);
        });
        $sectionByStudent = $cohort->map(
            fn (Collection $rows) => $rows
                ->sortBy(fn (StudentTermResult $r) => $sequenceByTerm[$r->term_id] ?? 0)
                ->last()
                ->section_id ?? 0,
        );

        // Section cohort → student_id → year rank (competition convention).
        $ranksBySection = $sectionByStudent
            ->map(fn (int $sectionId, int $studentId) => $studentId)
            ->groupBy(fn (int $studentId) => $sectionByStudent->get($studentId) ?? 0, preserveKeys: true)
            ->map(fn (Collection $group) => [
                'ranks' => Ranking::competition(
                    $group,
                    fn (int $studentId): ?float => $yearAverages->get($studentId),
                ),
                'of' => $group->filter(fn (int $id) => $yearAverages->get($id) !== null)->count(),
            ]);

        $promotions = StudentPromotion::query()
            ->whereIn('from_enrollment_id', $requested->pluck('student_enrollment_id')->unique())
            ->whereNotNull('decided_at')
            ->with('toGradeLevel:id,name')
            ->get()
            ->keyBy(fn (StudentPromotion $p) => $p->from_enrollment_id);

        $skills = $year->branch?->effectiveReportCardSkills() ?? [];

        $semesters = $terms
            ->filter(fn (Term $t) => $t->is_quarter && $t->semester !== null)
            ->groupBy('semester')
            ->map(fn (Collection $group, int $semester): array => [
                'semester' => $semester,
                'term_ids' => $group->pluck('id')->values()->all(),
            ])
            ->sortBy('semester')
            ->values()
            ->all();

        $cards = collect($studentIds)
            ->map(function (int $studentId) use ($year, $terms, $cohort, $yearAverages, $ranksBySection, $promotions, $skills, $semesters): ?array {
                $rows = $cohort->get($studentId);

                if ($rows === null || $rows->isEmpty()) {
                    return null;
                }

                return $this->card($year, $terms, $rows, $yearAverages, $ranksBySection, $promotions, $skills, $semesters);
            })
            ->filter()
            ->values()
            ->all();

        return ['terms' => $termColumns, 'cards' => $cards, 'skills' => $skills];
    }

    /**
     * The grading-criteria legend rows (optional print), read from the LIVE
     * bands of the scale the latest frozen row snapshotted. Informational
     * only — the marks themselves always keep their frozen letters.
     *
     * @param  list<array<string, mixed>>  $cards
     * @return list<array{letter: ?string, label: ?string, min: float, max: float, is_passing: bool}>
     */
    public function gradingCriteria(array $cards): array
    {
        $scaleId = $cards[0]['grading']['scale']['id'] ?? null;

        if ($scaleId === null) {
            return [];
        }

        return \App\Models\GradingScaleBand::query()
            ->where('grading_scale_id', $scaleId)
            ->orderByDesc('min_score')
            ->get()
            ->map(fn (\App\Models\GradingScaleBand $band): array => [
                'letter' => $band->letter,
                'label' => $band->label,
                'min' => (float) $band->min_score,
                'max' => (float) $band->max_score,
                'is_passing' => $band->is_passing,
            ])
            ->all();
    }

    /** The cover side needs the school masthead — same chain as the transcript. */
    public function masthead(AcademicYear $year): array
    {
        $branch = $year->branch()->with('school:id,name,logo_path,phone,address')->first();
        $school = $branch?->school;

        $address = collect([$branch?->sub_city, $branch?->city, $branch?->state])
            ->filter()
            ->implode(', ') ?: $school?->address;

        return [
            'school_name' => $school?->name,
            'branch_name' => $branch?->name,
            'address' => $address ?: null,
            'phone' => $branch?->phone ?? $school?->phone,
        ];
    }

    /**
     * @param  Collection<int, Term>  $terms
     * @param  Collection<int, StudentTermResult>  $rows
     * @param  Collection<int, ?float>  $yearAverages
     * @param  Collection<int, array{ranks: array<int, int>, of: int}>  $ranksBySection
     * @param  Collection<int|string, StudentPromotion>  $promotions
     * @param  list<array<string, mixed>>  $skills
     * @param  list<array<string, mixed>>  $semesters
     * @return array<string, mixed>
     */
    private function card(
        AcademicYear $year,
        Collection $terms,
        Collection $rows,
        Collection $yearAverages,
        Collection $ranksBySection,
        Collection $promotions,
        array $skills,
        array $semesters,
    ): array {
        $byTerm = $rows->keyBy('term_id');
        $latest = $terms->reverse()->first(fn (Term $t) => $byTerm->has($t->id));
        $latestRow = $byTerm[$latest->id];
        $studentId = (int) $latestRow->student_id;

        // Subject rows: ordered union across the student's term breakdowns.
        $subjects = $rows
            ->flatMap(fn (StudentTermResult $r) => collect($r->breakdown))
            ->unique('subject_id')
            ->sortBy('name')
            ->values()
            ->map(function (array $line) use ($terms, $byTerm): array {
                $perTerm = [];
                $totals = [];

                foreach ($terms as $term) {
                    $cell = $byTerm->has($term->id)
                        ? collect($byTerm[$term->id]->breakdown)->firstWhere('subject_id', $line['subject_id'])
                        : null;
                    $perTerm[$term->id] = $cell === null ? null : [
                        'total' => isset($cell['total']) ? (float) $cell['total'] : null,
                        'letter' => $cell['letter'] ?? null,
                        'is_passing' => $cell['is_passing'] ?? null,
                    ];

                    if (($perTerm[$term->id]['total'] ?? null) !== null) {
                        $totals[] = $perTerm[$term->id]['total'];
                    }
                }

                return [
                    'subject_id' => (int) $line['subject_id'],
                    'code' => $line['code'] ?? null,
                    'name' => $line['name'] ?? '—',
                    'per_term' => $perTerm,
                    'year_avg' => $totals === [] ? null : round(array_sum($totals) / count($totals), 2),
                ];
            })
            ->all();

        // Per-term summary strips (keyed by term id for the template).
        $perTerm = fn (callable $pick): array => $terms
            ->mapWithKeys(fn (Term $t) => [$t->id => $byTerm->has($t->id) ? $pick($byTerm[$t->id]) : null])
            ->all();

        $sectionKey = $latestRow->section_id ?? 0;
        $sectionRanks = $ranksBySection->get($sectionKey);

        // Semester sub-averages (quarters only) — mean of the group's term averages.
        $semesterCells = collect($semesters)
            ->map(function (array $group) use ($byTerm): array {
                $averages = collect($group['term_ids'])
                    ->map(fn (int $id) => $byTerm->get($id)?->average)
                    ->filter(fn ($v) => $v !== null)
                    ->map(fn ($v) => (float) $v);

                return [
                    ...$group,
                    'average' => $averages->isEmpty() ? null : round((float) $averages->avg(), 2),
                ];
            })
            ->all();

        $promotion = $rows
            ->pluck('student_enrollment_id')
            ->map(fn ($id) => $promotions->get($id))
            ->filter()
            ->first();

        return [
            'student' => [
                'id' => $latestRow->student?->id,
                'public_id' => $latestRow->student?->public_id,
                'full_name' => $latestRow->student?->full_name,
                'gender' => $latestRow->student?->gender?->value,
            ],
            'academic_year' => $year->name,
            'grade_level' => $latestRow->section?->gradeLevel?->name ?? $latestRow->gradeLevel?->name,
            'section_name' => $latestRow->section?->name,
            'subjects' => $subjects,
            'totals' => $perTerm(fn (StudentTermResult $r) => $r->total !== null ? (float) $r->total : null),
            'averages' => $perTerm(fn (StudentTermResult $r) => $r->average !== null ? (float) $r->average : null),
            'ranks' => $perTerm(fn (StudentTermResult $r) => $r->rank),
            'ranks_of' => $perTerm(fn (StudentTermResult $r) => $r->rank_of),
            'absences' => $perTerm(fn (StudentTermResult $r) => $r->absence_days),
            'conducts' => $perTerm(fn (StudentTermResult $r) => $r->conduct),
            'skill_ratings' => $skills === [] ? [] : $perTerm(fn (StudentTermResult $r) => $r->skills),
            'semesters' => $semesterCells,
            'year' => [
                'average' => $yearAverages->get($studentId),
                'rank' => $sectionRanks['ranks'][$studentId] ?? null,
                'rank_of' => $yearAverages->get($studentId) !== null ? ($sectionRanks['of'] ?? null) : null,
            ],
            // The latest term's snapshotted grading drives the optional
            // criteria legend — bands as they were frozen, never remapped.
            'grading' => $latestRow->grading,
            'comment' => $latestRow->comment,
            'outcome' => $promotion === null ? null : [
                'decision' => $promotion->decision->value,
                'label' => $promotion->decision->label(),
                'to_grade_level' => $promotion->toGradeLevel?->name,
            ],
        ];
    }

    /** @return \Illuminate\Database\Eloquent\Builder<StudentTermResult> */
    private function rows(AcademicYear $year)
    {
        return StudentTermResult::query()
            ->where('academic_year_id', $year->id)
            ->with([
                'student:id,first_name,father_name,grandfather_name,gender,public_id',
                'section:id,name,grade_level_id',
                'section.gradeLevel:id,name,sort_order',
                'gradeLevel:id,name',
            ]);
    }
}
