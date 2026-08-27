<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EnrollmentStatus;
use App\Enums\MarklistStatus;
use App\Http\Controllers\Controller;
use App\Models\AssessmentResult;
use App\Models\GradeLevel;
use App\Models\Marklist;
use App\Models\Section;
use App\Models\StudentEnrollment;
use App\Models\StudentTermResult;
use App\Models\SubjectAssignment;
use App\Models\Term;
use App\Models\User;
use App\Services\Analytics\Analytics;
use App\Services\GradingPolicyResolver;
use App\Services\Notify\Notifier;
use App\Support\Ranking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Grading analytics for one term, aggregated from the FROZEN
 * student_term_results rows (so the numbers always match issued report
 * cards) plus the marklist register: overall stats, grade-band distribution,
 * per-section and per-subject performance, top students and marklist
 * submission progress. One endpoint per dashboard (chart conventions).
 */
class GradingReportController extends Controller
{
    public function overview(Request $request, Term $term): JsonResponse
    {
        abort_unless(
            $request->user()->hasPermissionForScope('grades.view', $term->school_id, $term->branch_id),
            403,
        );

        $sectionId = $request->filled('section_id') ? $request->integer('section_id') : null;
        $gradeLevelId = $request->filled('grade_level_id') ? $request->integer('grade_level_id') : null;

        $results = $this->resultsFor($term->id, $sectionId, $gradeLevelId);

        $withAverage = $results->whereNotNull('average');
        // Pass/fail is judged only on rows that carry a grading snapshot —
        // rows frozen before a policy existed count as unknown, not failing.
        $graded = $withAverage->filter(fn ($r) => isset($r->grading['overall']));
        $passing = $graded->filter(fn ($r) => (bool) ($r->grading['overall']['is_passing'] ?? false));

        return response()->json([
            'data' => [
                'term' => ['id' => $term->id, 'name' => $term->name, 'status' => $term->status->value],
                'totals' => [
                    'students' => $results->count(),
                    'with_results' => $withAverage->count(),
                    'average' => $withAverage->isEmpty() ? null : round((float) $withAverage->avg('average'), 2),
                    'pass_rate' => $graded->isEmpty()
                        ? null
                        : round($passing->count() / $graded->count() * 100, 1),
                    'avg_absence_days' => $results->whereNotNull('absence_days')->avg('absence_days') !== null
                        ? round((float) $results->whereNotNull('absence_days')->avg('absence_days'), 1)
                        : null,
                ],
                'previous' => $this->previousTermComparison($term, $sectionId, $gradeLevelId),
                'bands' => $this->bandDistribution($withAverage),
                'sections' => $this->sectionStats($withAverage),
                'subjects' => $this->subjectStats($results),
                'gender' => $this->genderStats($withAverage),
                'marklists' => $this->marklistProgress($term, $sectionId, $gradeLevelId),
                'top_students' => $this->topStudents($withAverage),
                'at_risk' => $this->atRiskStudents($withAverage),
            ],
        ]);
    }

