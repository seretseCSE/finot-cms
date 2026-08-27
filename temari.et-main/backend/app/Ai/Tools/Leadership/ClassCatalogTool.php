<?php

namespace App\Ai\Tools\Leadership;

use App\Models\SubjectAssignment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Exam-authoring discovery for the leadership lane: the classes and subjects
 * ACTIVELY taught this semester in scope, with the exact section_id /
 * subject_id pairs CreateExamTool takes. Read-only; requires lms.manage —
 * the same supervisory permission the exam studio uses.
 */
class ClassCatalogTool extends LeadershipScopedTool
{
    public function description(): Stringable|string
    {
        return 'List the classes taught this semester (branch, grade, section, subject, teacher — with the section_id and subject_id CreateExamTool needs). Call this before building an exam. Narrow with branch_id (school-wide sessions), grade_level_id and/or subject_id — big schools need a filter.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($reason = $this->missingPermission('lms.manage')) {
            return $this->deny($reason);
        }

        $input = $request->all();
        $branchId = (int) ($input['branch_id'] ?? 0);
        $gradeLevelId = (int) ($input['grade_level_id'] ?? 0);
        $subjectId = (int) ($input['subject_id'] ?? 0);

        $rows = SubjectAssignment::query()
            ->whereIn('term_id', $this->currentTermIds($branchId > 0 ? $branchId : null))
            ->where('is_active', true)
            ->when($gradeLevelId > 0, fn ($q) => $q->whereHas('section', fn ($s) => $s->where('grade_level_id', $gradeLevelId)))
            ->when($subjectId > 0, fn ($q) => $q->where('subject_id', $subjectId))
            ->with([
                'subject:id,name', 'branch:id,name',
                'section:id,name,grade_level_id', 'section.gradeLevel:id,name',
                'employee:id,first_name,father_name',
            ])
            ->orderBy('section_id')
            ->limit(120)
            ->get();

        if ($rows->isEmpty()) {
            return $this->deny('No classes with subject teachers found for the current semester here — subject–teacher assignments are set up per semester on the Semesters page (/semesters). Check there, or try another branch.');
        }

        return $this->ok([
            'classes' => $rows->map(fn (SubjectAssignment $a): array => [
                'section_id' => $a->section_id,
                'subject_id' => $a->subject_id,
                'grade_level_id' => $a->section?->grade_level_id,
                'branch' => $a->branch?->name,
                'grade' => $a->section?->gradeLevel?->name,
                'section' => $a->section?->name,
                'subject' => $a->subject?->name,
                'teacher' => trim(($a->employee?->first_name ?? '').' '.($a->employee?->father_name ?? '')),
            ]),
            'note' => $rows->count() === 120 ? 'Long list — truncated at 120 rows. Narrow with grade_level_id or subject_id.' : null,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'branch_id' => $schema->integer()->description('School-wide sessions only: narrow to one branch.'),
            'grade_level_id' => $schema->integer()->description('Narrow to one grade level (use grade_level_id values from earlier results).'),
            'subject_id' => $schema->integer()->description('Narrow to one subject.'),
        ];
    }
}
