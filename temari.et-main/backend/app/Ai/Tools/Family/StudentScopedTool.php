<?php

namespace App\Ai\Tools\Family;

use App\Ai\Tools\AiTool;
use App\Enums\AiLane;
use App\Models\Student;
use App\Models\StudentGuardian;

/**
 * Base for every family-lane tool that reads ONE student's data. Resolves
 * the subject student the same way the /me lane does (ADR-012): the user's
 * own student row in the student lane; in the parent lane an active
 * guardian link, per-capability gated by the link's flags. `student_id` from
 * the model is accepted only in the parent lane and only when a link exists.
 */
abstract class StudentScopedTool extends AiTool
{
    /**
     * @return array{0: Student|null, 1: StudentGuardian|null, 2: string|null} student, link (parent lane), denial reason
     */
    protected function resolveStudent(?int $studentId = null): array
    {
        if ($this->context->lane === AiLane::Student) {
            $student = $this->context->user->studentProfile()->first();

            return $student !== null
                ? [$student, null, null]
                : [null, null, 'No student record is linked to this account.'];
        }

        $parent = $this->context->user->parentProfile()->first();

        if ($parent === null) {
            return [null, null, 'No parent profile is linked to this account.'];
        }

        $links = StudentGuardian::query()
            ->where('parent_id', $parent->id)
            ->where('is_active', true)
            ->with('student.currentEnrollment')
            ->get();

        $studentId ??= $this->context->student?->id;

        $link = $studentId !== null
            ? $links->firstWhere('student_id', $studentId)
            : ($links->count() === 1 ? $links->first() : null);

        if ($link === null) {
            return [null, null, $studentId !== null
                ? 'This student is not linked to your account.'
                : 'Multiple children are linked — specify which child (use the my_children tool to list them).'];
        }

        return [$link->student, $link, null];
    }

    /** Gate a capability on the guardian link (student lane always passes its own data). */
    protected function linkAllows(?StudentGuardian $link, string $flag): bool
    {
        return $link === null || (bool) $link->{$flag};
    }
}
