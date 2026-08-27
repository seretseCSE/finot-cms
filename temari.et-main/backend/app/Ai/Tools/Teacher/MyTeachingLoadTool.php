<?php

namespace App\Ai\Tools\Teacher;

use App\Models\SectionHomeroom;
use App\Models\SubjectAssignment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The teacher's own classes: subject assignments (with section/grade/term
 * ids other tools take as filters) and homeroom sections.
 */
class MyTeachingLoadTool extends TeacherScopedTool
{
    public function description(): Stringable|string
    {
        return 'List the teacher\'s own classes: each subject assignment (section, grade, subject, term, section_id/subject_id for the other tools) and any homeroom sections. Call this first to know which classes the teacher has.';
    }

    public function handle(Request $request): Stringable|string
    {
        $assignments = $this->ownAssignments()
            ->map(fn (SubjectAssignment $assignment): array => [
                'section_id' => $assignment->section_id,
                'subject_id' => $assignment->subject_id,
                'section' => $assignment->section?->name,
                'grade' => $assignment->section?->gradeLevel?->name,
                'subject' => $assignment->subject?->name,
                'term' => $assignment->term?->name,
                'term_is_current' => (bool) $assignment->term?->is_current,
                'periods_per_week' => $assignment->periods_per_week,
            ]);

        $homerooms = SectionHomeroom::query()
            ->whereIn('employee_id', $this->employeeIds())
            ->whereHas('academicYear', fn ($q) => $q->where('status', 'active'))
            ->with('section:id,name,grade_level_id', 'section.gradeLevel:id,name')
            ->get()
            ->map(fn (SectionHomeroom $homeroom): array => [
                'section_id' => $homeroom->section_id,
                'section' => $homeroom->section?->name,
                'grade' => $homeroom->section?->gradeLevel?->name,
            ]);

        if ($assignments->isEmpty() && $homerooms->isEmpty()) {
            return $this->deny('No teaching assignments or homerooms found for you in this school context.');
        }

        return $this->ok(['assignments' => $assignments, 'homerooms' => $homerooms]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
