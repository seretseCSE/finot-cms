<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Audit\UserAuditService;

class AccountLockoutService
{
    /**
     * Increment failed login attempts and apply automatic lockout if threshold is reached.
     */
    public function incrementFailedAttempts(User $user): void
    {
        $user->increment('failed_login_attempts');

        $failedAttempts = $user->failed_login_attempts;
        $lockoutThreshold = config('finot.failed_login_lockout_threshold', 5);

        if ($failedAttempts >= $lockoutThreshold) {
            $lockDuration = ($failedAttempts === $lockoutThreshold) ? 1 : 5;

            $user->update([
                'is_locked' => true,
                'locked_until' => now()->addMinutes($lockDuration),
            ]);

            app(UserAuditService::class)->logFailedLogin($user, 'account_locked', [
                'failed_attempts' => $failedAttempts,
                'lock_duration_minutes' => $lockDuration,
                'locked_until' => $user->locked_until->toDateTimeString(),
            ]);
        }
    }

    /**
     * Reset failed login attempts and clear any automatic lockout.
     */
    public function resetFailedAttempts(User $user): void
    {
        $user->update([
            'failed_login_attempts' => 0,
            'is_locked' => false,
            'locked_until' => null,
        ]);
    }

    /**
     * Get remaining lockout time in minutes.
     */
    public function getRemainingLockoutMinutes(User $user): int
    {
        if (! $user->is_locked || ! $user->locked_until) {
            return 0;
        }

        if ($user->locked_until->isPast()) {
            return 0;
        }

        return max(0, (int) now()->diffInMinutes($user->locked_until));
    }

    /**
     * Get a human-readable lockout message.
     */
    public function getLockoutMessage(User $user): string
    {
        $remainingMinutes = $this->getRemainingLockoutMinutes($user);

        if ($remainingMinutes <= 0) {
            return 'Account is locked. Please try again later.';
        }

        if ($remainingMinutes === 1) {
            return 'Account is locked. Please try again in 1 minute.';
        }

        return "Account is locked. Please try again in {$remainingMinutes} minutes.";
    }

    /**
     * Manually lock a user account (admin action).
     */
    public function manuallyLock(User $user, ?string $reason = null, ?int $adminId = null): void
    {
        $user->update(['is_locked' => true]);

        app(UserAuditService::class)->logFailedLogin($user, 'account_manually_locked', [
            'reason' => $reason ?? 'Manual lock by administrator',
            'admin_id' => $adminId,
            'lock_type' => 'manual',
        ]);
    }

    /**
     * Manually unlock a user account (admin action).
     */
    public function manuallyUnlock(User $user, ?string $reason = null, ?int $adminId = null): void
    {
        $user->update([
            'is_locked' => false,
            'locked_until' => null,
            'failed_login_attempts' => 0,
        ]);

        app(UserAuditService::class)->logFailedLogin($user, 'account_manually_unlocked', [
            'reason' => $reason ?? 'Manual unlock by administrator',
            'admin_id' => $adminId,
            'lock_type' => 'manual',
        ]);
    }

    /**
     * Lock the user account with a specific duration.
     */
    public function lockAccount(User $user, string $reason, string $duration, ?int $lockedBy = null): bool
    {
        $lockedUntil = match ($duration) {
            '1h' => now()->addHour(),
            '24h' => now()->addDay(),
            '7d' => now()->addWeek(),
            'permanent' => null,
            default => now()->addHour(),
        };

        $user->update([
            'is_locked' => true,
            'locked_until' => $lockedUntil,
            'lock_reason' => $reason,
            'locked_by' => $lockedBy,
            'locked_at' => now(),
        ]);

        if (function_exists('activity')) {
            \activity()
                ->causedBy($lockedBy ? User::find($lockedBy) : null)
                ->performedOn($user)
                ->withProperties([
                    'reason' => $reason,
                    'duration' => $duration,
                    'locked_until' => $lockedUntil,
                ])
                ->log('account_locked');
        }

        return true;
    }

    /**
     * Unlock the user account.
     */
    public function unlockAccount(User $user, ?int $unlockedBy = null): bool
    {
        $user->update([
            'is_locked' => false,
            'locked_until' => null,
            'lock_reason' => null,
            'locked_by' => null,
            'locked_at' => null,
        ]);

        if (function_exists('activity')) {
            \activity()
                ->causedBy($unlockedBy ? User::find($unlockedBy) : null)
                ->performedOn($user)
                ->withProperties([
                    'unlocked_at' => now(),
                ])
                ->log('account_unlocked');
        }

        return true;
    }

    /**
     * Check if the account is currently locked, with auto-unlock support.
     */
    public function isCurrentlyLocked(User $user): bool
    {
        if (! $user->is_locked) {
            return false;
        }

        if ($user->locked_until && $user->locked_until->isPast()) {
            $this->unlockAccount($user);

            return false;
        }

        return true;
    }
}
