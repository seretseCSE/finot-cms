<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\TestRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Spatie\Permission\Models\Role;

class RoleDashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * All roles that should be tested for dashboard access.
     */
    protected array $roles = [
        'superadmin',
        'admin',
        'education_head',
        'education_monitor',
        'finance_head',
        'hr_head',
        'staff',
    ];

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestRoleSeeder::class);
    }

    /**
     * Test that all roles from the seeder can be created and assigned to users.
     */
    public function test_all_roles_exist_in_database(): void
    {
        foreach ($this->roles as $roleName) {
            $this->assertDatabaseHas('roles', [
                'name' => $roleName,
                'guard_name' => 'web',
            ]);
        }
    }

    /**
     * Test that a user can be assigned each role.
     */
    public function test_user_can_be_assigned_each_role(): void
    {
        foreach ($this->roles as $roleName) {
            $user = User::factory()->create();
            $user->assignRole($roleName);

            $this->assertTrue(
                $user->hasRole($roleName),
                "User should have the role: {$roleName}"
            );
        }
    }

    /**
     * Test that users with all roles can authenticate.
     */
    public function test_all_roles_can_authenticate(): void
    {
        foreach ($this->roles as $roleName) {
            // Create a user with the specific role
            $user = User::factory()->create([
                'is_active' => true,
                'temp_password_changed' => true,
            ]);
            $user->assignRole($roleName);

            // Verify the user can authenticate
            $this->actingAs($user);
            $this->assertAuthenticatedAs($user);
        }
    }

    /**
     * Test superadmin permissions.
     */
    public function test_superadmin_permissions(): void
    {
        $superadmin = User::factory()->create([
            'is_active' => true,
            'temp_password_changed' => true,
        ]);
        $superadmin->assignRole('superadmin');

        // Superadmin should have access to all resources
        $this->assertTrue($superadmin->hasPermissionTo('system.settings'));
        $this->assertTrue($superadmin->hasPermissionTo('users.view'));
        $this->assertTrue($superadmin->hasPermissionTo('members.view'));
        $this->assertTrue($superadmin->hasPermissionTo('contributions.view'));
        $this->assertTrue($superadmin->hasPermissionTo('classes.view'));
        $this->assertTrue($superadmin->hasPermissionTo('teachers.view'));
    }

    /**
     * Test admin permissions.
     */
    public function test_admin_permissions(): void
    {
        $admin = User::factory()->create([
            'is_active' => true,
            'temp_password_changed' => true,
        ]);
        $admin->assignRole('admin');

        // Admin should have most permissions
        $this->assertTrue($admin->hasPermissionTo('users.view'));
        $this->assertTrue($admin->hasPermissionTo('members.view'));
        $this->assertTrue($admin->hasPermissionTo('contributions.view'));
        $this->assertTrue($admin->hasPermissionTo('classes.view'));
    }

    /**
     * Test education_head permissions.
     */
    public function test_education_head_permissions(): void
    {
        $educationHead = User::factory()->create([
            'is_active' => true,
            'temp_password_changed' => true,
        ]);
        $educationHead->assignRole('education_head');

        // Education head should have education-related permissions
        $this->assertTrue($educationHead->hasPermissionTo('classes.view'));
        $this->assertTrue($educationHead->hasPermissionTo('teachers.view'));
        $this->assertTrue($educationHead->hasPermissionTo('attendance.view'));
        $this->assertTrue($educationHead->hasPermissionTo('academic_years.view'));
        $this->assertTrue($educationHead->hasPermissionTo('members.view'));

        // Should not have finance permissions
        $this->assertFalse($educationHead->hasPermissionTo('contributions.record'));
    }

    /**
     * Test education_monitor permissions.
     */
    public function test_education_monitor_permissions(): void
    {
        $educationMonitor = User::factory()->create([
            'is_active' => true,
            'temp_password_changed' => true,
        ]);
        $educationMonitor->assignRole('education_monitor');

        // Education monitor should have limited education permissions
        $this->assertTrue($educationMonitor->hasPermissionTo('attendance.view'));
        $this->assertTrue($educationMonitor->hasPermissionTo('attendance.mark'));
        $this->assertTrue($educationMonitor->hasPermissionTo('members.view'));

        // Should not have admin permissions
        $this->assertFalse($educationMonitor->hasPermissionTo('users.view'));
        $this->assertFalse($educationMonitor->hasPermissionTo('classes.create'));
    }

    /**
     * Test finance_head permissions.
     */
    public function test_finance_head_permissions(): void
    {
        $financeHead = User::factory()->create([
            'is_active' => true,
            'temp_password_changed' => true,
        ]);
        $financeHead->assignRole('finance_head');

        // Finance head should have finance-related permissions
        $this->assertTrue($financeHead->hasPermissionTo('contributions.view'));
        $this->assertTrue($financeHead->hasPermissionTo('contributions.record'));
        $this->assertTrue($financeHead->hasPermissionTo('contributions.reports'));
        $this->assertTrue($financeHead->hasPermissionTo('members.view'));

        // Should not have education permissions
        $this->assertFalse($financeHead->hasPermissionTo('classes.create'));
        $this->assertFalse($financeHead->hasPermissionTo('teachers.manage'));
    }

    /**
     * Test hr_head permissions.
     */
    public function test_hr_head_permissions(): void
    {
        $hrHead = User::factory()->create([
            'is_active' => true,
            'temp_password_changed' => true,
        ]);
        $hrHead->assignRole('hr_head');

        // HR head should have HR-related permissions
        $this->assertTrue($hrHead->hasPermissionTo('members.view'));
        $this->assertTrue($hrHead->hasPermissionTo('members.create'));
        $this->assertTrue($hrHead->hasPermissionTo('members.update'));
        $this->assertTrue($hrHead->hasPermissionTo('groups.view'));
        $this->assertTrue($hrHead->hasPermissionTo('users.view'));

        // Should not have finance permissions
        $this->assertFalse($hrHead->hasPermissionTo('contributions.record'));
    }

    /**
     * Test staff permissions.
     */
    public function test_staff_permissions(): void
    {
        $staff = User::factory()->create([
            'is_active' => true,
            'temp_password_changed' => true,
        ]);
        $staff->assignRole('staff');

        // Staff should have limited permissions
        $this->assertTrue($staff->hasPermissionTo('department_resources.view'));

        // Should not have admin permissions
        $this->assertFalse($staff->hasPermissionTo('users.view'));
        $this->assertFalse($staff->hasPermissionTo('members.delete'));
    }

    /**
     * Test that user with unchanged temp password cannot access panel.
     */
    public function test_user_with_unchanged_temp_password_cannot_access_panel(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'temp_password_changed' => false,
        ]);
        $user->assignRole('staff');

        // The canAccessPanel method should return false
        $panel = app(\Filament\Panel::class);
        $this->assertFalse($user->canAccessPanel($panel));
    }

    /**
     * Test that inactive user cannot access panel.
     */
    public function test_inactive_user_cannot_access_panel(): void
    {
        $user = User::factory()->create([
            'is_active' => false,
            'temp_password_changed' => true,
        ]);
        $user->assignRole('staff');

        // The canAccessPanel method should return false
        $panel = app(\Filament\Panel::class);
        $this->assertFalse($user->canAccessPanel($panel));
    }

    /**
     * Test that active user with changed password can access panel.
     */
    public function test_active_user_with_changed_password_can_access_panel(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'temp_password_changed' => true,
        ]);
        $user->assignRole('staff');

        // The canAccessPanel method should return true
        $panel = app(\Filament\Panel::class);
        $this->assertTrue($user->canAccessPanel($panel));
    }

    /**
     * Test that each role can access their specific resources.
     */
    public function test_role_specific_resource_access(): void
    {
        // Test superadmin can access all resources
        $superadmin = User::factory()->create([
            'is_active' => true,
            'temp_password_changed' => true,
        ]);
        $superadmin->assignRole('superadmin');

        $this->assertTrue($superadmin->can('users.view'));
        $this->assertTrue($superadmin->can('members.view'));
        $this->assertTrue($superadmin->can('contributions.view'));
        $this->assertTrue($superadmin->can('classes.view'));

        // Test education_head can only access education resources
        $educationHead = User::factory()->create([
            'is_active' => true,
            'temp_password_changed' => true,
        ]);
        $educationHead->assignRole('education_head');

        $this->assertTrue($educationHead->can('classes.view'));
        $this->assertTrue($educationHead->can('teachers.view'));
        $this->assertFalse($educationHead->can('users.delete'));

        // Test finance_head can only access finance resources
        $financeHead = User::factory()->create([
            'is_active' => true,
            'temp_password_changed' => true,
        ]);
        $financeHead->assignRole('finance_head');

        $this->assertTrue($financeHead->can('contributions.view'));
        $this->assertTrue($financeHead->can('contributions.record'));
        $this->assertFalse($financeHead->can('classes.create'));
    }

    /**
     * Test multiple roles can be assigned to a single user.
     */
    public function test_user_can_have_multiple_roles(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'temp_password_changed' => true,
        ]);
        $user->assignRole(['finance_head', 'hr_head']);

        $this->assertTrue($user->hasRole('finance_head'));
        $this->assertTrue($user->hasRole('hr_head'));

        // Should have permissions from both roles
        $this->assertTrue($user->hasPermissionTo('contributions.view'));
        $this->assertTrue($user->hasPermissionTo('members.create'));
    }

    /**
     * Test all roles have appropriate permissions.
     */
    public function test_all_roles_have_appropriate_permissions(): void
    {
        $rolePermissions = [
            'superadmin' => ['system.settings', 'users.view', 'members.view', 'contributions.view'],
            'admin' => ['users.view', 'members.view', 'contributions.view'],
            'education_head' => ['classes.view', 'teachers.view', 'attendance.view'],
            'education_monitor' => ['attendance.view', 'attendance.mark', 'members.view'],
            'finance_head' => ['contributions.view', 'contributions.record', 'contributions.reports'],
            'hr_head' => ['members.view', 'members.create', 'groups.view', 'users.view'],
            'staff' => ['department_resources.view'],
        ];

        foreach ($rolePermissions as $roleName => $permissions) {
            $user = User::factory()->create([
                'is_active' => true,
                'temp_password_changed' => true,
            ]);
            $user->assignRole($roleName);

            foreach ($permissions as $permission) {
                $this->assertTrue(
                    $user->hasPermissionTo($permission),
                    "Role '{$roleName}' should have permission '{$permission}'"
                );
            }
        }
    }

    /**
     * Test role labels are set correctly.
     */
    public function test_role_labels_are_set_correctly(): void
    {
        $roleLabels = [
            'superadmin' => 'Super Admin',
            'admin' => 'Admin',
            'education_head' => 'Education Head',
            'education_monitor' => 'Education Monitor',
            'finance_head' => 'Finance Head',
            'hr_head' => 'HR Head',
            'staff' => 'Staff',
        ];

        foreach ($roleLabels as $roleName => $expectedLabel) {
            $role = Role::where('name', $roleName)->first();
            $this->assertNotNull($role, "Role '{$roleName}' should exist");
            $this->assertEquals(
                $expectedLabel,
                $role->label,
                "Role '{$roleName}' should have label '{$expectedLabel}'"
            );
        }
    }

    /**
     * Test user factory creates valid users for all roles.
     */
    public function test_user_factory_creates_valid_users_for_all_roles(): void
    {
        foreach ($this->roles as $roleName) {
            $user = User::factory()->create();
            $user->assignRole($roleName);

            $this->assertTrue($user->is_active, "User with role '{$roleName}' should be active by default");
            $this->assertTrue($user->temp_password_changed, "User with role '{$roleName}' should have temp_password_changed by default");
            $this->assertFalse($user->is_locked, "User with role '{$roleName}' should not be locked by default");
            $this->assertEquals(0, $user->failed_login_attempts, "User with role '{$roleName}' should have 0 failed login attempts");
        }
    }
}
