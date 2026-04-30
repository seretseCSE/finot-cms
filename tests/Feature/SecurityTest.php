<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function audit_logs_resource_accessible_to_superadmin(): void
    {
        $user = $this->createSuperadminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/audit-logs');
        $response->assertStatus(200);
    }

    #[Test]
    public function audit_logs_resource_accessible_to_admin(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/audit-logs');
        $response->assertStatus(200);
    }

    #[Test]
    public function non_admin_cannot_access_audit_logs(): void
    {
        $user = $this->createStaffUser();
        $this->actingAs($user);

        $response = $this->get('/admin/audit-logs');
        $response->assertStatus(403);
    }

    #[Test]
    public function export_audit_logs_page_accessible_to_superadmin(): void
    {
        $user = $this->createSuperadminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/export-audit-logs');
        $response->assertStatus(200);
    }

    #[Test]
    public function failed_logins_are_tracked_on_user_model(): void
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
    public function account_locks_after_five_failed_attempts(): void
    {
        $user = User::factory()->create([
            'phone' => '+251911000001',
            'password' => bcrypt('Password123'),
            'is_active' => true,
            'temp_password_changed' => true,
            'failed_login_attempts' => 4,
        ]);

        $user->increment('failed_login_attempts');
        $user->update(['locked_until' => now()->addHours(1)]);

        $this->assertNotNull($user->fresh()->locked_until);
    }

    #[Test]
    public function error_log_viewer_accessible_to_superadmin(): void
    {
        $user = $this->createSuperadminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/error-log-viewer');
        $response->assertStatus(200);
    }

    #[Test]
    public function system_health_monitoring_accessible_to_superadmin(): void
    {
        $user = $this->createSuperadminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/system-health-monitoring');
        $response->assertStatus(200);
    }

    #[Test]
    public function password_change_endpoint_exists(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('OldPass123'),
        ]);

        $response = $this->actingAs($user)->post('/user/change-password', [
            'current_password' => 'OldPass123',
            'new_password' => 'NewPass123',
            'new_password_confirmation' => 'NewPass123',
        ]);

        $response->assertStatus(302);
    }

    #[Test]
    public function error_logs_resource_accessible(): void
    {
        $user = $this->createSuperadminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/error-logs');
        $response->assertStatus(200);
    }
}
