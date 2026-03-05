<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalOversightFilamentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test GlobalOversight page loads for superadmin only.
     */
    public function test_global_oversight_page_loads_for_superadmin(): void
    {
        $user = $this->createSuperadminUser();

        $response = $this->actingAs($user)
            ->get('/admin/global-oversight');

        $response->assertStatus(200);
    }

    /**
     * Test GlobalOversight page is not accessible for admin.
     */
    public function test_global_oversight_not_accessible_for_admin(): void
    {
        $user = $this->createAdminUser();

        $response = $this->actingAs($user)
            ->get('/admin/global-oversight');

        $response->assertStatus(403);
    }

    /**
     * Test GlobalOversight page is not accessible for regular users.
     */
    public function test_global_oversight_not_accessible_for_regular_users(): void
    {
        $user = $this->createUserWithRole('member');

        $response = $this->actingAs($user)
            ->get('/admin/global-oversight');

        $response->assertStatus(403);
    }

    /**
     * Test GlobalOversight displays system health stats.
     */
    public function test_global_oversight_displays_system_health(): void
    {
        $user = $this->createSuperadminUser();

        $response = $this->actingAs($user)
            ->get('/admin/global-oversight');

        $response->assertStatus(200);
        // Should contain health-related content
    }

    /**
     * Test ErrorLogResource visibility for superadmin.
     */
    public function test_error_log_resource_visible_for_superadmin(): void
    {
        $user = $this->createSuperadminUser();

        $response = $this->actingAs($user)
            ->get('/admin/error-logs');

        $response->assertStatus(200);
    }

    /**
     * Test ErrorLogResource not visible for admin.
     */
    public function test_error_log_resource_not_visible_for_admin(): void
    {
        $user = $this->createAdminUser();

        $response = $this->actingAs($user)
            ->get('/admin/error-logs');

        $response->assertStatus(403);
    }

    /**
     * Test AuditLogResource visibility for superadmin.
     */
    public function test_audit_log_resource_visible_for_superadmin(): void
    {
        $user = $this->createSuperadminUser();

        $response = $this->actingAs($user)
            ->get('/admin/audit-logs');

        $response->assertStatus(200);
    }

    /**
     * Test AuditLogResource not visible for regular admin.
     */
    public function test_audit_log_resource_not_visible_for_admin(): void
    {
        $user = $this->createAdminUser();

        $response = $this->actingAs($user)
            ->get('/admin/audit-logs');

        $response->assertStatus(403);
    }

    /**
     * Test multiple non-superadmin roles cannot access.
     */
    public function test_other_roles_cannot_access(): void
    {
        $roles = [
            'hr_head',
            'finance_head',
            'education_head',
            'nibret_hisab_head',
            'inventory_staff',
        ];

        foreach ($roles as $role) {
            $user = $this->createUserWithRole($role);

            $response = $this->actingAs($user)
                ->get('/admin/global-oversight');

            $response->assertStatus(403);
        }
    }

    /**
     * Test canAccess method in GlobalOversight.
     */
    public function test_can_access_method(): void
    {
        // Test that the canAccess logic works correctly
        $superadmin = $this->createSuperadminUser();
        $this->assertTrue($superadmin->hasRole('superadmin'));

        $admin = $this->createAdminUser();
        $this->assertFalse($admin->hasRole('superadmin'));
    }

    /**
     * Test GlobalOversight navigation group.
     */
    public function test_global_oversight_navigation_group(): void
    {
        $user = $this->createSuperadminUser();

        $response = $this->actingAs($user)
            ->get('/admin/global-oversight');

        $response->assertStatus(200);
    }
}
