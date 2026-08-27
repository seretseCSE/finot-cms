<?php

namespace App\Services;

use App\Models\GradingPolicy;
use App\Models\GradingScale;
use App\Support\GradingDefaults;
use Illuminate\Support\Collection;

/**
 * Resolves which grading scale + display mode applies to a grade level in a
 * branch: the branch's own policy row wins, then the school-wide row
 * (branch_id null), then the platform percentage scale shown numerically.
 * Loads a scope's policies once and answers per-grade lookups from memory —
 * the term freeze asks for every section of a branch in one pass.
 */
class GradingPolicyResolver
{
    /** @var array<string, Collection<int, GradingPolicy>> */
    private array $policies = [];

    private ?GradingScale $fallback = null;

    /**
     * @return array{scale: GradingScale, display: string, policy: ?GradingPolicy}
     */
    public function resolve(int $schoolId, int $branchId, int $gradeSort): array
    {
        $policies = $this->policiesFor($schoolId, $branchId);

        $match = $policies->first(
            fn (GradingPolicy $p): bool => $p->branch_id === $branchId && $p->appliesToGradeSort($gradeSort)
        ) ?? $policies->first(
            fn (GradingPolicy $p): bool => $p->branch_id === null && $p->appliesToGradeSort($gradeSort)
        );

        if ($match !== null && $match->scale !== null) {
            return ['scale' => $match->scale, 'display' => $match->display, 'policy' => $match];
        }

        return ['scale' => $this->fallbackScale(), 'display' => 'numeric', 'policy' => null];
    }

    /**
     * @return Collection<int, GradingPolicy>
     */
    private function policiesFor(int $schoolId, int $branchId): Collection
    {
        $key = "{$schoolId}:{$branchId}";

        return $this->policies[$key] ??= GradingPolicy::query()
            ->where('school_id', $schoolId)
            ->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId))
            ->with('scale.bands')
            ->get();
    }

    private function fallbackScale(): GradingScale
    {
        if ($this->fallback !== null) {
            return $this->fallback;
        }

        $scale = GradingScale::query()
            ->whereNull('school_id')
            ->where('code', GradingDefaults::FALLBACK_CODE)
            ->with('bands')
            ->first();

        if ($scale === null) {
            GradingDefaults::provision();

            $scale = GradingScale::query()
                ->whereNull('school_id')
                ->where('code', GradingDefaults::FALLBACK_CODE)
                ->with('bands')
                ->firstOrFail();
        }

        return $this->fallback = $scale;
    }
}
