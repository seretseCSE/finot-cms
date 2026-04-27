<?php

namespace Tests\Unit\Services\Auth;

use App\Models\User;
use App\Services\Auth\AccountLockoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountLockoutServiceTest extends TestCase
{
    use RefreshDatabase;

    private AccountLockoutService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AccountLockoutService();
    }

    #[Test]
    public function exactly_five_failed_attempts_triggers_lockout(): void
    {
        config(['finot.failed_login_lockout_threshold' => 5]);

        $user = User::factory()->create([
            'failed_login_attempts' => 4,
            'is_locked' => false,
            'locked_until' => null,
        ]);

        $this->service->incrementFailedAttempts($user);

        $user->refresh();

        $this->assertTrue($user->is_locked);
        $this->assertNotNull($user->locked_until);
        $this->assertEquals(5, $user->failed_login_attempts);
    }

    #[Test]
    public function four_failed_attempts_does_not_trigger_lockout(): void
    {
        config(['finot.failed_login_lockout_threshold' => 5]);

        $user = User::factory()->create([
            'failed_login_attempts' => 3,
            'is_locked' => false,
            'locked_until' => null,
        ]);

        $this->service->incrementFailedAttempts($user);

        $user->refresh();

        $this->assertFalse($user->is_locked);
        $this->assertNull($user->locked_until);
        $this->assertEquals(4, $user->failed_login_attempts);
    }

    #[Test]
    public function progressive_lockout_duration_first_group_is_one_minute(): void
    {
        config(['finot.failed_login_lockout_threshold' => 5]);

        $user = User::factory()->create([
            'failed_login_attempts' => 4,
            'is_locked' => false,
        ]);

        $this->service->incrementFailedAttempts($user);
        $user->refresh();

        $expectedUntil = now()->addMinute();
        $this->assertEqualsWithDelta(
            $expectedUntil->timestamp,
            $user->locked_until->timestamp,
            5,
            'First lockout should be 1 minute'
        );
    }

    #[Test]
    public function progressive_lockout_duration_subsequent_group_is_five_minutes(): void
    {
        config(['finot.failed_login_lockout_threshold' => 5]);

        $user = User::factory()->create([
            'failed_login_attempts' => 5,
            'is_locked' => true,
            'locked_until' => now()->subMinute(),
        ]);

        $this->service->incrementFailedAttempts($user);
        $user->refresh();

        $expectedUntil = now()->addMinutes(5);
        $this->assertEqualsWithDelta(
            $expectedUntil->timestamp,
            $user->locked_until->timestamp,
            5,
            'Subsequent lockout should be 5 minutes'
        );
    }

    #[Test]
    public function is_currently_locked_returns_false_when_not_locked(): void
    {
        $user = User::factory()->create([
            'is_locked' => false,
            'locked_until' => null,
        ]);

        $this->assertFalse($this->service->isCurrentlyLocked($user));
    }

    #[Test]
    public function is_currently_locked_returns_true_when_locked_and_time_not_expired(): void
    {
        $user = User::factory()->create([
            'is_locked' => true,
            'locked_until' => now()->addMinutes(5),
        ]);

        $this->assertTrue($this->service->isCurrentlyLocked($user));
    }

    #[Test]
    public function automatic_unlock_happens_when_lock_time_expires(): void
    {
        $user = User::factory()->create([
            'is_locked' => true,
            'locked_until' => now()->subMinutes(1),
            'failed_login_attempts' => 5,
        ]);

        $result = $this->service->isCurrentlyLocked($user);

        $this->assertFalse($result);

        $user->refresh();
        $this->assertFalse($user->is_locked);
        $this->assertNull($user->locked_until);
    }

    #[Test]
    public function reset_failed_attempts_clears_lock_and_attempts(): void
    {
        $user = User::factory()->create([
            'failed_login_attempts' => 10,
            'is_locked' => true,
            'locked_until' => now()->addMinutes(5),
        ]);

        $this->service->resetFailedAttempts($user);
        $user->refresh();

        $this->assertEquals(0, $user->failed_login_attempts);
        $this->assertFalse($user->is_locked);
        $this->assertNull($user->locked_until);
    }

    #[Test]
    public function get_remaining_lockout_minutes_returns_zero_when_not_locked(): void
    {
        $user = User::factory()->create([
            'is_locked' => false,
            'locked_until' => null,
        ]);

        $this->assertEquals(0, $this->service->getRemainingLockoutMinutes($user));
    }

    #[Test]
    public function get_remaining_lockout_minutes_returns_positive_when_locked(): void
    {
        $user = User::factory()->create([
            'is_locked' => true,
            'locked_until' => now()->addMinutes(5),
        ]);

        $remaining = $this->service->getRemainingLockoutMinutes($user);

        $this->assertGreaterThan(0, $remaining);
        $this->assertLessThanOrEqual(5, $remaining);
    }

    #[Test]
    public function lock_account_with_permanent_duration_sets_null_locked_until(): void
    {
        $user = User::factory()->create([
            'is_locked' => false,
            'locked_until' => null,
        ]);

        $this->service->lockAccount($user, 'Banned', 'permanent', 1);
        $user->refresh();

        $this->assertTrue($user->is_locked);
        $this->assertNull($user->locked_until);
        $this->assertEquals('Banned', $user->lock_reason);
        $this->assertEquals(1, $user->locked_by);
    }

    #[Test]
    public function unlock_account_clears_all_lock_fields(): void
    {
        $user = User::factory()->create([
            'is_locked' => true,
            'locked_until' => now()->addMinutes(10),
            'lock_reason' => 'Test lock',
            'locked_by' => 1,
            'locked_at' => now(),
        ]);

        $this->service->unlockAccount($user, 2);
        $user->refresh();

        $this->assertFalse($user->is_locked);
        $this->assertNull($user->locked_until);
        $this->assertNull($user->lock_reason);
        $this->assertNull($user->locked_by);
        $this->assertNull($user->locked_at);
    }

    #[Test]
    public function manually_lock_sets_is_locked_without_duration(): void
    {
        $user = User::factory()->create([
            'is_locked' => false,
        ]);

        $this->service->manuallyLock($user, 'Admin action', 1);
        $user->refresh();

        $this->assertTrue($user->is_locked);
    }

    #[Test]
    public function manually_unlock_clears_lock_and_resets_attempts(): void
    {
        $user = User::factory()->create([
            'is_locked' => true,
            'locked_until' => now()->addMinutes(5),
            'failed_login_attempts' => 3,
        ]);

        $this->service->manuallyUnlock($user, 'Admin action', 1);
        $user->refresh();

        $this->assertFalse($user->is_locked);
        $this->assertEquals(0, $user->failed_login_attempts);
    }
}
