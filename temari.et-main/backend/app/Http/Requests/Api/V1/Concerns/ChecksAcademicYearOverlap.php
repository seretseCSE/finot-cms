<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Models\AcademicYear;

/**
 * A branch runs one academic year at a time — their date windows must never
 * overlap. Two years covering the same days would make every term-anchored
 * lookup (fees, results, "current year") ambiguous.
 */
trait ChecksAcademicYearOverlap
{
    /**
     * The first existing year in the branch whose window overlaps the requested
     * [starts_on, ends_on], or null when the dates are clear (or unresolvable).
     */
    protected function overlappingAcademicYear(?int $branchId, ?int $ignoreId = null): ?AcademicYear
    {
        $startsOn = $this->input('starts_on');
        $endsOn = $this->input('ends_on');

        if (! $branchId || ! $startsOn || ! $endsOn) {
            return null;
        }

        // Two windows overlap iff each starts on or before the other ends.
        return AcademicYear::query()
            ->where('branch_id', $branchId)
            ->whereDate('starts_on', '<=', $endsOn)
            ->whereDate('ends_on', '>=', $startsOn)
            ->when($ignoreId, fn ($q, $id) => $q->whereKeyNot($id))
            ->orderBy('starts_on')
            ->first();
    }

    protected function academicYearOverlapMessage(AcademicYear $year): string
    {
        return sprintf(
            'These dates overlap with "%s" (%s – %s). Academic years in the same branch cannot overlap.',
            $year->name,
            $year->starts_on?->format('M j, Y'),
            $year->ends_on?->format('M j, Y'),
        );
    }
}