    /**
     * Marklist analysis: the per-student score list for one subject (or the
     * overall average when no subject is given), ranked, with a gender
     * summary and the grading scale's bands as default performance ranges.
     * Range binning itself happens client-side so adjusting custom ranges
     * never refetches — this endpoint just hands over the raw scored rows.
     */
    public function marklistAnalysis(Request $request, Term $term, GradingPolicyResolver $grading): JsonResponse
    {
        abort_unless(
            $request->user()->hasPermissionForScope('grades.view', $term->school_id, $term->branch_id),
            403,
        );

        $sectionId = $request->filled('section_id') ? $request->integer('section_id') : null;
        $gradeLevelId = $request->filled('grade_level_id') ? $request->integer('grade_level_id') : null;
        $subjectId = $request->filled('subject_id') ? $request->integer('subject_id') : null;

        $results = $this->resultsFor($term->id, $sectionId, $gradeLevelId);

        $subject = null;
        $scored = $results
            ->map(function (StudentTermResult $r) use ($subjectId, &$subject): ?array {
                if ($subjectId !== null) {
                    $line = collect($r->breakdown)->firstWhere('subject_id', $subjectId);
                    if ($line === null || ($line['total'] ?? null) === null) {
                        return null;
                    }
                    $subject ??= [
                        'id' => (int) $line['subject_id'],
                        'code' => $line['code'] ?? null,
                        'name' => $line['name'] ?? '—',
                    ];
                    $score = (float) $line['total'];
                    $letter = $line['letter'] ?? null;
                    $isPassing = $line['is_passing'] ?? null;
                } else {
                    if ($r->average === null) {
                        return null;
                    }
                    $score = (float) $r->average;
                    $letter = $r->grading['overall']['letter'] ?? null;
                    $isPassing = $r->grading['overall']['is_passing'] ?? null;
                }

                return [
                    'student_id' => $r->student_id,
                    'public_id' => $r->student?->public_id,
                    'full_name' => $r->student?->full_name,
                    'photo_url' => $r->student?->photo_url,
                    'gender' => $r->student?->gender?->value,
                    'section_id' => $r->section_id,
                    'section_name' => $r->section !== null
                        ? trim(($r->section->gradeLevel?->name ?? '').' '.$r->section->name)
                        : null,
                    'score' => $score,
                    'letter' => $letter,
                    'is_passing' => $isPassing,
                ];
            })
            ->filter()
            ->sortByDesc('score')
            ->take(4000)
            ->values();

        $ranks = Ranking::competition($scored->keyBy('student_id'), fn (array $s): float => $s['score']);
        $students = $scored
            ->map(function (array $s) use ($ranks): array {
                $s['rank'] = $ranks[$s['student_id']] ?? null;

                return $s;
            })
            ->values();

        return response()->json([
            'data' => [
                'term' => ['id' => $term->id, 'name' => $term->name, 'status' => $term->status->value],
                'subject' => $subject,
                'students' => $students->all(),
                'summary' => [
                    'count' => $students->count(),
                    'male' => $students->where('gender', 'male')->count(),
                    'female' => $students->where('gender', 'female')->count(),
                    'average' => $students->isEmpty() ? null : round((float) $students->avg('score'), 2),
                    'min' => $students->isEmpty() ? null : (float) $students->min('score'),
                    'max' => $students->isEmpty() ? null : (float) $students->max('score'),
                ],
                'default_ranges' => $this->defaultRanges($term, $sectionId, $gradeLevelId, $grading),
            ],
        ]);
    }

