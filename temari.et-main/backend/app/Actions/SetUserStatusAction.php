<?php

namespace App\Actions;

use App\Enums\AccountStatus;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;

/**
 * Changes a user's GLOBAL account status (active/inactive/banned). Platform-only
 * concern. When the new status revokes access, all of the target's access tokens
 * are deleted so the change takes effect immediately across every live session.
 */
class SetUserStatusAction
{
    public function execute(User $target, AccountStatus $status, User $actor, ?string $reason = null): User
    {
        return DB::transaction(function () use ($target, $status, $actor, $reason): User {
            $previous = $target->status;

            $target->forceFill([
                'status' => $status,
                'status_changed_at' => now(),
                'status_changed_by' => $actor->id,
                'status_reason' => $reason,
            ])->save();

            // Revoke live sessions immediately when access is withdrawn.
            if (! $status->grantsAccess()) {
                $target->tokens()->delete();
            }

            ActivityLogger::log($actor, 'user.status_changed', $target, [
                'from' => $previous?->value,
                'to' => $status->value,
                'reason' => $reason,
            ]);

            return $target;
        });
    }
}
