<?php

namespace App\Ai\Tools\Teacher;

use App\Models\Assessment;
use App\Models\SubjectAssignment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * How one of the teacher's OWN classes is doing: per-assessment averages,
 * score distribution and the students at the bottom — the raw material for
 * "which topic went badly" and intervention advice.
 */
class ClassPerformanceTool extends TeacherScopedTool
{
    public function description(): Stringable|string
    {
        return 'Analyze one of the teacher\'s own classes (section_id + optional subject_id from my_teaching_load): per-assessment average/max/min, how many scored below half, and the lowest-scoring students per assessment. Use for questions about class performance or weak topics.';
    }

    public function handle(Request $request): Stringable|string
    {
        $sectionId = $request->integer('section_id');

        if ($sectionId === 0) {
            return $this->deny('section_id is required (see my_teaching_load).');
        }

        $assignments = $this->ownAssignments($sectionId, $request->integer('subject_id') ?: null)
            ->filter(fn (SubjectAssignment $a) => (bool) $a->term?->is_current || $request->boolean('include_past_terms'));

        if ($assignments->isEmpty()) {
            return $this->deny('That class is not one of your own assignments.');
        }

        $assessments = Assessment::query()
            ->whereIn('subject_assignment_id', $assignments->pluck('id'))
            ->with(['results.student:id,first_name,father_name,grandfather_name'])
            ->orderBy('conducted_on')
            ->limit(40)
            ->get()
            ->map(function (Assessment $assessment): array {
                $scored = $assessment->results->filter(fn ($r) => ! $r->is_absent && $r->score !== null);
                $max = (float) $assessment->max_score;

                return [
                    'assessment' => $assessment->name,
                    'type' => $assessment->type,
                    'max_score' => $max,
                    'weight' => (float) $assessment->weight,
                    'conducted_on' => $assessment->conducted_on?->toDateString(),
                    'graded_count' => $scored->count(),
                    'absent_count' => $assessment->results->where('is_absent', true)->count(),
                    'average' => $scored->isEmpty() ? null : round((float) $scored->avg('score'), 2),
                    'highest' => $scored->isEmpty() ? null : (float) $scored->max('score'),
                    'lowest' => $scored->isEmpty() ? null : (float) $scored->min('score'),
                    'below_half_count' => $max > 0 ? $scored->filter(fn ($r) => (float) $r->score < $max / 2)->count() : null,
                    'lowest_students' => $scored->sortBy('score')->take(5)->map(fn ($r): array => [
                        'student' => $r->student?->full_name,
                        'score' => (float) $r->score,
                    ])->values(),
                ];
            });

        return $this->ok([
            'section' => $assignments->first()->section?->name,
            'subjects' => $assignments->pluck('subject.name')->unique()->values(),
            'assessments' => $assessments,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'section_id' => $schema->integer()->required()->description('The class section (from my_teaching_load).'),
            'subject_id' => $schema->integer()->description('Narrow to one subject.'),
            'include_past_terms' => $schema->boolean()->description('Include closed terms (default: current term only).'),
        ];
    }
}
