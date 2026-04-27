<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function superadmin_can_access_users_page(): void
    {
        $user = $this->createSuperadminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/users');
        $response->assertStatus(200);
    }

    #[Test]
    public function superadmin_can_access_users_create_page(): void
    {
        $user = $this->createSuperadminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/users/create');
        $response->assertStatus(200);
    }

    #[Test]
    public function superadmin_can_lock_user_account(): void
    {
        $target = User::factory()->create(['is_locked' => false]);

        $target->update(['is_locked' => true]);

        $this->assertTrue($target->fresh()->is_locked);
    }

    #[Test]
    public function superadmin_can_unlock_user_account(): void
    {
        $target = User::factory()->create(['is_locked' => true]);

        $target->update(['is_locked' => false]);

        $this->assertFalse($target->fresh()->is_locked);
    }

    #[Test]
    public function admin_can_access_users_page(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/users');
        $response->assertStatus(200);
    }

    #[Test]
    public function admin_can_access_users_edit_page(): void
    {
        $admin = $this->createAdminUser();
        $target = User::factory()->create();
        $this->actingAs($admin);

        $response = $this->get("/admin/users/{$target->id}/edit");
        $response->assertStatus(200);
    }

    #[Test]
    public function user_cannot_edit_own_account_via_resource(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $response = $this->get("/admin/users/{$user->id}/edit");
        $response->assertStatus(403);
    }

    #[Test]
    public function user_list_shows_lock_and_active_status(): void
    {
        $admin = $this->createSuperadminUser();
        User::factory()->create(['is_locked' => true, 'is_active' => false]);
        $this->actingAs($admin);

        $response = $this->get('/admin/users');
        $response->assertStatus(200);
    }

    #[Test]
    public function force_logout_clears_user_sessions(): void
    {
        $admin = $this->createSuperadminUser();
        $target = User::factory()->create();
        $this->actingAs($admin);

        $response = $this->post("/admin/users/{$target->id}/force-logout");
        $this->assertDatabaseMissing('user_sessions', ['user_id' => $target->id]);
    }

    #[Test]
    public function user_sessions_resource_accessible(): void
    {
        $user = $this->createSuperadminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/user-sessions');
        $response->assertStatus(200);
    }
}
