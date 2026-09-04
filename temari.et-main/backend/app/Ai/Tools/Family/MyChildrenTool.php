<?php

namespace App\Ai\Tools\Family;

use App\Ai\Tools\AiTool;
use App\Models\StudentGuardian;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Parent lane only: the children linked to this guardian and what each link
 * permits — the model uses the returned student_id for the child tools.
 */
class MyChildrenTool extends AiTool
{
    public function description(): Stringable|string
    {
        return 'List the children linked to the parent account: student_id, name, grade, section, school, and which data (grades, attendance, fees) the guardian link permits.';
    }

    public function handle(Request $request): Stringable|string
    {
        $parent = $this->context->user->parentProfile()->first();

        if ($parent === null) {
            return $this->deny('No parent profile is linked to this account.');
        }

        $links = StudentGuardian::query()
            ->where('parent_id', $parent->id)
            ->where('is_active', true)
            ->with(['student.currentEnrollment.gradeLevel', 'student.currentEnrollment.section', 'student.currentEnrollment.branch.school'])
            ->get();

        return $this->ok($links->map(function (StudentGuardian $link): array {
            $student = $link->student;
            $enrollment = $student?->currentEnrollment;

            return [
                'student_id' => $student?->id,
                'name' => $student?->full_name,
                'grade' => $enrollment?->gradeLevel?->name,
                'section' => $enrollment?->section?->name,
                'school' => $enrollment?->branch?->school?->name,
                'branch' => $enrollment?->branch?->name,
                'enrollment_status' => $enrollment?->status,
                'can_view_grades' => (bool) $link->can_view_grades,
                'can_view_attendance' => (bool) $link->can_view_attendance,
                'can_view_fees' => (bool) $link->can_pay_fees,
            ];
        })->values());
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
