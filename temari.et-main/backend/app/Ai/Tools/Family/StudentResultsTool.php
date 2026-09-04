<?php

namespace App\Ai\Tools\Family;

use App\Models\AssessmentResult;
use App\Models\StudentTermResult;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Academic results for the student/child: frozen term report cards (average,
 * rank, per-subject breakdown) plus the current term's running continuous
 * assessment marks. The per-subject data is what weakness analysis and
 * study-plan answers must be grounded in.
 */
class StudentResultsTool extends StudentScopedTool
{
    public function description(): Stringable|string
    {
        return 'Get academic results: frozen report cards per term (average, rank, per-subject totals and grade letters) and current-term continuous assessment scores per subject. Use for any question about marks, performance, weak/strong subjects, or how to improve.';
    }

    public function handle(Request $request): Stringable|string
    {
        [$student, $link, $denial] = $this->resolveStudent($request->integer('student_id') ?: null);

        if ($denial !== null) {
            return $this->deny($denial);
        }

        if (! $this->linkAllows($link, 'can_view_grades')) {
            return $this->deny('Your guardian link does not permit viewing this student\'s grades.');
        }

        $terms = StudentTermResult::query()
            ->where('student_id', $student->id)
            ->with(['term:id,name,academic_year_id', 'section:id,name', 'gradeLevel:id,name'])
            ->orderByDesc('term_id')
            ->limit(6)
            ->get()
            ->map(fn (StudentTermResult $result): array => [
                'term' => $result->term?->name,
                'grade' => $result->gradeLevel?->name,
                'section' => $result->section?->name,
                'average' => $result->average !== null ? (float) $result->average : null,
                'rank' => $result->rank,
                'rank_of' => $result->rank_of,
                'absence_days' => $result->absence_days,
                'subjects' => collect($result->breakdown ?? [])->map(fn (array $subject): array => [
                    'subject' => $subject['name'] ?? null,
                    'total' => $subject['total'] ?? null,
                    'letter' => $subject['letter'] ?? null,
                    'passing' => $subject['is_passing'] ?? null,
                ])->values(),
            ]);

        // Current term running marks (not yet frozen): per assessment.
        $enrollment = $student->currentEnrollment;
        $running = [];

        if ($enrollment !== null && $enrollment->section_id !== null) {
            $term = $this->context->currentTerm($enrollment->branch_id);

            if ($term !== null) {
                $running = AssessmentResult::query()
                    ->where('student_id', $student->id)
                    ->whereHas('assessment.subjectAssignment', fn ($q) => $q
                        ->where('term_id', $term->id)
                        ->where('section_id', $enrollment->section_id))
                    ->with(['assessment:id,subject_assignment_id,name,max_score,weight', 'assessment.subjectAssignment.subject:id,name'])
                    ->limit(120)
                    ->get()
                    ->groupBy(fn (AssessmentResult $r) => $r->assessment?->subjectAssignment?->subject?->name ?? 'Unknown')
                    ->map(fn ($results, string $subject): array => [
                        'subject' => $subject,
                        'assessments' => $results->map(fn (AssessmentResult $r): array => [
                            'name' => $r->assessment?->name,
                            'score' => $r->is_absent ? null : ($r->score !== null ? (float) $r->score : null),
                            'max' => $r->assessment?->max_score !== null ? (float) $r->assessment->max_score : null,
                            'absent' => (bool) $r->is_absent,
                        ])->values(),
                    ])
                    ->values();
            }
        }

        return $this->ok([
            'student' => $student->full_name,
            'report_cards' => $terms,
            'current_term_marks' => $running,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'student_id' => $schema->integer()->description('Parent lane only: the child to look at (from my_children). Omit in the student lane.'),
        ];
    }
}
