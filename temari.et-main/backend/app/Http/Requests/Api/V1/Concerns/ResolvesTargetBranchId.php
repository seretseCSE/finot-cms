<?php

namespace App\Http\Requests\Api\V1\Concerns;

/**
 * The branch id a branch-anchored request targets: an explicit `branch_id`
 * in the payload (how school managers act from the school-wide workspace)
 * wins over the X-Branch-Id context. Mirrors Controller::targetBranch() —
 * validation rules must judge the same branch the controller will act on.
 */
trait ResolvesTargetBranchId
{
    protected function targetBranchId(): ?int
    {
        return $this->filled('branch_id')
            ? (int) $this->input('branch_id')
            : $this->user()?->activeBranchId();
    }
}
