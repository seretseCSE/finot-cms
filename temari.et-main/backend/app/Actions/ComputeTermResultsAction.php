<?php

namespace App\Actions;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Models\AttendanceRecord;
use App\Models\GradeLevel;
use App\Models\GradingScale;
use App\Models\StudentEnrollment;
use App\Models\StudentTermResult;
use App\Models\SubjectAssignment;
use App\Models\Term;
use App\Services\GradingPolicyResolver;
use App\Support\Ranking;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Computes (or recomputes) the semester report card rows for a term: per
 * enrollment, the weighted total of every subject, the average across
 * subjects with marks, the rank within the section, absence days, and the
 * grade bands resolved through the branch's grading policy — then freezes
 * them into student_term_results. Letters are SNAPSHOTTED here: editing a
 * scale later never rewrites history. Conduct + homeroom comments are
 * entered separately and survive recomputes. Idempotent; processes section
 * by section so a 10k-student branch never loads its whole continuous assessment at once.
 */
class ComputeTermResultsAction
{
    public function __construct(private readonly GradingPolicyResolver $grading)
    {
    }

    /** @return int number of result rows written */
    public function execute(Term $term): int
    {
        $written = 0;

        $sectionIds = StudentEnrollment::query()
            ->where('academic_year_id', $term->academic_year_id)
            ->where('branch_id', $term->branch_id)
            ->where('status', EnrollmentStatus::Active->value)
            ->when($term->school_program_id !== null, fn ($q) => $q->where('school_program_id', $term->school_program_id))
            ->whereNotNull('section_id')
            ->distinct()
            ->pluck('section_id');

        $gradeSorts = GradeLevel::query()->pluck('sort_order', 'id');

        foreach ($sectionIds as $sectionId) {
            $written += $this->computeSection($term, (int) $sectionId, $gradeSorts);
        }

        return $written;
    }

    /**
     * @param  Collection<int, int>  $gradeSorts  grade_level_id → sort_order
     */
    private function computeSection(Term $term, int $sectionId, Collection $gradeSorts): int
    {
        $enrollments = StudentEnrollment::query()
            ->where('academic_year_id', $term->academic_year_id)
            ->where('section_id', $sectionId)
            ->where('status', EnrollmentStatus::Active->value)
            ->when($term->school_program_id !== null, fn ($q) => $q->where('school_program_id', $term->school_program_id))
            ->get();

        if ($enrollments->isEmpty()) {
            return 0;
        }

        $assignments = SubjectAssignment::query()
            ->where('term_id', $term->id)
            ->where('section_id', $sectionId)
            ->where('is_active', true)
            ->with(['subject:id,code,name', 'assessments.results'])
            ->get();

        $absences = $this->absenceDays($term, $sectionId);

        // subject_id → per-student summed weighted totals across every
        // assessment of every assignment row (team teaching shares a subject).
        $rows = $enrollments->map(function (StudentEnrollment $enrollment) use ($assignments, $term, $sectionId, $gradeSorts, $absences): array {
            $gradeSort = (int) ($gradeSorts[$enrollment->grade_level_id] ?? 0);
            $policy = $this->grading->resolve($term->school_id, $term->branch_id, $gradeSort);

            $breakdown = $this->subjectTotals($assignments, $enrollment->student_id, $policy);
            $scored = collect($breakdown)->whereNotNull('total');

            $total = $scored->isEmpty() ? null : round((float) $scored->sum('total'), 2);
            $average = $scored->isEmpty() ? null : round((float) $scored->avg('total'), 2);

            $overallBand = $average !== null ? $policy['scale']->bandFor($average) : null;

            return [
                'student_id' => $enrollment->student_id,
                'student_enrollment_id' => $enrollment->id,
                'term_id' => $term->id,
                'school_id' => $term->school_id,
                'branch_id' => $term->branch_id,
                'academic_year_id' => $term->academic_year_id,
                'section_id' => $sectionId,
                'grade_level_id' => $enrollment->grade_level_id,
                'total' => $total,
                'average' => $average,
                'rank' => null,
                'rank_of' => null,
                'subject_count' => $scored->count(),
                'breakdown' => $breakdown,
                'grading' => [
                    'scale' => [
                        'id' => $policy['scale']->id,
                        'code' => $policy['scale']->code,
                        'name' => $policy['scale']->name,
                    ],
                    'display' => $policy['display'],
                    'overall' => $overallBand === null ? null : [
                        'letter' => $overallBand->letter,
                        'label' => $overallBand->label,
                        'grade_points' => $overallBand->grade_points !== null ? (float) $overallBand->grade_points : null,
                        'is_passing' => $overallBand->is_passing,
                    ],
                ],
                'absence_days' => (int) ($absences[$enrollment->student_id] ?? 0),
                'computed_at' => now(),
            ];
        });

        // Competition ranking (1, 2, 2, 4) among students with an average.
        $ranks = Ranking::competition(
            $rows->keyBy('student_enrollment_id'),
            fn (array $row): ?float => $row['average'],
        );
        $rankOf = count($ranks);

        $rows = $this->stampSubjectRanks($rows);

        DB::transaction(function () use ($rows, $ranks, $rankOf, $term): void {
            foreach ($rows as $row) {
                $row['rank'] = $ranks[$row['student_enrollment_id']] ?? null;
                $row['rank_of'] = $row['average'] !== null ? $rankOf : null;

                // Conduct + homeroom comment are staff-entered on the same row
                // and deliberately absent here, so recomputes preserve them.
                StudentTermResult::updateOrCreate(
                    [
                        'student_enrollment_id' => $row['student_enrollment_id'],
                        'term_id' => $term->id,
                    ],
                    $row,
                );
            }
        });

        return $rows->count();
    }

