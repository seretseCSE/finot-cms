<?php

namespace App\Ai\Tools\Teacher;

use App\Ai\Tools\AiTool;
use App\Models\Employee;
use App\Models\SubjectAssignment;
use Illuminate\Support\Collection;

/**
 * Base for teacher-lane tools. Teachers act through OWNERSHIP (ADR-010):
 * every read here is bounded to the caller's own subject assignments and
 * homeroom sections in the conversation's school/branch scope — never a
 * branch-wide read. Supervisory questions belong to the leadership lane.
 */
abstract class TeacherScopedTool extends AiTool
{
    /** @return Collection<int, int> employee ids of this user in scope */
    protected function employeeIds(): Collection
    {
        return Employee::query()
            ->where('user_id', $this->context->user->id)
            ->where('school_id', $this->context->schoolId())
            ->when($this->context->branchId() !== null, fn ($q) => $q->where('branch_id', $this->context->branchId()))
            ->pluck('id');
    }

    /** Own ACTIVE subject assignments, optionally narrowed, with relations. */
    protected function ownAssignments(?int $sectionId = null, ?int $subjectId = null): Collection
    {
        return SubjectAssignment::query()
            ->whereIn('employee_id', $this->employeeIds())
            ->where('is_active', true)
            ->when($sectionId !== null, fn ($q) => $q->where('section_id', $sectionId))
            ->when($subjectId !== null, fn ($q) => $q->where('subject_id', $subjectId))
            ->with(['subject:id,code,name', 'section:id,name,grade_level_id', 'section.gradeLevel:id,name', 'term:id,name,is_current,status'])
            ->get();
    }
}
