<?php

namespace App\Ai\Tools\Leadership;

use App\Ai\Tools\AiTool;
use App\Models\Branch;
use App\Models\Term;
use Illuminate\Support\Collection;

/**
 * Base for leadership/registrar/finance analytics tools. Scope = the
 * conversation's school (+ branch when the session was opened in a branch
 * workspace); a school-wide session may narrow per question via the
 * branch_id argument — validated to belong to the school, mirroring
 * Controller::branchFilterId. Every concrete tool re-checks its own kernel
 * permission before touching data, and every read is read-only.
 */
abstract class LeadershipScopedTool extends AiTool
{
    /** Deny unless the caller holds at least one of the permissions in scope. */
    protected function missingPermission(string ...$any): ?string
    {
        foreach ($any as $permission) {
            if ($this->context->allows($permission)) {
                return null;
            }
        }

        return 'You do not have '.$any[0].' access in this context.';
    }

    /** @return Collection<int, int> branch ids in play for a query */
    protected function branchIds(?int $requested = null): Collection
    {
        if ($this->context->branchId() !== null) {
            return collect([$this->context->branchId()]);
        }

        $all = Branch::query()
            ->where('school_id', $this->context->schoolId())
            ->pluck('id');

        if ($requested !== null && $all->contains($requested)) {
            return collect([$requested]);
        }

        return $all;
    }

    /** Current terms across the branches in play (one per branch). */
    protected function currentTermIds(?int $requestedBranch = null): Collection
    {
        return Term::query()
            ->whereIn('branch_id', $this->branchIds($requestedBranch))
            ->where('is_current', true)
            ->pluck('id');
    }
}
