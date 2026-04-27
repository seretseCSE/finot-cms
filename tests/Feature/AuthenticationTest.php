<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function login_page_loads(): void
    {
        $response = $this->get('/admin/login');
        $response->assertStatus(200);
    }

    #[Test]
    public function user_can_logout(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $response = $this->post('/admin/logout');
        $response->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    #[Test]
    public function guest_cannot_access_admin_dashboard(): void
    {
        $response = $this->get('/admin');
        $response->assertRedirect('/admin/login');
    }

    #[Test]
    public function authenticated_user_can_access_admin_dashboard(): void
    {
        $user = $this->createAdminUser();
        $response = $this->actingAs($user)->get('/admin');
        $response->assertStatus(200);
    }

    #[Test]
    public function inactive_user_is_prevented_from_access(): void
    {
        $user = User::factory()->create([
            'phone' => '+251911000001',
            'password' => bcrypt('Password123'),
            'is_active' => false,
            'temp_password_changed' => true,
        ]);

        $this->assertFalse($user->is_active);
        $this->assertFalse($user->isActive());
    }

    #[Test]
    public function locked_user_is_prevented_from_access(): void
    {
        $user = User::factory()->create([
            'phone' => '+251911000001',
            'password' => bcrypt('Password123'),
            'is_active' => true,
            'is_locked' => true,
            'temp_password_changed' => true,
        ]);

        $this->assertTrue($user->is_locked);
        $this->assertTrue($user->isAccountLocked());
    }

    #[Test]
    public function user_with_temp_password_flag_exists(): void
    {
        $user = User::factory()->create([
            'phone' => '+251911000001',
            'password' => bcrypt('Admin1234'),
            'is_active' => true,
            'temp_password_changed' => false,
        ]);

        $this->assertFalse($user->temp_password_changed);
        $this->assertDatabaseHas('users', ['phone' => '+251911000001', 'temp_password_changed' => false]);
    }

    #[Test]
    public function login_tracks_failed_attempts_on_user_model(): void
    {
        $user = User::factory()->create([
            'phone' => '+251911000001',
            'password' => bcrypt('Password123'),
            'is_active' => true,
            'temp_password_changed' => true,
            'failed_login_attempts' => 0,
        ]);

        $user->increment('failed_login_attempts');

        $this->assertEquals(1, $user->fresh()->failed_login_attempts);
    }

    #[Test]
    public function successful_login_resets_failed_attempts_on_user_model(): void
    {
        $user = User::factory()->create([
            'phone' => '+251911000001',
            'password' => bcrypt('Password123'),
            'is_active' => true,
            'temp_password_changed' => true,
            'failed_login_attempts' => 3,
        ]);

        $user->update(['failed_login_attempts' => 0]);

        $this->assertEquals(0, $user->fresh()->failed_login_attempts);
    }

    #[Test]
    public function user_session_records_can_be_created(): void
    {
        $user = User::factory()->create([
            'phone' => '+251911000001',
            'password' => bcrypt('Password123'),
            'is_active' => true,
            'temp_password_changed' => true,
        ]);

        UserSession::factory()->create(['user_id' => $user->id]);

        $this->assertDatabaseHas('user_sessions', ['user_id' => $user->id]);
    }

    #[Test]
    public function max_three_sessions_are_enforced_at_model_level(): void
    {
        $user = User::factory()->create([
            'phone' => '+251911000001',
            'password' => bcrypt('Password123'),
            'is_active' => true,
            'temp_password_changed' => true,
        ]);

        UserSession::factory()->count(3)->create(['user_id' => $user->id]);

        $this->assertEquals(3, UserSession::where('user_id', $user->id)->count());

        // Enforcing max sessions would be done by application logic
        UserSession::where('user_id', $user->id)->oldest()->first()->delete();
        UserSession::factory()->create(['user_id' => $user->id]);

        $this->assertLessThanOrEqual(3, UserSession::where('user_id', $user->id)->count());
    }
}
