<?php

namespace App\Ai\Tools\Leadership;

use App\Models\StudentTermResult;
use App\Models\Term;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Section-vs-section comparison for a term: average of averages, pass-band
 * spread, absence days — the "is 9A really better than 9B" answer.
 */
class SectionComparisonTool extends LeadershipScopedTool
{
    public function description(): Stringable|string
    {
        return 'Compare SECTIONS for a term: per section — student count, mean average, highest/lowest average, and mean absence days. Use for section comparisons or ranking sections. Defaults to the latest computed term.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (($denied = $this->missingPermission('reports.view', 'grades.view', 'grades.manage')) !== null) {
            return $this->deny($denied);
        }

        $branchIds = $this->branchIds($request->integer('branch_id') ?: null);

        $termId = $request->integer('term_id') ?: StudentTermResult::query()
            ->whereIn('branch_id', $branchIds)
            ->max('term_id');

        if ($termId === null) {
            return $this->deny('No computed term results yet.');
        }

        $rows = StudentTermResult::query()
            ->whereIn('student_term_results.branch_id', $branchIds)
            ->where('term_id', $termId)
            ->whereNotNull('average')
            ->join('sections', 'sections.id', '=', 'student_term_results.section_id')
            ->join('grade_levels', 'grade_levels.id', '=', 'student_term_results.grade_level_id')
            ->selectRaw('sections.name as section, grade_levels.name as grade, grade_levels.sort_order,
                count(*) as students,
                round(avg(average), 2) as mean_average,
                round(max(average), 2) as best,
                round(min(average), 2) as worst,
                round(avg(coalesce(absence_days, 0)), 1) as mean_absence_days')
            ->groupBy('sections.name', 'grade_levels.name', 'grade_levels.sort_order')
            ->orderBy('grade_levels.sort_order')
            ->orderByDesc('mean_average')
            ->limit(60)
            ->get()
            ->map(fn ($row): array => [
                'grade' => $row->grade,
                'section' => $row->section,
                'students' => (int) $row->students,
                'mean_average' => (float) $row->mean_average,
                'best_average' => (float) $row->best,
                'worst_average' => (float) $row->worst,
                'mean_absence_days' => (float) $row->mean_absence_days,
            ]);

        if ($rows->isEmpty()) {
            return $this->deny('No frozen results for that term in this scope.');
        }

        return $this->ok([
            'term' => Term::query()->find($termId)?->name,
            'sections' => $rows,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'term_id' => $schema->integer()->description('A specific term (default: latest computed).'),
            'branch_id' => $schema->integer()->description('School-wide sessions only: narrow to one branch.'),
        ];
    }
}
