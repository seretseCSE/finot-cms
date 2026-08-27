<?php

namespace App\Policies;

use App\Models\LeaveRequest;
use App\Models\User;

/**
 * Two lanes, mirroring the rest of HR: supervisors (`leave.view` /
 * `leave.manage`) see and decide any request in their scope; every staff
 * member (`leave.request_own`) owns their personal requests — submit, view,
 * cancel while still pending.
 */
class LeaveRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasContextPermission('leave.view')
            || $user->hasContextPermission('leave.request_own');
    }

    public function view(User $user, LeaveRequest $request): bool
    {
        return ($user->hasPermissionForScope('leave.view', $request->school_id, $request->branch_id)
                && $user->operatesInBranch($request->branch))
            || $this->owns($user, $request);
    }

    /** Approve / reject. */
    public function decide(User $user, LeaveRequest $request): bool
    {
        return $user->hasPermissionForScope('leave.manage', $request->school_id, $request->branch_id)
            && $user->operatesInBranch($request->branch);
    }

    /** Managers cancel anything undecided; owners cancel their own pending requests. */
    public function cancel(User $user, LeaveRequest $request): bool
    {
        return $this->decide($user, $request)
            || ($this->owns($user, $request) && $request->isPending());
    }

    public function delete(User $user, LeaveRequest $request): bool
    {
        return $this->decide($user, $request);
    }

    private function owns(User $user, LeaveRequest $request): bool
    {
        return $request->employee?->user_id === $user->id
            && $user->hasPermissionForScope('leave.request_own', $request->school_id, $request->branch_id);
    }
}
