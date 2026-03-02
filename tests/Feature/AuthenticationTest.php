<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test login page is accessible.
     */
    public function test_login_page_is_accessible(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    /**
     * Test user can login with valid credentials.
     */
    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'phone' => '+251911234567',
            'password' => bcrypt('password'),
            'is_active' => true,
            'temp_password_changed' => true,
        ]);

        $response = $this->post('/login', [
            'phone' => '+251911234567',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
    }

    /**
     * Test user cannot login with invalid credentials.
     */
    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $user = User::factory()->create([
            'phone' => '+251911234567',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'phone' => '+251911234567',
            'password' => 'wrongpassword',
        ]);

        $response->assertSessionHasErrors('phone');
        $this->assertGuest();
    }

    /**
     * Test user cannot login with inactive account.
     */
    public function test_user_cannot_login_with_inactive_account(): void
    {
        $user = User::factory()->inactive()->create([
            'phone' => '+251911234567',
            'password' => bcrypt('password'),
        ]);

        $response = $this->post('/login', [
            'phone' => '+251911234567',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('phone');
        $this->assertGuest();
    }

    /**
     * Test user cannot login with locked account.
     */
    public function test_user_cannot_login_with_locked_account(): void
    {
        $user = User::factory()->locked()->create([
            'phone' => '+251911234567',
            'password' => bcrypt('password'),
        ]);

        $response = $this->post('/login', [
            'phone' => '+251911234567',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('phone');
        $this->assertGuest();
    }

    /**
     * Test user cannot login without changing temporary password.
     */
    public function test_user_cannot_login_without_changing_temp_password(): void
    {
        $user = User::factory()->needsPasswordChange()->create([
            'phone' => '+251911234567',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'phone' => '+251911234567',
            'password' => 'password',
        ]);

        // Should redirect to password change page
        $response->assertRedirect('/change-initial-password');
        $this->assertGuest();
    }

    /**
     * Test user is redirected to login when accessing protected route.
     */
    public function test_user_is_redirected_to_login_when_accessing_protected_route(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect('/login');
    }

    /**
     * Test user can logout.
     */
    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    /**
     * Test login validation for phone number.
     */
    public function test_login_validates_phone_number(): void
    {
        $response = $this->post('/login', [
            'phone' => '',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('phone');
    }

    /**
     * Test login validation for password.
     */
    public function test_login_validates_password(): void
    {
        $response = $this->post('/login', [
            'phone' => '+251911234567',
            'password' => '',
        ]);

        $response->assertSessionHasErrors('password');
    }

    /**
     * Test remember me functionality.
     */
    public function test_remember_me_functionality(): void
    {
        $user = User::factory()->create([
            'phone' => '+251911234567',
            'password' => bcrypt('password'),
            'is_active' => true,
            'temp_password_changed' => true,
        ]);

        $response = $this->post('/login', [
            'phone' => '+251911234567',
            'password' => 'password',
            'remember' => true,
        ]);

        $this->assertAuthenticatedAs($user);
    }

    /**
     * Test failed login attempts are tracked.
     */
    public function test_failed_login_attempts_are_tracked(): void
    {
        $user = User::factory()->create([
            'phone' => '+251911234567',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        // Attempt to login with wrong password
        $this->post('/login', [
            'phone' => '+251911234567',
            'password' => 'wrongpassword',
        ]);

        $user->refresh();

        $this->assertEquals(1, $user->failed_login_attempts);
    }

    /**
     * Test account is locked after multiple failed attempts.
     */
    public function test_account_is_locked_after_multiple_failed_attempts(): void
    {
        $user = User::factory()->create([
            'phone' => '+251911234567',
            'password' => bcrypt('password'),
            'is_active' => true,
            'failed_login_attempts' => 4,
        ]);

        // Attempt to login with wrong password (5th attempt)
        $this->post('/login', [
            'phone' => '+251911234567',
            'password' => 'wrongpassword',
        ]);

        $user->refresh();

        // Account should be locked
        $this->assertTrue($user->isAccountLocked());
    }

    /**
     * Test unauthenticated user cannot access dashboard.
     */
    public function test_unauthenticated_user_cannot_access_dashboard(): void
    {
        $response = $this->get('/admin/dashboard');

        $response->assertRedirect('/login');
    }

    /**
     * Test authenticated user can access dashboard.
     */
    public function test_authenticated_user_can_access_dashboard(): void
    {
        $user = User::factory()->admin()->create();

        $response = $this->actingAs($user)->get('/admin/dashboard');

        $response->assertStatus(200);
    }

    /**
     * Test inactive user is redirected after login.
     */
    public function test_inactive_user_receives_appropriate_message(): void
    {
        $user = User::factory()->inactive()->create([
            'phone' => '+251911234567',
            'password' => bcrypt('password'),
        ]);

        $response = $this->post('/login', [
            'phone' => '+251911234567',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('phone');
    }

    /**
     * Test locked user receives appropriate message.
     */
    public function test_locked_user_receives_appropriate_message(): void
    {
        $user = User::factory()->locked()->create([
            'phone' => '+251911234567',
            'password' => bcrypt('password'),
        ]);

        $response = $this->post('/login', [
            'phone' => '+251911234567',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('phone');
    }
}
