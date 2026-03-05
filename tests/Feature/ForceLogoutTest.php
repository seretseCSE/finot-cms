<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ForceLogoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test superadmin can force logout all users.
     */
    public function test_superadmin_can_force_logout_all_users(): void
    {
        $user = $this->createSuperadminUser();

        // Create some sessions for different users
        $this->createUserSessions(5);

        $response = $this->actingAs($user)
            ->post('/admin/emergency/force-logout-all');

        $response->assertSessionHas('success');

        // Verify sessions are cleared
        $this->assertEquals(0, DB::table('sessions')->count());
    }

    /**
     * Test admin cannot force logout all users.
     */
    public function test_admin_cannot_force_logout_all_users(): void
    {
        $user = $this->createAdminUser();

        $response = $this->actingAs($user)
            ->post('/admin/emergency/force-logout-all');

        $response->assertStatus(403);
    }

    /**
     * Test regular user cannot force logout all users.
     */
    public function test_regular_user_cannot_force_logout_all_users(): void
    {
        $user = $this->createUserWithRole('member');

        $response = $this->actingAs($user)
            ->post('/admin/emergency/force-logout-all');

        $response->assertStatus(403);
    }

    /**
     * Test force logout clears all sessions except current user.
     */
    public function test_force_logout_clears_all_sessions_except_current(): void
    {
        $superadmin = $this->createSuperadminUser();
        
        // Create sessions for other users
        $otherUsers = User::factory()->count(3)->create();
        
        foreach ($otherUsers as $otherUser) {
            UserSession::create([
                'user_id' => $otherUser->id,
                'device_name' => 'Test Device',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Test',
                'is_active' => true,
                'last_activity' => now(),
            ]);
        }

        // Create session for superadmin
        $this->actingAs($superadmin);
        
        // Force logout
        $response = $this->post('/admin/emergency/force-logout-all');

        $response->assertSessionHas('success');

        // Other user sessions should be cleared
        foreach ($otherUsers as $otherUser) {
            $this->assertEquals(0, UserSession::where('user_id', $otherUser->id)->where('is_active', true)->count());
        }
    }

    /**
     * Test force logout action is logged.
     */
    public function test_force_logout_action_is_logged(): void
    {
        $user = $this->createSuperadminUser();

        // Create some sessions
        $this->createUserSessions(3);

        $response = $this->actingAs($user)
            ->post('/admin/emergency/force-logout-all');

        // Verify activity log exists
        $this->assertDatabaseHas('activity_log', [
            'causer_type' => User::class,
            'causer_id' => $user->id,
        ]);
    }

    /**
     * Test emergency tools page is accessible only for superadmin.
     */
    public function test_emergency_tools_page_access(): void
    {
        // Test superadmin access
        $superadmin = $this->createSuperadminUser();
        $response = $this->actingAs($superadmin)->get('/admin/emergency-tools');
        $response->assertStatus(200);

        // Test admin denial
        $admin = $this->createAdminUser();
        $response = $this->actingAs($admin)->get('/admin/emergency-tools');
        $response->assertStatus(403);
    }

    /**
     * Test individual user logout by admin.
     */
    public function test_admin_can_logout_individual_user(): void
    {
        $admin = $this->createSuperadminUser();
        $user = User::factory()->create();

        // Create session for user
        UserSession::create([
            'user_id' => $user->id,
            'device_name' => 'Test Device',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
            'is_active' => true,
            'last_activity' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->post("/admin/users/{$user->id}/force-logout");

        $response->assertSessionHas('success');

        // User sessions should be cleared
        $this->assertEquals(0, UserSession::where('user_id', $user->id)->where('is_active', true)->count());
    }

    /**
     * Test role change forces logout.
     */
    public function test_role_change_forces_logout(): void
    {
        $user = $this->createAdminUser();

        // Create session
        UserSession::create([
            'user_id' => $user->id,
            'device_name' => 'Test Device',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
            'is_active' => true,
            'last_activity' => now(),
        ]);

        // Update user role
        $user->syncRoles('superadmin');

        // Should trigger logout of all sessions
        $this->assertEquals(0, UserSession::where('user_id', $user->id)->where('is_active', true)->count());
    }

    /**
     * Test force logout via EmergencyTools page.
     */
    public function test_force_logout_via_emergency_tools(): void
    {
        $user = $this->createSuperadminUser();

        // Create sessions
        $this->createUserSessions(3);

        // Access emergency tools and perform action
        $response = $this->actingAs($user)
            ->get('/admin/emergency-tools');

        $response->assertStatus(200);
        $response->assertSee('Force Logout');

        // Perform the force logout action
        $response = $this->actingAs($user)
            ->post('/admin/emergency/force-logout-all');

        $response->assertSessionHas('success');
    }

    /**
     * Test multiple concurrent sessions are all cleared.
     */
    public function test_multiple_concurrent_sessions_cleared(): void
    {
        $user = $this->createSuperadminUser();

        // Create multiple sessions for same user
        for ($i = 0; $i < 5; $i++) {
            UserSession::create([
                'user_id' => $user->id,
                'device_name' => "Device {$i}",
                'ip_address' => "127.0.0." . ($i + 1),
                'user_agent' => 'Test',
                'is_active' => true,
                'last_activity' => now(),
            ]);
        }

        // Force logout
        $response = $this->actingAs($user)
            ->post('/admin/emergency/force-logout-all');

        // Sessions should be cleared
        $this->assertEquals(0, UserSession::where('user_id', $user->id)->where('is_active', true)->count());
    }

    /**
     * Test session files are cleared on force logout.
     */
    public function test_session_files_are_cleared(): void
    {
        $user = $this->createSuperadminUser();

        // Create some sessions
        $this->createUserSessions(3);

        // Force logout
        $response = $this->actingAs($user)
            ->post('/admin/emergency/force-logout-all');

        $response->assertSessionHas('success');

        // Verify database sessions are cleared
        $this->assertEquals(0, DB::table('sessions')->count());
    }

    /**
     * Test non-admin user cannot access force logout endpoint.
     */
    public function test_non_admin_cannot_access_force_logout_endpoint(): void
    {
        $user = $this->createUserWithRole('member');

        $response = $this->actingAs($user)
            ->post('/admin/emergency/force-logout-all');

        $response->assertStatus(403);
    }

    /**
     * Helper: Create sessions for random users.
     */
    protected function createUserSessions(int $count): void
    {
        $users = User::factory()->count($count)->create();

        foreach ($users as $user) {
            UserSession::create([
                'user_id' => $user->id,
                'device_name' => 'Test Device',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Test',
                'is_active' => true,
                'last_activity' => now(),
            ]);
        }
    }
}
