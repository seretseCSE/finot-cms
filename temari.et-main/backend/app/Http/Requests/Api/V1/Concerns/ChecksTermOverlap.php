<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Models\AcademicYear;
use App\Models\SchoolProgram;
use App\Models\Term;

/**
 * Semesters of the SAME education program (same branch program row) run
 * back-to-back, never on top of each other — overlapping windows would make a
 * date belong to two "current" semesters at once. Different programs may share
 * dates; a semester without a date window is exempt (nothing to compare).
 */
trait ChecksTermOverlap
{
    /**
     * The first existing semester of the same program whose window overlaps the
     * requested [starts_on, ends_on], or null when clear / unresolvable.
     */
    protected function overlappingTerm(AcademicYear $year, ?int $ignoreTermId = null): ?Term
    {
        $startsOn = $this->input('starts_on');
        $endsOn = $this->input('ends_on');

        if (! $startsOn || ! $endsOn) {
            return null;
        }

        $programId = $this->resolveExistingProgramId($year, $ignoreTermId);
        if (! $programId) {
            // A program the branch does not run yet has no semesters to clash with.
            return null;
        }

        return Term::query()
            ->where('school_program_id', $programId)
            ->whereNotNull('starts_on')
            ->whereNotNull('ends_on')
            ->whereDate('starts_on', '<=', $endsOn)
            ->whereDate('ends_on', '>=', $startsOn)
            ->when($ignoreTermId, fn ($q, $id) => $q->whereKeyNot($id))
            ->orderBy('starts_on')
            ->first();
    }

    protected function termOverlapMessage(Term $term): string
    {
        return sprintf(
            'These dates overlap with "%s" (%s – %s). Semesters of the same program cannot overlap.',
            $term->name,
            $term->starts_on?->format('M j, Y'),
            $term->ends_on?->format('M j, Y'),
        );
    }

    /**
     * The branch program the semester will belong to — lookup only, mirroring
     * TermController::resolveProgramId without creating anything (a brand-new
     * program has no semesters to overlap).
     */
    private function resolveExistingProgramId(AcademicYear $year, ?int $ignoreTermId): ?int
    {
        $type = $this->input('program_type');

        if ($type) {
            return SchoolProgram::query()
                ->where('branch_id', $year->branch_id)
                ->where('type', $type)
                ->value('id');
        }

        // No program_type on an edit → keep the semester's current program.
        if ($ignoreTermId) {
            return Term::query()->whereKey($ignoreTermId)->value('school_program_id');
        }

        // No program_type on create → controller falls back to the first active.
        return SchoolProgram::query()
            ->where('branch_id', $year->branch_id)
            ->where('is_active', true)
            ->orderBy('id')
            ->value('id');
    }
}
