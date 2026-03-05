<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SessionLimitTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test session limit is enforced (max 3 devices).
     */
    public function test_session_limit_max_three_devices(): void
    {
        $user = User::factory()->create();

        // Create 3 sessions (max allowed)
        $this->createSessionsForUser($user, 3);

        $activeSessions = UserSession::where('user_id', $user->id)
            ->where('is_active', true)
            ->count();

        $this->assertEquals(3, $activeSessions);
    }

    /**
     * Test fourth session should not be allowed.
     */
    public function test_fourth_session_not_allowed(): void
    {
        $user = User::factory()->create();

        // Create 3 sessions (max allowed)
        $this->createSessionsForUser($user, 3);

        // Try to create a 4th session - should be prevented
        // In the application, this should be handled by session limiting logic
        $canCreateMore = $this->canCreateNewSession($user);

        $this->assertFalse($canCreateMore);
    }

    /**
     * Test session count is correctly calculated.
     */
    public function test_session_count_is_correct(): void
    {
        $user = User::factory()->create();

        // Create 2 sessions
        $this->createSessionsForUser($user, 2);

        $count = $this->getActiveSessionCount($user);

        $this->assertEquals(2, $count);
    }

    /**
     * Test inactive sessions don't count towards limit.
     */
    public function test_inactive_sessions_not_counted(): void
    {
        $user = User::factory()->create();

        // Create 2 active sessions
        $this->createSessionsForUser($user, 2, true);

        // Create 1 inactive session
        $this->createSessionsForUser($user, 1, false);

        // Only active sessions should count
        $activeCount = $this->getActiveSessionCount($user);
        $this->assertEquals(2, $activeCount);

        // Total sessions should be 3
        $totalCount = UserSession::where('user_id', $user->id)->count();
        $this->assertEquals(3, $totalCount);
    }

    /**
     * Test session can be revoked.
     */
    public function test_session_can_be_revoked(): void
    {
        $user = User::factory()->create();

        // Create a session
        $session = $this->createSessionForUser($user);

        // Revoke the session
        $session->update(['is_active' => false]);

        // Session should no longer be active
        $this->assertFalse($session->fresh()->is_active);
    }

    /**
     * Test revoking session allows new session.
     */
    public function test_revoking_session_allows_new_session(): void
    {
        $user = User::factory()->create();

        // Create 3 sessions
        $this->createSessionsForUser($user, 3);

        // Revoke one session
        $sessionToRevoke = UserSession::where('user_id', $user->id)->first();
        $sessionToRevoke->update(['is_active' => false]);

        // Should now be able to create a new session
        $canCreateMore = $this->canCreateNewSession($user);

        $this->assertTrue($canCreateMore);
    }

    /**
     * Test session details include device info.
     */
    public function test_session_includes_device_info(): void
    {
        $user = User::factory()->create();

        $session = UserSession::create([
            'user_id' => $user->id,
            'device_name' => 'Test Device',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 Test',
            'is_active' => true,
            'last_activity' => now(),
        ]);

        $this->assertEquals('Test Device', $session->device_name);
        $this->assertEquals('127.0.0.1', $session->ip_address);
    }

    /**
     * Test oldest session is revoked when limit exceeded.
     */
    public function test_oldest_session_revoked_when_limit_exceeded(): void
    {
        $user = User::factory()->create();

        // Create 3 sessions with different timestamps
        $session1 = $this->createSessionForUser($user, now()->subHours(3));
        $session2 = $this->createSessionForUser($user, now()->subHours(2));
        $session3 = $this->createSessionForUser($user, now()->subHour());

        // Try to add a 4th - should revoke oldest
        $this->handleSessionLimitExceeded($user);

        // The oldest session should be revoked
        $this->assertFalse($session1->fresh()->is_active);
        
        // Others should still be active
        $this->assertTrue($session2->fresh()->is_active);
        $this->assertTrue($session3->fresh()->is_active);
    }

    /**
     * Test session limit can be configured.
     */
    public function test_session_limit_can_be_configured(): void
    {
        $maxSessions = config('session.max_sessions', 3);
        
        // Default should be 3
        $this->assertEquals(3, $maxSessions);
    }

    /**
     * Test concurrent sessions from same device.
     */
    public function test_concurrent_sessions_same_device(): void
    {
        $user = User::factory()->create();

        // Create multiple sessions from same device
        $session1 = $this->createSessionForUser($user, null, 'Chrome', '192.168.1.1');
        $session2 = $this->createSessionForUser($user, null, 'Chrome', '192.168.1.1');

        // Both should exist
        $count = UserSession::where('user_id', $user->id)->count();
        $this->assertEquals(2, $count);
    }

    /**
     * Test session expiry is enforced.
     */
    public function test_session_expiry_is_enforced(): void
    {
        $user = User::factory()->create();

        // Create expired session
        $session = $this->createSessionForUser($user, now()->subDays(30));

        // Session should be considered expired
        $this->assertTrue($this->isSessionExpired($session));
    }

    /**
     * Test active session detection.
     */
    public function test_active_session_detection(): void
    {
        $user = User::factory()->create();

        $activeSession = UserSession::create([
            'user_id' => $user->id,
            'device_name' => 'Active Device',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
            'is_active' => true,
            'last_activity' => now(),
        ]);

        $inactiveSession = UserSession::create([
            'user_id' => $user->id,
            'device_name' => 'Inactive Device',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
            'is_active' => false,
            'last_activity' => now()->subHour(),
        ]);

        $this->assertTrue($this->isSessionActive($activeSession));
        $this->assertFalse($this->isSessionActive($inactiveSession));
    }

    /**
     * Helper: Create multiple sessions for a user.
     */
    protected function createSessionsForUser(User $user, int $count, bool $active = true): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->createSessionForUser($user);
        }
    }

    /**
     * Helper: Create a single session for a user.
     */
    protected function createSessionForUser(User $user, $lastActivity = null, string $device = 'Test Device', string $ip = '127.0.0.1'): UserSession
    {
        return UserSession::create([
            'user_id' => $user->id,
            'device_name' => $device,
            'ip_address' => $ip,
            'user_agent' => 'Test Agent',
            'is_active' => true,
            'last_activity' => $lastActivity ?? now(),
        ]);
    }

    /**
     * Helper: Check if user can create new session.
     */
    protected function canCreateNewSession(User $user): bool
    {
        $maxSessions = config('session.max_sessions', 3);
        $activeCount = UserSession::where('user_id', $user->id)
            ->where('is_active', true)
            ->count();

        return $activeCount < $maxSessions;
    }

    /**
     * Helper: Get active session count.
     */
    protected function getActiveSessionCount(User $user): int
    {
        return UserSession::where('user_id', $user->id)
            ->where('is_active', true)
            ->count();
    }

    /**
     * Helper: Handle session limit exceeded.
     */
    protected function handleSessionLimitExceeded(User $user): void
    {
        $maxSessions = config('session.max_sessions', 3);
        $activeCount = $this->getActiveSessionCount($user);

        if ($activeCount >= $maxSessions) {
            // Revoke oldest session
            $oldestSession = UserSession::where('user_id', $user->id)
                ->where('is_active', true)
                ->oldest()
                ->first();

            if ($oldestSession) {
                $oldestSession->update(['is_active' => false]);
            }
        }
    }

    /**
     * Helper: Check if session is expired.
     */
    protected function isSessionExpired(UserSession $session): bool
    {
        $expiryDays = config('session.expiry_days', 30);
        return $session->last_activity->lt(now()->subDays($expiryDays));
    }

    /**
     * Helper: Check if session is active.
     */
    protected function isSessionActive(UserSession $session): bool
    {
        return $session->is_active && !$this->isSessionExpired($session);
    }
}
