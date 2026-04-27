<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalChurchSettingsAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test superadmin can access GlobalChurchSettings page.
     */
    public function test_superadmin_can_access_global_church_settings(): void
    {
        $user = $this->createSuperadminUser();

        $response = $this->actingAs($user)
            ->get('/admin/global-church-settings');

        $response->assertStatus(200);
    }

    /**
     * Test admin cannot access GlobalChurchSettings page.
     */
    public function test_admin_cannot_access_global_church_settings(): void
    {
        $user = $this->createAdminUser();

        $response = $this->actingAs($user)
            ->get('/admin/global-church-settings');

        // Should redirect or show 403
        $response->assertStatus(403);
    }

    /**
     * Test regular user cannot access GlobalChurchSettings page.
     */
    public function test_regular_user_cannot_access_global_church_settings(): void
    {
        $user = $this->createUserWithRole('member');

        $response = $this->actingAs($user)
            ->get('/admin/global-church-settings');

        // Should redirect or show 403
        $response->assertStatus(403);
    }

    /**
     * Test unauthenticated user cannot access GlobalChurchSettings page.
     */
    public function test_unauthenticated_user_cannot_access_global_church_settings(): void
    {
        $response = $this->get('/admin/global-church-settings');

        // Should redirect to login
        $response->assertRedirect('/login');
    }

    /**
     * Test HR Head cannot access GlobalChurchSettings page.
     */
    public function test_hr_head_cannot_access_global_church_settings(): void
    {
        $user = $this->createHrHeadUser();

        $response = $this->actingAs($user)
            ->get('/admin/global-church-settings');

        $response->assertStatus(403);
    }

    /**
     * Test Finance Head cannot access GlobalChurchSettings page.
     */
    public function test_finance_head_cannot_access_global_church_settings(): void
    {
        $user = $this->createFinanceHeadUser();

        $response = $this->actingAs($user)
            ->get('/admin/global-church-settings');

        $response->assertStatus(403);
    }

    /**
     * Test superadmin can update global settings.
     */
    public function test_superadmin_can_update_global_settings(): void
    {
        $user = $this->createSuperadminUser();

        $response = $this->actingAs($user)
            ->post('/admin/global-church-settings', [
                'church_name' => 'Test Church',
                'church_email' => 'test@church.com',
            ]);

        $response->assertSessionHas('success');
    }

    /**
     * Test admin cannot update global settings.
     */
    public function test_admin_cannot_update_global_settings(): void
    {
        $user = $this->createAdminUser();

        $response = $this->actingAs($user)
            ->post('/admin/global-church-settings', [
                'church_name' => 'Test Church',
            ]);

        $response->assertStatus(403);
    }

    /**
     * Test settings form displays correctly for superadmin.
     */
    public function test_settings_form_displays_for_superadmin(): void
    {
        $user = $this->createSuperadminUser();

        $response = $this->actingAs($user)
            ->get('/admin/global-church-settings');

        $response->assertSee('Global Church Settings');
    }

    /**
     * Test multiple roles cannot access.
     */
    public function test_multiple_roles_cannot_access(): void
    {
        $roles = [
            'education_head',
            'nibret_hisab_head',
            'inventory_staff',
            'music_head',
            'usher_head',
        ];

        foreach ($roles as $role) {
            $user = $this->createUserWithRole($role);

            $response = $this->actingAs($user)
                ->get('/admin/global-church-settings');

            $response->assertStatus(403);
        }
    }

    /**
     * Test permission-based access.
     */
    public function test_permission_based_access(): void
    {
        // Create user with specific permission
        $user = User::factory()->create();
        $user->givePermissionTo('settings.manage');

        // Should still require superadmin role according to the requirements
        $response = $this->actingAs($user)
            ->get('/admin/global-church-settings');

        // Should deny access as it's superadmin-only
        $response->assertStatus(403);
    }
}