    /**
     * The submission monitor: one row per active teaching assignment of the
     * term — teacher, class, marklist workflow state and how much of the marks
     * grid is actually filled in — so a director sees WHO is behind, not just
     * how many. Entry completeness counts recorded cells (score or absent)
     * against roster × assessment columns; an assignment with no columns yet
     * reports null (nothing to measure).
     */
    public function submissionStatus(Request $request, Term $term): JsonResponse
    {
        abort_unless(
            $request->user()->hasPermissionForScope('grades.view', $term->school_id, $term->branch_id),
            403,
        );

        $sectionId = $request->filled('section_id') ? $request->integer('section_id') : null;
        $gradeLevelId = $request->filled('grade_level_id') ? $request->integer('grade_level_id') : null;

        $assignments = SubjectAssignment::query()
            ->where('term_id', $term->id)
            ->where('is_active', true)
            ->when($sectionId !== null, fn ($q) => $q->where('section_id', $sectionId))
            ->when($sectionId === null && $gradeLevelId !== null, fn ($q) => $q->whereHas(
                'section',
                fn ($sec) => $sec->where('grade_level_id', $gradeLevelId),
            ))
            ->with([
                'subject:id,code,name',
                'section:id,name,grade_level_id',
                'section.gradeLevel:id,name,sort_order',
                'employee:id,first_name,father_name,grandfather_name,user_id,photo_path',
                'marklist:id,subject_assignment_id,status,submitted_at,approved_at',
            ])
            ->withCount('assessments')
            ->get();

        $ids = $assignments->pluck('id');

        // Roster size per section (one grouped query, active rows of the year).
        $rosterCounts = StudentEnrollment::query()
            ->whereIn('section_id', $assignments->pluck('section_id')->unique()->filter())
            ->where('academic_year_id', $term->academic_year_id)
            ->where('status', EnrollmentStatus::Active->value)
            ->selectRaw('section_id, COUNT(*) as c')
            ->groupBy('section_id')
            ->pluck('c', 'section_id');

        // Recorded cells (a mark or an explicit absence) per assignment.
        $recordedCells = AssessmentResult::query()
            ->join('assessments', 'assessments.id', '=', 'assessment_results.assessment_id')
            ->whereIn('assessments.subject_assignment_id', $ids)
            ->where(fn ($q) => $q->whereNotNull('assessment_results.score')->orWhere('assessment_results.is_absent', true))
            ->selectRaw('assessments.subject_assignment_id as aid, COUNT(*) as c')
            ->groupBy('aid')
            ->pluck('c', 'aid');

        $rows = $assignments
            ->map(function (SubjectAssignment $a) use ($rosterCounts, $recordedCells): array {
                $roster = (int) ($rosterCounts[$a->section_id] ?? 0);
                $columns = (int) $a->assessments_count;
                $cells = $roster * $columns;
                $recorded = min((int) ($recordedCells[$a->id] ?? 0), $cells);

                return [
                    'subject_assignment_id' => $a->id,
                    'subject' => ['id' => $a->subject?->id, 'code' => $a->subject?->code, 'name' => $a->subject?->name],
                    'section' => [
                        'id' => $a->section?->id,
                        'name' => trim(($a->section?->gradeLevel?->name ?? '').' '.($a->section?->name ?? '')),
                        'grade_sort' => $a->section?->gradeLevel?->sort_order ?? 0,
                    ],
                    'teacher' => [
                        'employee_id' => $a->employee?->id,
                        'name' => $a->employee?->full_name,
                        'photo_url' => $a->employee?->photo_url,
                        'has_account' => $a->employee?->user_id !== null,
                    ],
                    'status' => $a->marklist?->status?->value ?? 'not_started',
                    'submitted_at' => $a->marklist?->submitted_at?->toIso8601String(),
                    'approved_at' => $a->marklist?->approved_at?->toIso8601String(),
                    'entry' => [
                        'students' => $roster,
                        'columns' => $columns,
                        'percent' => $cells > 0 ? (int) round($recorded / $cells * 100) : null,
                    ],
                ];
            })
            // Worst first: the follow-up list a director actually works down.
            ->sortBy([
                fn (array $a, array $b) => self::STATUS_ORDER[$a['status']] <=> self::STATUS_ORDER[$b['status']],
                fn (array $a, array $b) => ($a['entry']['percent'] ?? -1) <=> ($b['entry']['percent'] ?? -1),
            ])
            ->values();

        return response()->json([
            'data' => $rows->all(),
            'meta' => [
                'term' => ['id' => $term->id, 'name' => $term->name, 'status' => $term->status->value, 'ends_on' => $term->ends_on?->toDateString()],
                'total' => $rows->count(),
                'not_started' => $rows->where('status', 'not_started')->count(),
                'draft' => $rows->where('status', 'draft')->count(),
                'submitted' => $rows->where('status', 'submitted')->count(),
                'approved' => $rows->where('status', 'approved')->count(),
            ],
        ]);
    }

    /** Follow-up priority for the submission monitor's default order. */
    private const STATUS_ORDER = ['not_started' => 0, 'draft' => 1, 'submitted' => 2, 'approved' => 3];

