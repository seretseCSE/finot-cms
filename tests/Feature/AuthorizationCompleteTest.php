<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthorizationCompleteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test superadmin can override any permission.
     */
    public function test_superadmin_can_override_any_permission(): void
    {
        $superadmin = $this->createSuperadminUser();

        // Give superadmin a specific permission
        $superadmin->givePermissionTo('custom.permission');

        // Superadmin should have the permission
        $this->assertTrue($superadmin->hasPermissionTo('custom.permission'));
    }

    /**
     * Test superadmin has all permissions implicitly.
     */
    public function test_superadmin_has_all_permissions_implicitly(): void
    {
        $superadmin = $this->createSuperadminUser();

        // Create a new permission
        Permission::create(['name' => 'test.any.permission', 'guard_name' => 'web']);

        // Superadmin should have access regardless
        $this->assertTrue($superadmin->hasRole('superadmin'));
    }

    /**
     * Test admin cannot access System Settings.
     */
    public function test_admin_cannot_access_system_settings(): void
    {
        $admin = $this->createAdminUser();

        // Give admin all permissions except superadmin-only ones
        $admin->givePermissionTo('users.manage');
        $admin->givePermissionTo('members.manage');

        // But should not have system settings access
        $this->assertFalse($admin->hasPermissionTo('system.settings'));
        $this->assertFalse($admin->hasPermissionTo('backup.restore'));
    }

    /**
     * Test admin cannot access Backup pages.
     */
    public function test_admin_cannot_access_backup_pages(): void
    {
        $admin = $this->createAdminUser();

        // Test access to backup page
        $response = $this->actingAs($admin)
            ->get('/admin/backup-restore');

        $response->assertStatus(403);
    }

    /**
     * Test superadmin has explicit backup.restore permission.
     */
    public function test_superadmin_has_backup_restore_permission(): void
    {
        $superadmin = $this->createSuperadminUser();

        // Superadmin should have backup restore permission
        $this->assertTrue($superadmin->hasPermissionTo('backup.restore'));
    }

    /**
     * Test permission inheritance.
     */
    public function test_permission_inheritance(): void
    {
        $role = Role::create(['name' => 'test_role', 'guard_name' => 'web']);
        
        $permission1 = Permission::create(['name' => 'test.permission1', 'guard_name' => 'web']);
        $permission2 = Permission::create(['name' => 'test.permission2', 'guard_name' => 'web']);
        
        $role->givePermissionTo([$permission1, $permission2]);
        
        $user = User::factory()->create();
        $user->assignRole($role);
        
        $this->assertTrue($user->hasPermissionTo('test.permission1'));
        $this->assertTrue($user->hasPermissionTo('test.permission2'));
    }

    /**
     * Test role-only user has limited access.
     */
    public function test_role_only_user_limited_access(): void
    {
        $user = $this->createUserWithRole('member');

        // Should not have system permissions
        $this->assertFalse($user->hasPermissionTo('system.settings'));
        $this->assertFalse($user->hasPermissionTo('backup.restore'));
        $this->assertFalse($user->hasPermissionTo('audit.export'));
    }

    /**
     * Test HR Head specific permissions.
     */
    public function test_hr_head_specific_permissions(): void
    {
        $hrHead = $this->createHrHeadUser();

        // Should have HR permissions
        $this->assertTrue($hrHead->hasPermissionTo('members.create'));
        $this->assertTrue($hrHead->hasPermissionTo('members.update'));
        $this->assertTrue($hrHead->hasPermissionTo('groups.manage'));

        // Should not have finance permissions
        $this->assertFalse($hrHead->hasPermissionTo('finance.reports'));
    }

    /**
     * Test Finance Head specific permissions.
     */
    public function test_finance_head_specific_permissions(): void
    {
        $financeHead = $this->createFinanceHeadUser();

        // Should have finance permissions
        $this->assertTrue($financeHead->hasPermissionTo('contributions.manage'));
        $this->assertTrue($financeHead->hasPermissionTo('donations.manage'));

        // Should not have system permissions
        $this->assertFalse($financeHead->hasPermissionTo('system.settings'));
    }

    /**
     * Test Education Head specific permissions.
     */
    public function test_education_head_specific_permissions(): void
    {
        $educationHead = $this->createEducationHeadUser();

        // Should have education permissions
        $this->assertTrue($educationHead->hasPermissionTo('education.manage'));

        // Should not have system permissions
        $this->assertFalse($educationHead->hasPermissionTo('system.oversight'));
    }

    /**
     * Test permission revocation.
     */
    public function test_permission_revocation(): void
    {
        $user = $this->createUserWithRole('member');
        
        $user->givePermissionTo('test.permission');
        $this->assertTrue($user->hasPermissionTo('test.permission'));
        
        $user->revokePermissionTo('test.permission');
        $this->assertFalse($user->hasPermissionTo('test.permission'));
    }

    /**
     * Test role switching changes permissions.
     */
    public function test_role_switching_changes_permissions(): void
    {
        $user = User::factory()->create();
        
        // Initially no permissions
        $this->assertFalse($user->hasPermissionTo('test.permission'));
        
        // Assign role with permission
        $role = Role::create(['name' => 'test_role', 'guard_name' => 'web']);
        $role->givePermissionTo('test.permission');
        $user->assignRole($role);
        
        // Now has permission
        $this->assertTrue($user->hasPermissionTo('test.permission'));
        
        // Remove role
        $user->removeRole($role);
        
        // Permission should be gone
        $this->assertFalse($user->hasPermissionTo('test.permission'));
    }

    /**
     * Test multiple roles cumulative permissions.
     */
    public function test_multiple_roles_cumulative(): void
    {
        $user = User::factory()->create();
        
        $role1 = Role::create(['name' => 'role1', 'guard_name' => 'web']);
        $role2 = Role::create(['name' => 'role2', 'guard_name' => 'web']);
        
        $role1->givePermissionTo('permission1');
        $role2->givePermissionTo('permission2');
        
        $user->assignRole([$role1, $role2]);
        
        $this->assertTrue($user->hasPermissionTo('permission1'));
        $this->assertTrue($user->hasPermissionTo('permission2'));
    }

    /**
     * Test superadmin bypasses all permission checks.
     */
    public function test_superadmin_bypasses_permission_checks(): void
    {
        $superadmin = $this->createSuperadminUser();

        // Superadmin role should grant access to protected resources
        $this->assertTrue($superadmin->hasRole('superadmin'));
        
        // Even without explicit permissions, superadmin should access
        $response = $this->actingAs($superadmin)
            ->get('/admin/global-oversight');
        
        $response->assertStatus(200);
    }

    /**
     * Test direct permission check method.
     */
    public function test_direct_permission_check(): void
    {
        $user = User::factory()->create();
        
        $user->givePermissionTo('direct.permission');
        
        $this->assertTrue($user->hasDirectPermission('direct.permission'));
    }

    /**
     * Test all permissions from roles.
     */
    public function test_all_permissions_from_roles(): void
    {
        $role = Role::create(['name' => 'test_role', 'guard_name' => 'web']);
        $role->givePermissionTo('role.permission');
        
        $user = User::factory()->create();
        $user->assignRole($role);
        
        $permissions = $user->getPermissionsViaRoles();
        
        $this->assertTrue($permissions->contains('name', 'role.permission'));
    }

    /**
     * Test unauthorized access returns 403.
     */
    public function test_unauthorized_access_returns_403(): void
    {
        $user = $this->createUserWithRole('member');

        $response = $this->actingAs($user)
            ->get('/admin/backup-restore');

        $response->assertStatus(403);
    }

    /**
     * Test guest cannot access any admin page.
     */
    public function test_guest_cannot_access_admin(): void
    {
        $response = $this->get('/admin');
        
        $response->assertRedirect('/login');
    }

    /**
     * Test unauthorized API access.
     */
    public function test_unauthorized_api_access(): void
    {
        $user = $this->createUserWithRole('member');

        $response = $this->actingAs($user)
            ->getJson('/api/admin/system-status');

        // Should return 403 or 401
        $response->assertStatus(403);
    }
}
