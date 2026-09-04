<?php

namespace App\Ai\Tools\Leadership;

use App\Models\StudentTermResult;
use App\Models\Term;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The highest / lowest performing students of a term in scope. Sensitive:
 * leadership decision support only — the agent's instructions require
 * framing low performers as students needing SUPPORT, never a shaming list.
 */
class TopBottomStudentsTool extends LeadershipScopedTool
{
    public function description(): Stringable|string
    {
        return 'List the top or bottom N students by term average (optionally per grade), with section and rank. Use for recognition lists or identifying students who need support. Defaults to the latest computed term.';
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

        $direction = $request->string('direction')->toString() === 'bottom' ? 'asc' : 'desc';
        $limit = min(max($request->integer('limit') ?: 10, 1), 25);

        $rows = StudentTermResult::query()
            ->whereIn('branch_id', $branchIds)
            ->where('term_id', $termId)
            ->whereNotNull('average')
            ->when($request->integer('grade_level_id') ?: null, fn ($q, $id) => $q->where('grade_level_id', $id))
            ->with(['student:id,first_name,father_name,grandfather_name', 'section:id,name', 'gradeLevel:id,name'])
            ->orderBy('average', $direction)
            ->limit($limit)
            ->get()
            ->map(fn (StudentTermResult $result): array => [
                'student' => $result->student?->full_name,
                'link' => '/students/'.$result->student_id,
                'grade' => $result->gradeLevel?->name,
                'section' => $result->section?->name,
                'average' => (float) $result->average,
                'rank_in_section' => $result->rank !== null ? $result->rank.'/'.$result->rank_of : null,
                'absence_days' => $result->absence_days,
            ]);

        return $this->ok([
            'term' => Term::query()->find($termId)?->name,
            'direction' => $direction === 'asc' ? 'lowest first' : 'highest first',
            'students' => $rows,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'direction' => $schema->string()->enum(['top', 'bottom'])->description('top = best performers, bottom = students needing support.'),
            'limit' => $schema->integer()->description('How many (max 25, default 10).'),
            'grade_level_id' => $schema->integer()->description('Narrow to one grade level.'),
            'term_id' => $schema->integer()->description('A specific term (default: latest computed).'),
            'branch_id' => $schema->integer()->description('School-wide sessions only: narrow to one branch.'),
        ];
    }
}