    /**
     * Nudge the laggards: in-app reminder to the teachers behind the given
     * assignments (or every non-approved one in scope). One notification per
     * TEACHER — never one per class — listing their pending marklists, folded
     * by a per-term dedupe key so repeated nagging updates in place instead of
     * stacking. Reminding is the approver's move (the countersigning
     * authority), mirroring the paper ritual.
     */
    public function remindPending(Request $request, Term $term, Notifier $notifier): JsonResponse
    {
        abort_unless(
            $request->user()->hasPermissionForScope('grades.approve', $term->school_id, $term->branch_id),
            403,
        );

        // The target list is ALWAYS explicit — a malformed payload must never
        // silently widen a one-row nudge into a whole-term blast.
        $data = $request->validate([
            'subject_assignment_ids' => ['required', 'array', 'min:1', 'max:500'],
            'subject_assignment_ids.*' => ['integer'],
        ]);

        $assignments = SubjectAssignment::query()
            ->where('term_id', $term->id)
            ->where('is_active', true)
            ->whereIn('id', $data['subject_assignment_ids'])
            ->whereDoesntHave('marklist', fn ($q) => $q->where('status', MarklistStatus::Approved->value))
            ->whereHas('employee', fn ($q) => $q->whereNotNull('user_id'))
            ->with([
                'subject:id,name',
                'section:id,name,grade_level_id',
                'section.gradeLevel:id,name',
                'employee:id,user_id',
            ])
            ->get();

        $reminded = 0;

        foreach ($assignments->groupBy(fn (SubjectAssignment $a) => $a->employee->user_id) as $userId => $group) {
            $classes = $group
                ->map(fn (SubjectAssignment $a): string => trim(
                    ($a->subject?->name ?? '')
                    .' ('.trim(($a->section?->gradeLevel?->name ?? '').' '.($a->section?->name ?? '')).')'
                ))
                ->take(6);
            $extra = $group->count() - $classes->count();

            // Dedupe per CLASS SET, not per teacher: re-clicking the same row
            // (or re-running the same remind-all) folds into one note, while
            // a reminder about a different class is genuinely new information
            // and must land as its own notification. `count` is reserved for
            // the Notifier's fold counter — the figure travels as `pending`.
            $setHash = substr(md5($group->pluck('id')->sort()->implode(',')), 0, 12);

            $notifier->toUser(User::find($userId), 'academics.marklist_reminder', [
                'pending' => $group->count(),
                'classes' => $classes->implode(', ').($extra > 0 ? ' +'.$extra : ''),
                'term' => $term->name,
            ], [
                'link' => '/marklists',
                'schoolId' => $term->school_id,
                'branchId' => $term->branch_id,
                'dedupeKey' => "marklist-reminder:{$term->id}:{$userId}:{$setHash}",
            ]);
            $reminded++;
        }

        Analytics::capture($request->user(), 'marklist.reminded', [
            'term_id' => $term->id,
            'teachers' => $reminded,
            'assignments' => $assignments->count(),
        ], $term->school_id, $term->branch_id);

        return response()->json([
            'data' => ['teachers' => $reminded, 'assignments' => $assignments->count()],
            'message' => 'Reminders sent.',
        ]);
    }

    /**
     * The grading scale's bands as ready-made performance ranges — resolvable
     * only when the narrowing pins down a single grade level (the policy is
     * grade-windowed). The frontend falls back to fixed ranges otherwise.
     *
     * @return list<array{min: float, max: float, label: string, letter: ?string, is_passing: bool}>|null
     */
    private function defaultRanges(Term $term, ?int $sectionId, ?int $gradeLevelId, GradingPolicyResolver $grading): ?array
    {
        if ($sectionId !== null) {
            $gradeLevelId = Section::query()->whereKey($sectionId)->value('grade_level_id') ?? $gradeLevelId;
        }

        if ($gradeLevelId === null) {
            return null;
        }

        $gradeSort = GradeLevel::query()->whereKey($gradeLevelId)->value('sort_order');
        if ($gradeSort === null) {
            return null;
        }

        $policy = $grading->resolve($term->school_id, $term->branch_id, (int) $gradeSort);

        return $policy['scale']->bands
            ->sortByDesc('min_score')
            ->map(fn ($band): array => [
                'min' => (float) $band->min_score,
                'max' => (float) $band->max_score,
                'label' => $band->label,
                'letter' => $band->letter,
                'is_passing' => (bool) $band->is_passing,
            ])
            ->values()
            ->all();
    }

    /**
     * The frozen result rows for one term under the optional section/grade
     * narrowing — the single population every aggregate reads.
     *
     * @return Collection<int, StudentTermResult>
     */
    private function resultsFor(int $termId, ?int $sectionId, ?int $gradeLevelId): Collection
    {
        return StudentTermResult::query()
            ->where('term_id', $termId)
            ->when($sectionId !== null, fn ($q) => $q->where('section_id', $sectionId))
            ->when($sectionId === null && $gradeLevelId !== null, fn ($q) => $q->whereHas(
                'section',
                fn ($s) => $s->where('grade_level_id', $gradeLevelId),
            ))
            ->with([
                'section:id,name,grade_level_id',
                'section.gradeLevel:id,name,sort_order',
                'student:id,first_name,father_name,grandfather_name,gender,public_id,photo_path',
            ])
            ->limit(10000)
            ->get();
    }