    /**
     * Per-subject section ranks, frozen into each breakdown line (rank +
     * rank_of among the section's scored students for that subject). Same
     * competition convention as the overall rank; whether the printed card
     * SHOWS them is the report_card_subject_ranks school/branch setting —
     * the snapshot always carries them so flipping the setting needs no
     * recompute.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function stampSubjectRanks(Collection $rows): Collection
    {
        // subject_id → enrollment_id → rank, over the section's frozen totals.
        $ranksBySubject = [];
        $countsBySubject = [];

        foreach ($rows as $row) {
            foreach ($row['breakdown'] as $line) {
                if ($line['total'] !== null) {
                    $ranksBySubject[$line['subject_id']][$row['student_enrollment_id']] = (float) $line['total'];
                }
            }
        }

        foreach ($ranksBySubject as $subjectId => $totals) {
            $ranksBySubject[$subjectId] = Ranking::competition(
                collect($totals),
                fn (float $total): float => $total,
            );
            $countsBySubject[$subjectId] = count($totals);
        }

        return $rows->map(function (array $row) use ($ranksBySubject, $countsBySubject): array {
            $row['breakdown'] = array_map(function (array $line) use ($row, $ranksBySubject, $countsBySubject): array {
                $line['rank'] = $ranksBySubject[$line['subject_id']][$row['student_enrollment_id']] ?? null;
                $line['rank_of'] = $line['rank'] !== null ? $countsBySubject[$line['subject_id']] : null;

                return $line;
            }, $row['breakdown']);

            return $row;
        });
    }

    /**
     * Absent-day counts per student for the section within the term (anchored
     * rows by term_id; unanchored ones by the term's date window).
     *
     * @return Collection<int, int> student_id → days
     */
    private function absenceDays(Term $term, int $sectionId): Collection
    {
        return AttendanceRecord::query()
            ->where('section_id', $sectionId)
            ->where('status', AttendanceStatus::Absent->value)
            ->where(function ($q) use ($term): void {
                $q->where('term_id', $term->id);

                if ($term->starts_on !== null && $term->ends_on !== null) {
                    $q->orWhere(fn ($w) => $w->whereNull('term_id')
                        ->whereBetween('date', [$term->starts_on, $term->ends_on]));
                }
            })
            ->selectRaw('student_id, COUNT(*) as days')
            ->groupBy('student_id')
            ->pluck('days', 'student_id');
    }

    /**
     * Weighted total per subject for one student: Σ (score/max × weight) over
     * every non-absent scored assessment, merged across team-teaching rows,
     * plus the grade band it falls into under the resolved grading policy.
     *
     * @param  Collection<int, SubjectAssignment>  $assignments
     * @param  array{scale: GradingScale, display: string}  $policy
     * @return list<array{subject_id: int, code: string, name: string, total: ?float, letter: ?string, band_label: ?string, is_passing: ?bool}>
     */
    private function subjectTotals(Collection $assignments, int $studentId, array $policy): array
    {
        return $assignments
            ->groupBy('subject_id')
            ->map(function (Collection $group) use ($studentId, $policy): array {
                $subject = $group->first()->subject;
                $sum = 0.0;
                $scored = false;

                foreach ($group as $assignment) {
                    foreach ($assignment->assessments as $assessment) {
                        $result = $assessment->results->firstWhere('student_id', $studentId);

                        if ($result === null || $result->is_absent || $result->score === null || (float) $assessment->max_score <= 0) {
                            continue;
                        }

                        $sum += ((float) $result->score / (float) $assessment->max_score) * (float) $assessment->weight;
                        $scored = true;
                    }
                }

                $total = $scored ? round($sum, 2) : null;
                $band = $total !== null ? $policy['scale']->bandFor($total) : null;

                return [
                    'subject_id' => $subject->id,
                    'code' => $subject->code,
                    'name' => $subject->name,
                    'total' => $total,
                    'letter' => $band?->letter,
                    'band_label' => $band?->label,
                    'is_passing' => $band?->is_passing,
                ];
            })
            ->values()
            ->all();
    }
}
