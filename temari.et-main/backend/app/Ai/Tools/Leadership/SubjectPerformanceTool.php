<?php

namespace App\Ai\Tools\Leadership;

use App\Models\StudentTermResult;
use App\Models\Term;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Which subjects are strong/weak: average per subject (optionally per
 * grade) aggregated in Postgres straight out of the frozen term-result
 * breakdown JSONB — never from model memory.
 */
class SubjectPerformanceTool extends LeadershipScopedTool
{
    public function description(): Stringable|string
    {
        return 'Average score and pass rate per SUBJECT (optionally split by grade) for a term, from frozen report-card data. Use for "which subject is weakest", "how is Mathematics doing", or subject comparisons. Defaults to the most recent computed term.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (($denied = $this->missingPermission('reports.view', 'grades.view', 'grades.manage')) !== null) {
            return $this->deny($denied);
        }

        $branchIds = $this->branchIds($request->integer('branch_id') ?: null);

        $termId = $request->integer('term_id') ?: null;

        if ($termId !== null) {
            $valid = Term::query()->where('id', $termId)->whereIn('branch_id', $branchIds)->exists();
            if (! $valid) {
                return $this->deny('That term is outside this school.');
            }
        } else {
            // Most recent term that actually has frozen results in scope.
            $termId = StudentTermResult::query()
                ->whereIn('branch_id', $branchIds)
                ->max('term_id');
        }

        if ($termId === null) {
            return $this->deny('No computed term results yet — results freeze when a term closes.');
        }

        $splitByGrade = $request->boolean('by_grade');

        $placeholders = implode(',', array_fill(0, $branchIds->count(), '?'));
        $gradeSelect = $splitByGrade ? 'g.name as grade,' : '';
        $gradeGroup = $splitByGrade ? 'g.name,' : '';

        $rows = collect(DB::select(
            "select {$gradeSelect}
                    elem->>'name' as subject_name,
                    round(avg((elem->>'total')::numeric), 2) as average,
                    count(*) as students,
                    count(*) filter (where (elem->>'is_passing')::boolean) as passing
             from student_term_results r
             join grade_levels g on g.id = r.grade_level_id
             cross join lateral jsonb_array_elements(r.breakdown) as elem
             where r.branch_id in ({$placeholders})
               and r.term_id = ?
               and elem->>'total' is not null
             group by {$gradeGroup} elem->>'name'
             order by average asc
             limit 80",
            [...$branchIds->all(), $termId],
        ))->map(fn ($row): array => array_filter([
            'grade' => $splitByGrade ? $row->grade : null,
            'subject' => $row->subject_name,
            'average' => (float) $row->average,
            'students' => (int) $row->students,
            'pass_rate_percent' => (int) $row->students > 0 ? round($row->passing * 100 / $row->students, 1) : null,
        ], fn ($v) => $v !== null));

        if ($rows->isEmpty()) {
            return $this->deny('No frozen results for that term in this scope.');
        }

        $term = Term::query()->find($termId);

        return $this->ok([
            'term' => $term?->name,
            'ordered' => 'weakest subject first',
            'subjects' => $rows,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'term_id' => $schema->integer()->description('A specific term (default: latest computed).'),
            'by_grade' => $schema->boolean()->description('Split rows per grade level.'),
            'branch_id' => $schema->integer()->description('School-wide sessions only: narrow to one branch.'),
        ];
    }
}