    /**
     * Same-scope average and pass rate of the PREVIOUS semester in the year,
     * so the dashboard can show the trend (null when there is no earlier
     * semester or it has no frozen results yet).
     *
     * @return array{term: array{id: int, name: string}, average: ?float, pass_rate: ?float}|null
     */
    private function previousTermComparison(Term $term, ?int $sectionId, ?int $gradeLevelId): ?array
    {
        $previous = Term::query()
            ->where('academic_year_id', $term->academic_year_id)
            ->where('sequence', '<', $term->sequence)
            ->orderByDesc('sequence')
            ->first();

        if ($previous === null) {
            return null;
        }

        $results = $this->resultsFor($previous->id, $sectionId, $gradeLevelId)->whereNotNull('average');
        if ($results->isEmpty()) {
            return null;
        }

        $graded = $results->filter(fn ($r) => isset($r->grading['overall']));
        $passing = $graded->filter(fn ($r) => (bool) ($r->grading['overall']['is_passing'] ?? false));

        return [
            'term' => ['id' => $previous->id, 'name' => $previous->name],
            'average' => round((float) $results->avg('average'), 2),
            'pass_rate' => $graded->isEmpty()
                ? null
                : round($passing->count() / $graded->count() * 100, 1),
        ];
    }

    /**
     * Overall grade-band distribution — how many students landed in each
     * snapshotted band, ordered best-first.
     *
     * @param  Collection<int, StudentTermResult>  $results
     * @return list<array{label: string, letter: ?string, is_passing: bool, count: int}>
     */
    private function bandDistribution(Collection $results): array
    {
        return $results
            ->filter(fn ($r) => isset($r->grading['overall']['label']))
            ->groupBy(fn ($r) => $r->grading['overall']['label'])
            ->map(fn (Collection $group, string $label): array => [
                'label' => $label,
                'letter' => $group->first()->grading['overall']['letter'] ?? null,
                'is_passing' => (bool) ($group->first()->grading['overall']['is_passing'] ?? false),
                'count' => $group->count(),
                // For best-first ordering; midpoint of the group's averages.
                'avg' => round((float) $group->avg('average'), 2),
            ])
            ->sortByDesc('avg')
            ->map(fn (array $row): array => collect($row)->except('avg')->all())
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, StudentTermResult>  $results
     * @return list<array<string, mixed>>
     */
    private function sectionStats(Collection $results): array
    {
        return $results
            ->filter(fn ($r) => $r->section !== null)
            ->groupBy('section_id')
            ->map(function (Collection $group): array {
                $passing = $group->filter(fn ($r) => (bool) ($r->grading['overall']['is_passing'] ?? false));
                $section = $group->first()->section;

                return [
                    'section_id' => $section->id,
                    'name' => trim(($section->gradeLevel?->name ?? '').' '.$section->name),
                    'grade_sort' => $section->gradeLevel?->sort_order ?? 0,
                    'students' => $group->count(),
                    'average' => round((float) $group->avg('average'), 2),
                    'pass_rate' => round($passing->count() / max(1, $group->count()) * 100, 1),
                ];
            })
            ->sortBy([['grade_sort', 'asc'], ['name', 'asc']])
            ->map(fn (array $row): array => collect($row)->except('grade_sort')->all())
            ->values()
            ->all();
    }

    /**
     * Per-subject performance across the term, unpacked from the frozen
     * per-subject breakdown snapshots.
     *
     * @param  Collection<int, StudentTermResult>  $results
     * @return list<array<string, mixed>>
     */
    private function subjectStats(Collection $results): array
    {
        $lines = $results->flatMap(fn ($r) => collect($r->breakdown)
            ->filter(fn ($line) => ($line['total'] ?? null) !== null));

        return $lines
            ->groupBy('subject_id')
            ->map(function (Collection $group): array {
                $first = $group->first();
                $passing = $group->filter(fn ($line) => (bool) ($line['is_passing'] ?? false));

                return [
                    'subject_id' => $first['subject_id'],
                    'code' => $first['code'] ?? null,
                    'name' => $first['name'] ?? '—',
                    'students' => $group->count(),
                    'average' => round((float) $group->avg('total'), 2),
                    'pass_rate' => round($passing->count() / max(1, $group->count()) * 100, 1),
                ];
            })
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * Marklist submission progress: every active teaching assignment of the
     * term bucketed by workflow status (no marklist row yet = not started).
     *
     * @return array{total: int, draft: int, submitted: int, approved: int}
     */
    private function marklistProgress(Term $term, ?int $sectionId, ?int $gradeLevelId): array
    {
        $bySection = function ($q) use ($sectionId, $gradeLevelId) {
            $q->when($sectionId !== null, fn ($s) => $s->where('section_id', $sectionId))
                ->when($sectionId === null && $gradeLevelId !== null, fn ($s) => $s->whereHas(
                    'section',
                    fn ($sec) => $sec->where('grade_level_id', $gradeLevelId),
                ));
        };

        $assignments = SubjectAssignment::query()
            ->where('term_id', $term->id)
            ->where('is_active', true)
            ->tap($bySection)
            ->count();

        $statuses = Marklist::query()
            ->where('term_id', $term->id)
            ->when($sectionId !== null || $gradeLevelId !== null, fn ($q) => $q->whereHas(
                'subjectAssignment',
                $bySection,
            ))
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return [
            'total' => $assignments,
            'draft' => (int) ($statuses['draft'] ?? 0) + max(0, $assignments - (int) $statuses->sum()),
            'submitted' => (int) ($statuses['submitted'] ?? 0),
            'approved' => (int) ($statuses['approved'] ?? 0),
        ];
    }

    /**
     * Boys/girls split — a standard line on Ethiopian term reports.
     *
     * @param  Collection<int, StudentTermResult>  $results
     * @return list<array{gender: string, students: int, average: ?float, pass_rate: ?float}>
     */
    private function genderStats(Collection $results): array
    {
        return $results
            ->filter(fn ($r) => $r->student?->gender !== null)
            ->groupBy(fn ($r) => $r->student->gender->value)
            ->map(function (Collection $group, string $gender): array {
                $graded = $group->filter(fn ($r) => isset($r->grading['overall']));
                $passing = $graded->filter(fn ($r) => (bool) ($r->grading['overall']['is_passing'] ?? false));

                return [
                    'gender' => $gender,
                    'students' => $group->count(),
                    'average' => round((float) $group->avg('average'), 2),
                    'pass_rate' => $graded->isEmpty()
                        ? null
                        : round($passing->count() / $graded->count() * 100, 1),
                ];
            })
            ->sortBy('gender')
            ->values()
            ->all();
    }

    /**
     * Students who did NOT pass this term, weakest first — the follow-up list
     * for the director, mirroring topStudents.
     *
     * @param  Collection<int, StudentTermResult>  $results
     * @return list<array<string, mixed>>
     */
    private function atRiskStudents(Collection $results): array
    {
        return $results
            ->filter(fn ($r) => isset($r->grading['overall']) && ! ($r->grading['overall']['is_passing'] ?? false))
            ->sortBy(fn ($r) => (float) $r->average)
            ->take(10)
            ->map(fn ($r): array => [
                'student_id' => $r->student_id,
                'public_id' => $r->student?->public_id,
                'full_name' => $r->student?->full_name,
                'photo_url' => $r->student?->photo_url,
                'section' => $r->section !== null
                    ? trim(($r->section->gradeLevel?->name ?? '').' '.$r->section->name)
                    : null,
                'average' => (float) $r->average,
                'absence_days' => $r->absence_days,
                'letter' => $r->grading['overall']['letter'] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, StudentTermResult>  $results
     * @return list<array<string, mixed>>
     */
    private function topStudents(Collection $results): array
    {
        return $results
            ->sortByDesc(fn ($r) => (float) $r->average)
            ->take(10)
            ->map(fn ($r): array => [
                'student_id' => $r->student_id,
                'public_id' => $r->student?->public_id,
                'full_name' => $r->student?->full_name,
                'photo_url' => $r->student?->photo_url,
                'section' => $r->section !== null
                    ? trim(($r->section->gradeLevel?->name ?? '').' '.$r->section->name)
                    : null,
                'average' => (float) $r->average,
                'rank' => $r->rank,
                'letter' => $r->grading['overall']['letter'] ?? null,
            ])
            ->values()
            ->all();
    }
}
