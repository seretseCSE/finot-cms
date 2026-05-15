<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function global_church_settings_accessible_to_superadmin(): void
    {
        $user = $this->createSuperadminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/global-church-settings');
        $this->assertNotEquals(404, $response->getStatusCode(), 'Route not found');
        $this->assertNotEquals(403, $response->getStatusCode(), 'Forbidden');
    }

    #[Test]
    public function global_church_settings_not_accessible_to_admin(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/global-church-settings');
        $response->assertStatus(403);
    }

    #[Test]
    public function global_oversight_accessible_to_superadmin(): void
    {
        $user = $this->createSuperadminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/global-oversight');
        $this->assertNotEquals(404, $response->getStatusCode(), 'Route not found');
        $this->assertNotEquals(403, $response->getStatusCode(), 'Forbidden');
    }

    #[Test]
    public function backup_restore_accessible_to_superadmin(): void
    {
        $user = $this->createSuperadminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/backup-restore');
        $this->assertNotEquals(404, $response->getStatusCode(), 'Route not found');
        $this->assertNotEquals(403, $response->getStatusCode(), 'Forbidden');
    }

    #[Test]
    public function help_docs_accessible(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/help-docs');
        $this->assertNotEquals(404, $response->getStatusCode(), 'Route not found');
        $this->assertNotEquals(403, $response->getStatusCode(), 'Forbidden');
    }

    #[Test]
    public function departments_resource_accessible(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/departments');
        $this->assertNotEquals(404, $response->getStatusCode(), 'Route not found');
        $this->assertNotEquals(403, $response->getStatusCode(), 'Forbidden');
    }

    #[Test]
    public function duplicate_records_resource_accessible(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/duplicate-records');
        $this->assertNotEquals(404, $response->getStatusCode(), 'Route not found');
        $this->assertNotEquals(403, $response->getStatusCode(), 'Forbidden');
    }

    #[Test]
    public function temporary_filters_resource_accessible(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/temporary-filters');
        $this->assertNotEquals(404, $response->getStatusCode(), 'Route not found');
        $this->assertNotEquals(403, $response->getStatusCode(), 'Forbidden');
    }

    #[Test]
    public function manage_active_sessions_accessible(): void
    {
        $user = $this->createSuperadminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/manage-active-sessions');
        $this->assertNotEquals(404, $response->getStatusCode(), 'Route not found');
        $this->assertNotEquals(403, $response->getStatusCode(), 'Forbidden');
    }

    #[Test]
    public function manage_custom_options_accessible(): void
    {
        $user = $this->createSuperadminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/manage-custom-options');
        $this->assertNotEquals(404, $response->getStatusCode(), 'Route not found');
        $this->assertNotEquals(403, $response->getStatusCode(), 'Forbidden');
    }
}
