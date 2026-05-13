<?php

namespace App\Services;

use App\Models\User;

class AccountLockService
{
    /**
     * Check if user account is locked (either manual or automatic).
     *
     * @param User $user The user to check
     * @return bool Whether the account is locked
     */
    public static function isAccountLocked(User $user): bool
    {
        return $user->is_locked || ($user->locked_until && $user->locked_until->isFuture());
    }

    /**
     * Check if user is manually locked (admin action).
     *
     * @param User $user The user to check
     * @return bool Whether the account is manually locked
     */
    public static function isManuallyLocked(User $user): bool
    {
        return $user->is_locked;
    }

    /**
     * Check if user is automatically locked (failed attempts).
     *
     * @param User $user The user to check
     * @return bool Whether the account is automatically locked
     */
    public static function isAutomaticallyLocked(User $user): bool
    {
        return !$user->is_locked && $user->locked_until && $user->locked_until->isFuture();
    }

    /**
     * Check if account is currently locked.
     *
     * @param User $user The user to check
     * @return bool Whether the account is currently locked
     */
    public static function isCurrentlyLocked(User $user): bool
    {
        if (!$user->is_locked) {
            return false;
        }

        if ($user->locked_until && $user->locked_until->isPast()) {
            // Auto-unlock if lock time has passed
            self::unlockAccount($user);

            return false;
        }

        return true;
    }

    /**
     * Get lock status message.
     *
     * @param User $user The user to check
     * @return string The lock status message
     */
    public static function getLockStatusMessage(User $user): string
    {
        if (self::isManuallyLocked($user)) {
            return 'Account disabled. Please contact administrator.';
        }

        if (self::isAutomaticallyLocked($user)) {
            return self::getLockoutMessage($user);
        }

        return '';
    }

    /**
     * Manually lock user account.
     *
     * @param User $user The user to lock
     * @param string|null $reason The reason for locking
     * @param int|null $adminId The admin ID performing the lock
     * @return void
     */
    public static function manuallyLock(User $user, ?string $reason = null, ?int $adminId = null): void
    {
        $user->update(['is_locked' => true]);

        // Log manual lock action
        self::logFailedLogin($user, 'account_manually_locked', [
            'reason' => $reason ?? 'Manual lock by administrator',
            'admin_id' => $adminId,
            'lock_type' => 'manual',
        ]);
    }

    /**
     * Manually unlock user account.
     *
     * @param User $user The user to unlock
     * @param string|null $reason The reason for unlocking
     * @param int|null $adminId The admin ID performing the unlock
     * @return void
     */
    public static function manuallyUnlock(User $user, ?string $reason = null, ?int $adminId = null): void
    {
        $user->update([
            'is_locked' => false,
            'locked_until' => null, // Clear automatic lockout as well
            'failed_login_attempts' => 0, // Reset failed attempts
        ]);

        // Log manual unlock action
        self::logFailedLogin($user, 'account_manually_unlocked', [
            'reason' => $reason ?? 'Manual unlock by administrator',
            'admin_id' => $adminId,
            'lock_type' => 'manual',
        ]);
    }

    /**
     * Reset failed login attempts.
     *
     * @param User $user The user to reset
     * @return void
     */
    public static function resetFailedAttempts(User $user): void
    {
        $user->update([
            'failed_login_attempts' => 0,
            'is_locked' => false,
            'locked_until' => null,
        ]);
    }

    /**
     * Increment failed login attempts and lock if necessary.
     *
     * @param User $user The user to increment attempts for
     * @return void
     */
    public static function incrementFailedAttempts(User $user): void
    {
        $user->increment('failed_login_attempts');

        // Progressive locking: 1 minute for first group, 5 minutes for subsequent groups
        $failedAttempts = $user->fresh()->failed_login_attempts;

        $lockoutThreshold = config('finot.failed_login_lockout_threshold', 5);

        if ($failedAttempts >= $lockoutThreshold) {
            $lockDuration = ($failedAttempts === $lockoutThreshold) ? 1 : 5; // 1 minute for first group, 5 for subsequent
            $user->update([
                'is_locked' => true,
                'locked_until' => now()->addMinutes($lockDuration),
            ]);

            // Log lockout event
            self::logFailedLogin($user, 'account_locked', [
                'failed_attempts' => $failedAttempts,
                'lock_duration_minutes' => $lockDuration,
                'locked_until' => $user->locked_until->toDateTimeString(),
            ]);
        }
    }

    /**
     * Lock user account with specified duration.
     *
     * @param User $user The user to lock
     * @param string $reason The reason for locking
     * @param string $duration The lock duration (1h, 24h, 7d, permanent)
     * @param int|null $lockedBy The user ID performing the lock
     * @return bool Whether the lock was successful
     */
    public static function lockAccount(User $user, string $reason, string $duration, ?int $lockedBy = null): bool
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

        // Log lock action
        activity()
            ->causedBy($lockedBy ? User::find($lockedBy) : null)
            ->performedOn($user)
            ->withProperties([
                'reason' => $reason,
                'duration' => $duration,
                'locked_until' => $lockedUntil,
            ])
            ->log('account_locked');

        return true;
    }

    /**
     * Unlock user account.
     *
     * @param User $user The user to unlock
     * @param int|null $unlockedBy The user ID performing the unlock
     * @return bool Whether the unlock was successful
     */
    public static function unlockAccount(User $user, ?int $unlockedBy = null): bool
    {
        $user->update([
            'is_locked' => false,
            'locked_until' => null,
            'lock_reason' => null,
            'locked_by' => null,
            'locked_at' => null,
        ]);

        // Log unlock action
        activity()
            ->causedBy($unlockedBy ? User::find($unlockedBy) : null)
            ->performedOn($user)
            ->withProperties([
                'unlocked_at' => now(),
            ])
            ->log('account_unlocked');

        return true;
    }

    /**
     * Get remaining lockout time in minutes.
     *
     * @param User $user The user to check
     * @return int The remaining lockout minutes
     */
    public static function getRemainingLockoutMinutes(User $user): int
    {
        if (!$user->is_locked || !$user->locked_until) {
            return 0;
        }

        if ($user->locked_until->isPast()) {
            return 0;
        }

        return now()->diffInMinutes($user->locked_until);
    }

    /**
     * Get formatted lockout message.
     *
     * @param User $user The user to check
     * @return string The formatted lockout message
     */
    public static function getLockoutMessage(User $user): string
    {
        $remainingMinutes = self::getRemainingLockoutMinutes($user);

        if ($remainingMinutes <= 0) {
            return 'Account is locked. Please try again later.';
        }

        if ($remainingMinutes === 1) {
            return 'Account is locked. Please try again in 1 minute.';
        }

        return "Account is locked. Please try again in {$remainingMinutes} minutes.";
    }

    /**
     * Log failed login attempt to audit log
     */
    private static function logFailedLogin(User $user, string $event, array $context = []): void
    {
        $logData = [
            'event' => $event,
            'user_id' => $user->id,
            'phone' => $user->phone,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toDateTimeString(),
            'failed_attempts' => $user->failed_login_attempts,
            'is_locked' => $user->is_locked,
            'locked_until' => $user->locked_until?->toDateTimeString(),
        ];

        // Merge additional context
        $logData = array_merge($logData, $context);

        // Write to audit log
        logger()->channel('audit')->warning('Failed login attempt', $logData);
    }
}
