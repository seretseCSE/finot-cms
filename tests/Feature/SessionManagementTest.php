<?php

namespace Tests\Feature;

use App\Http\Middleware\TrackUserSessions;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SessionManagementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_can_access_with_less_than_three_active_sessions(): void
    {
        $user = User::factory()->create([
            'phone' => '+251911000001',
            'password' => bcrypt('Password123'),
            'is_active' => true,
        ]);

        // Seed 2 active sessions
        UserSession::factory()->count(2)->create([
            'user_id' => $user->id,
            'last_activity' => now(),
        ]);

        $this->assertFalse($user->fresh()->hasMaxSessions());
    }

    #[Test]
    public function user_blocked_when_max_three_sessions_reached(): void
    {
        $user = User::factory()->create([
            'phone' => '+251911000002',
            'password' => bcrypt('Password123'),
            'is_active' => true,
        ]);

        // Seed 3 active sessions
        UserSession::factory()->count(3)->create([
            'user_id' => $user->id,
            'last_activity' => now(),
        ]);

        $this->assertTrue($user->fresh()->hasMaxSessions());
    }

    #[Test]
    public function user_can_access_after_sessions_expire(): void
    {
        $user = User::factory()->create([
            'phone' => '+251911000003',
            'password' => bcrypt('Password123'),
            'is_active' => true,
        ]);

        // Seed 3 expired sessions (older than 30 minutes)
        UserSession::factory()->count(3)->create([
            'user_id' => $user->id,
            'last_activity' => now()->subMinutes(31),
        ]);

        $this->assertFalse($user->fresh()->hasMaxSessions());
    }

    #[Test]
    public function new_session_recorded_by_middleware(): void
    {
        $user = User::factory()->create([
            'phone' => '+251911000004',
            'password' => bcrypt('Password123'),
            'is_active' => true,
        ]);

        $this->actingAs($user);
        $this->assertEquals(0, $user->activeSessions()->count());

        // Simulate a request through the middleware
        $request = Request::create('/admin');
        $request->setUserResolver(fn () => $user);
        $middleware = new TrackUserSessions();
        $middleware->handle($request, fn () => response('OK'));

        $this->assertEquals(1, $user->fresh()->activeSessions()->count());
    }

    #[Test]
    public function middleware_blocks_login_when_max_sessions_reached(): void
    {
        $user = User::factory()->create([
            'phone' => '+251911000005',
            'password' => bcrypt('Password123'),
            'is_active' => true,
        ]);

        // Seed 3 active sessions
        UserSession::factory()->count(3)->create([
            'user_id' => $user->id,
            'last_activity' => now(),
        ]);

        $this->actingAs($user);

        $request = Request::create('/admin');
        $request->setLaravelSession($this->app->make('session')->driver());
        $request->setUserResolver(fn () => $user);

        $middleware = new TrackUserSessions();
        $response = $middleware->handle($request, fn () => response('OK'));

        // Middleware should return a redirect response with error
        $this->assertEquals(302, $response->getStatusCode());
    }

    #[Test]
    public function session_updated_on_subsequent_requests(): void
    {
        $user = User::factory()->create([
            'phone' => '+251911000006',
            'password' => bcrypt('Password123'),
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $oldActivity = now()->subMinutes(5);
        $session = UserSession::factory()->create([
            'user_id' => $user->id,
            'session_token' => session()->getId(),
            'last_activity' => $oldActivity,
        ]);

        // Wait a small amount to ensure time difference
        sleep(1);

        $request = Request::create('/admin');
        $request->setUserResolver(fn () => $user);
        $middleware = new TrackUserSessions();
        $middleware->handle($request, fn () => response('OK'));

        $session->refresh();
        $this->assertGreaterThan($oldActivity, $session->last_activity);
    }
}
