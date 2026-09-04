<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProfileAndSessionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_can_access_edit_profile_page(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/admin/edit-profile');
        $this->assertNotEquals(404, $response->getStatusCode(), 'Route not found');
        $this->assertNotEquals(403, $response->getStatusCode(), 'Forbidden');
    }

    #[Test]
    public function user_can_update_language_preference(): void
    {
        $user = User::factory()->create(['language_preference' => 'en']);

        $user->update(['language_preference' => 'am']);

        $this->assertEquals('am', $user->fresh()->language_preference);
    }

    #[Test]
    public function manage_active_sessions_accessible_to_superadmin(): void
    {
        $user = $this->createSuperadminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/manage-active-sessions');
        $this->assertNotEquals(404, $response->getStatusCode(), 'Route not found');
        $this->assertNotEquals(403, $response->getStatusCode(), 'Forbidden');
    }

    #[Test]
    public function manage_active_sessions_is_forbidden_for_admin(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $this->get('/admin/manage-active-sessions')->assertForbidden();
    }

    #[Test]
    public function custom_options_management_accessible_to_superadmin(): void
    {
        $user = $this->createSuperadminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/manage-custom-options');
        $this->assertNotEquals(404, $response->getStatusCode(), 'Route not found');
        $this->assertNotEquals(403, $response->getStatusCode(), 'Forbidden');
    }

    #[Test]
    public function password_change_requires_current_password(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('CurrentPass123'),
        ]);

        $response = $this->actingAs($user)->post('/user/change-password', [
            'current_password' => 'WrongPass',
            'new_password' => 'NewPass123',
            'new_password_confirmation' => 'NewPass123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['current_password']);
    }

    #[Test]
    public function password_requirements_endpoint_returns_rules(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/user/password-requirements');
        $response->assertStatus(200);
    }

    #[Test]
    public function user_profile_update_page_exists(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/edit-profile');
        $this->assertNotEquals(404, $response->getStatusCode(), 'Route not found');
        $this->assertNotEquals(403, $response->getStatusCode(), 'Forbidden');
    }

    #[Test]
    public function inactive_user_is_marked_inactive(): void
    {
        $user = User::factory()->create([
            'phone' => '+251911000001',
            'password' => bcrypt('Password123'),
            'is_active' => false,
            'temp_password_changed' => true,
        ]);

        $this->assertFalse($user->isActive());
    }
}
