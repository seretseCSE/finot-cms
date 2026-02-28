<?php

namespace App\Models;

use App\Models\Traits\HasUserSessions;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, HasUserSessions;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'password_history',
        'is_active',
        'is_locked',
        'temp_password_changed',
        'failed_login_attempts',
        'locked_until',
        'lock_reason',
        'locked_by',
        'locked_at',
        'department_id',
        'language_preference',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var list<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'is_locked' => 'boolean',
        'temp_password_changed' => 'boolean',
        'password_history' => 'array',
        'language_preference' => 'string',
        'locked_until' => 'datetime',
        'locked_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_locked' => 'boolean',
            'temp_password_changed' => 'boolean',
            'password_history' => 'array',
            'language_preference' => 'string',
            'locked_until' => 'datetime',
        ];
    }

    /**
     * Get the display name attribute.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name;
    }

    /**
     * Get the Ethiopian join date attribute.
     */
    public function getEthiopianJoinDateAttribute(): string
    {
        return $this->created_at ? $this->created_at->format('M d, Y') : '';
    }

    /**
     * Check if the user is active.
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Get the department scope for query scoping.
     */
    public function getDepartmentScope(): ?int
    {
        return $this->department_id;
    }

    /**
     * Get the department that the user belongs to.
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the user who locked this account.
     */
    public function lockedBy()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    /**
     * Get the preferred locale.
     */
    public function getPreferredLocale(): string
    {
        return $this->language_preference ?? 'en';
    }

    /**
     * Add current password hash to history.
     */
    public function addToPasswordHistory(string $passwordHash, int $maxHistoryCount = 3): void
    {
        $history = $this->password_history ?? [];

        // Add current password to the beginning of history
        array_unshift($history, $passwordHash);

        // Keep only the specified number of most recent passwords
        $this->password_history = array_slice($history, 0, $maxHistoryCount);

        $this->save();
    }

    /**
     * Override the canAccessPanel method.
     */
    public function canAccessPanel(\Filament\Panel $panel): bool
    {
        return $this->isActive() && $this->temp_password_changed;
    }

    /**
     * Get the phone number for authentication.
     */
    public function username(): string
    {
        return $this->phone;
    }

    /**
     * Get the email for authentication fallback.
     */
    public function getEmailForPasswordReset(): string
    {
        return $this->email;
    }



    /**
     * Get the email field name for authentication compatibility.
     */
    public function getEmailName(): string
    {
        return 'phone';
    }

    /**
     * Find the user instance for the given phone number.
     *
     * @param  string  $phone
     * @return \App\Models\User|null
     */
    public function findForPhone(string $phone): ?User
    {
        return $this->where('phone', $phone)->first();
    }

    /**
     * Check if user account is locked (either manual or automatic)
     */
    public function isAccountLocked(): bool
    {
        return $this->is_locked || ($this->locked_until && $this->locked_until->isFuture());
    }

    /**
     * Check if user is manually locked (admin action)
     */
    public function isManuallyLocked(): bool
    {
        return $this->is_locked;
    }

    /**
     * Check if user is automatically locked (failed attempts)
     */
    public function isAutomaticallyLocked(): bool
    {
        return !$this->is_locked && $this->locked_until && $this->locked_until->isFuture();
    }

    /**
     * Get lock status message
     */
    public function getLockStatusMessage(): string
    {
        if ($this->isManuallyLocked()) {
            return 'Account disabled. Please contact administrator.';
        }

        if ($this->isAutomaticallyLocked()) {
            return $this->getLockoutMessage();
        }

        return '';
    }

    /**
     * Manually lock user account
     */
    public function manuallyLock(string $reason = null, int $adminId = null): void
    {
        $this->update(['is_locked' => true]);

        // Log manual lock action
        $this->logFailedLogin('account_manually_locked', [
            'reason' => $reason ?? 'Manual lock by administrator',
            'admin_id' => $adminId,
            'lock_type' => 'manual',
        ]);
    }

    /**
     * Manually unlock user account
     */
    public function manuallyUnlock(string $reason = null, int $adminId = null): void
    {
        $this->update([
            'is_locked' => false,
            'locked_until' => null, // Clear automatic lockout as well
            'failed_login_attempts' => 0, // Reset failed attempts
        ]);

        // Log manual unlock action
        $this->logFailedLogin('account_manually_unlocked', [
            'reason' => $reason ?? 'Manual unlock by administrator',
            'admin_id' => $adminId,
            'lock_type' => 'manual',
        ]);
    }

    /**
     * Check if user needs to change temporary password.
     */
    public function needsPasswordChange(): bool
    {
        return !$this->temp_password_changed;
    }

    /**
     * Reset failed login attempts.
     */
    public function resetFailedAttempts(): void
    {
        $this->update([
            'failed_login_attempts' => 0,
            'is_locked' => false,
            'locked_until' => null,
        ]);
    }

    /**
     * Increment failed login attempts and lock if necessary.
     */
    public function incrementFailedAttempts(): void
    {
        $this->increment('failed_login_attempts');

        // Progressive locking: 1 minute for first group, 5 minutes for subsequent groups
        $failedAttempts = $this->failed_login_attempts;

        if ($failedAttempts >= 5) {
            $lockDuration = ($failedAttempts === 5) ? 1 : 5; // 1 minute for first group, 5 for subsequent
            $this->update([
                'is_locked' => true,
                'locked_until' => now()->addMinutes($lockDuration),
            ]);

            // Log the lockout event
            $this->logFailedLogin('account_locked', [
                'failed_attempts' => $failedAttempts,
                'lock_duration_minutes' => $lockDuration,
                'locked_until' => $this->locked_until->toDateTimeString(),
            ]);
        }
    }

    /**
     * Add current password to history and update password
     */
    public function updatePassword(string $newPassword, int $maxHistoryCount = 3): void
    {
        $currentPasswordHash = $this->password;

        // Get current password history
        $history = $this->password_history ?? [];

        // Add current password to the beginning of history
        array_unshift($history, $currentPasswordHash);

        // Keep only the last N passwords
        $history = array_slice($history, 0, $maxHistoryCount);

        // Update password and history
        $this->update([
            'password' => $newPassword,
            'password_history' => $history,
            'temp_password_changed' => true,
        ]);
    }

    /**
     * Check if password has been used before
     */
    public function isPasswordInHistory(string $password, int $maxHistoryCount = 3): bool
    {
        $history = $this->password_history ?? [];

        if (empty($history)) {
            return false;
        }

        // Check against last N passwords
        $recentHistory = array_slice($history, 0, $maxHistoryCount);

        foreach ($recentHistory as $oldPasswordHash) {
            if (Hash::check($password, $oldPasswordHash)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get password history as array
     */
    public function getPasswordHistory(int $maxCount = 3): array
    {
        $history = $this->password_history ?? [];
        return array_slice($history, 0, $maxCount);
    }

    /**
     * Log failed login attempt to audit log
     */
    public function logFailedLogin(string $event, array $context = []): void
    {
        $logData = [
            'event' => $event,
            'user_id' => $this->id,
            'phone' => $this->phone,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toDateTimeString(),
            'failed_attempts' => $this->failed_login_attempts,
            'is_locked' => $this->is_locked,
            'locked_until' => $this->locked_until?->toDateTimeString(),
        ];

        // Merge additional context
        $logData = array_merge($logData, $context);

        // Write to audit log
        logger()->channel('audit')->warning('Failed login attempt', $logData);
    }

    /**
     * Get remaining lockout time in minutes
     */
    public function getRemainingLockoutMinutes(): int
    {
        if (!$this->is_locked || !$this->locked_until) {
            return 0;
        }

        if ($this->locked_until->isPast()) {
            return 0;
        }

        return $this->locked_until->diffInMinutes(now());
    }

    /**
     * Get formatted lockout message
     */
    public function getLockoutMessage(): string
    {
        $remainingMinutes = $this->getRemainingLockoutMinutes();

        if ($remainingMinutes <= 0) {
            return 'Account is locked. Please try again later.';
        }

        if ($remainingMinutes === 1) {
            return 'Account is locked. Please try again in 1 minute.';
        }

        return "Account is locked. Please try again in {$remainingMinutes} minutes.";
    }

    /**
     * Lock the user account.
     */
    public function lockAccount(string $reason, string $duration, int $lockedBy = null): bool
    {
        $lockedUntil = match($duration) {
            '1h' => now()->addHour(),
            '24h' => now()->addDay(),
            '7d' => now()->addWeek(),
            'permanent' => null,
            default => now()->addHour(),
        };

        $this->update([
            'is_locked' => true,
            'locked_until' => $lockedUntil,
            'lock_reason' => $reason,
            'locked_by' => $lockedBy,
            'locked_at' => now(),
        ]);

        // Log the lock action
        activity()
            ->causedBy($lockedBy ? User::find($lockedBy) : null)
            ->performedOn($this)
            ->withProperties([
                'reason' => $reason,
                'duration' => $duration,
                'locked_until' => $lockedUntil,
            ])
            ->log('account_locked');

        return true;
    }

    /**
     * Unlock the user account.
     */
    public function unlockAccount(int $unlockedBy = null): bool
    {
        $this->update([
            'is_locked' => false,
            'locked_until' => null,
            'lock_reason' => null,
            'locked_by' => null,
            'locked_at' => null,
        ]);

        // Log the unlock action
        activity()
            ->causedBy($unlockedBy ? User::find($unlockedBy) : null)
            ->performedOn($this)
            ->withProperties([
                'unlocked_at' => now(),
            ])
            ->log('account_unlocked');

        return true;
    }

    /**
     * Check if the account is currently locked.
     */
    public function isCurrentlyLocked(): bool
    {
        if (!$this->is_locked) {
            return false;
        }

        if ($this->locked_until && $this->locked_until->isPast()) {
            // Auto-unlock if lock time has passed
            $this->unlockAccount();
            return false;
        }

        return true;
    }

    /**
     * Get lock status badge data.
     */
    public function getLockStatusBadge(): array
    {
        if (!$this->isCurrentlyLocked()) {
            return [
                'status' => 'Active',
                'color' => 'success',
                'icon' => 'heroicon-o-check-circle',
            ];
        }

        if ($this->locked_until === null) {
            return [
                'status' => 'Permanently Locked',
                'color' => 'danger',
                'icon' => 'heroicon-o-lock-closed',
            ];
        }

        return [
            'status' => 'Locked',
            'color' => 'danger',
            'icon' => 'heroicon-o-lock-closed',
            'until' => $this->locked_until->format('M j, Y H:i'),
        ];
    }
}
