<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\PromotionDecision;
use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\StudentEnrollment;
use App\Models\StudentPromotion;
use App\Models\StudentTermResult;
use Illuminate\Support\Collection;

/**
 * Builds the year-end promotion board: every live enrollment of a year —
 * PLUS the ones an executed rollover already closed — with its per-term
 * averages, annual average, attendance rate, the policy-derived SUGGESTION
 * and any saved decision. Deciding and executing live in
 * SavePromotionDecisionsAction / RolloverPromotionsAction — this service only
 * reads.
 */
class PromotionBoardService
{
    /**
     * @return array{rows: Collection<int, array<string, mixed>>, terms: Collection<int, array<string, mixed>>, threshold: float, top_grade_sort: ?int}
     */
    public function build(AcademicYear $year, ?int $gradeLevelId, ?int $branchFilterId = null): array
    {
        $threshold = $year->branch->effectivePromotionThreshold();
        $topGradeSort = $this->topGradeSort($year->branch_id);

        // The rollover stamps its sources promoted/repeated/graduated, so an
        // executed row stops being `live` — but the board must keep showing it
        // or the year you just rolled over reads as empty and the revert lane
        // has no row to act on. Executed rows come back read-only (the decision
        // cell renders a locked badge, not a picker).
        $executedSourceIds = StudentPromotion::query()
            ->where('academic_year_id', $year->id)
            ->whereNotNull('executed_at')
            ->when($gradeLevelId !== null, fn ($q) => $q->where('from_grade_level_id', $gradeLevelId))
            ->pluck('from_enrollment_id');

        $enrollments = StudentEnrollment::query()
            ->where('academic_year_id', $year->id)
            ->where(fn ($q) => $q->live()->orWhereIn('id', $executedSourceIds))
            ->when($gradeLevelId !== null, fn ($q) => $q->where('grade_level_id', $gradeLevelId))
            ->with([
                'student:id,first_name,father_name,grandfather_name,gender,public_id,photo_path',
                'section:id,name',
                'gradeLevel:id,name,sort_order',
            ])
            ->get();

        $enrollmentIds = $enrollments->pluck('id');

        $results = StudentTermResult::query()
            ->whereIn('student_enrollment_id', $enrollmentIds)
            ->get()
            ->groupBy('student_enrollment_id');

        $decisions = StudentPromotion::query()
            ->whereIn('from_enrollment_id', $enrollmentIds)
            ->get()
            ->keyBy('from_enrollment_id');

        $attendance = AttendanceRecord::query()
            ->where('academic_year_id', $year->id)
            ->whereIn('student_id', $enrollments->pluck('student_id'))
            ->selectRaw('student_id, COUNT(*) AS total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS present', [AttendanceStatus::Present->value])
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        $terms = $year->terms()
            ->orderBy('sequence')
            ->get(['id', 'name', 'sequence', 'status'])
            ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'sequence' => $t->sequence, 'status' => $t->status->value]);

        $rows = $enrollments->map(function (StudentEnrollment $enrollment) use ($results, $decisions, $attendance, $threshold, $topGradeSort): array {
            $termResults = $results->get($enrollment->id, collect());

            $termAverages = $termResults
                ->map(fn (StudentTermResult $r) => [
                    'term_id' => $r->term_id,
                    'average' => $r->average !== null ? (float) $r->average : null,
                    'rank' => $r->rank,
                    'rank_of' => $r->rank_of,
                ])
                ->values();

            $scored = $termResults->filter(fn (StudentTermResult $r) => $r->average !== null);
            $annual = $scored->isEmpty() ? null : round((float) $scored->avg(fn ($r) => (float) $r->average), 2);

            $att = $attendance->get($enrollment->student_id);
            $attendanceRate = $att !== null && (int) $att->total > 0
                ? round(((int) $att->present / (int) $att->total) * 100, 1)
                : null;

            $decision = $decisions->get($enrollment->id);

            return [
                'enrollment_id' => $enrollment->id,
                'student' => [
                    'id' => $enrollment->student->id,
                    'public_id' => $enrollment->student->public_id,
                    'full_name' => $enrollment->student->full_name,
                    'gender' => $enrollment->student->gender,
                    'photo_url' => $enrollment->student->photo_url,
                ],
                'grade_level_id' => $enrollment->grade_level_id,
                'grade_level_name' => $enrollment->gradeLevel->name,
                'section_name' => $enrollment->section?->name,
                'enrollment_status' => $enrollment->status->value,
                'term_averages' => $termAverages,
                'annual_average' => $annual,
                'attendance_rate' => $attendanceRate,
                'suggestion' => $this->suggest($enrollment->gradeLevel, $annual, $threshold, $topGradeSort)?->value,
                'decision' => $decision === null ? null : [
                    'value' => $decision->decision->value,
                    'notes' => $decision->notes,
                    'average' => $decision->average !== null ? (float) $decision->average : null,
                    'decided_at' => $decision->decided_at?->toISOString(),
                    'executed_at' => $decision->executed_at?->toISOString(),
                ],
            ];
        })->sortBy([['grade_level_id', 'asc'], ['section_name', 'asc'], ['student.full_name', 'asc']])->values();

        return [
            'rows' => $rows,
            'terms' => $terms,
            'threshold' => $threshold,
            'top_grade_sort' => $topGradeSort,
        ];
    }

    /**
     * Policy suggestion: no marks → none (decide by hand); top grade of the
     * branch + passing → graduated; passing → promoted; failing → repeated.
     */
    private function suggest(GradeLevel $grade, ?float $annual, float $threshold, ?int $topGradeSort): ?PromotionDecision
    {
        if ($annual === null) {
            return null;
        }

        if ($annual < $threshold) {
            return PromotionDecision::Repeated;
        }

        if ($topGradeSort !== null && $grade->sort_order >= $topGradeSort) {
            return PromotionDecision::Graduated;
        }

        return PromotionDecision::Promoted;
    }

    /** Highest grade the branch actually runs (has sections for). */
    private function topGradeSort(int $branchId): ?int
    {
        return GradeLevel::query()
            ->whereIn('id', Section::query()->where('branch_id', $branchId)->where('is_active', true)->select('grade_level_id'))
            ->max('sort_order');
    }
}
