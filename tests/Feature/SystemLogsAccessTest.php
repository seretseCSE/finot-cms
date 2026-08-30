<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemLogsAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_error_log_viewer_visible_for_superadmin(): void
    {
        $user = $this->createSuperadminUser();

        $response = $this->actingAs($user)
            ->get('/admin/error-log-viewer');

        $response->assertStatus(200);
    }

    public function test_error_log_viewer_not_visible_for_admin(): void
    {
        $user = $this->createAdminUser();

        $response = $this->actingAs($user)
            ->get('/admin/error-log-viewer');

        $response->assertStatus(403);
    }

    public function test_audit_log_resource_visible_for_superadmin(): void
    {
        $user = $this->createSuperadminUser();

        $response = $this->actingAs($user)
            ->get('/admin/audit-logs');

        $response->assertStatus(200);
    }

    public function test_audit_log_resource_not_visible_for_regular_admin(): void
    {
        $user = $this->createAdminUser();

        $response = $this->actingAs($user)
            ->get('/admin/audit-logs');

        $response->assertStatus(403);
    }
}
