<?php

namespace Tests;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Set up the application for testing.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Get or create a department by name.
     */
    protected function getOrCreateDepartment(string $name): Department
    {
        return Department::firstOrCreate(
            ['name_en' => $name],
            [
                'name_en' => $name,
                'name_am' => $name,
                'code' => strtoupper(substr($name, 0, 3)),
                'is_active' => true,
            ]
        );
    }

    /**
     * Get or create a role by name.
     */
    protected function getOrCreateRole(string $name): Role
    {
        return Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    /**
     * Create a user with a specific role and department using factory.
     */
    protected function createUserWithRole(string $roleName, ?string $departmentName = null): User
    {
        $factory = User::factory()->withRole($roleName);

        if ($departmentName) {
            $factory->withDepartment($departmentName);
        }

        return $factory->create();
    }

    /**
     * Create a Superadmin user.
     */
    protected function createSuperadminUser(): User
    {
        return User::factory()->superadmin()->create();
    }

    /**
     * Create an Admin user.
     */
    protected function createAdminUser(): User
    {
        return User::factory()->admin()->create();
    }

    /**
     * Create an HR Head user.
     */
    protected function createHrHeadUser(): User
    {
        return User::factory()->hrHead()->create();
    }

    /**
     * Create a Finance Head user.
     */
    protected function createFinanceHeadUser(): User
    {
        return User::factory()->financeHead()->create();
    }

    /**
     * Create a Nibret Hisab Head user.
     */
    protected function createNibretHisabHeadUser(): User
    {
        return User::factory()->nibretHisabHead()->create();
    }

    /**
     * Create an Inventory Staff user.
     */
    protected function createInventoryStaffUser(): User
    {
        return User::factory()->inventoryStaff()->create();
    }

    /**
     * Create an Education Head user.
     */
    protected function createEducationHeadUser(): User
    {
        return User::factory()->educationHead()->create();
    }

    /**
     * Create an Education Monitor user.
     */
    protected function createEducationMonitorUser(): User
    {
        return User::factory()->educationMonitor()->create();
    }

    /**
     * Create a Worship Monitor user.
     */
    protected function createWorshipMonitorUser(): User
    {
        return User::factory()->worshipMonitor()->create();
    }

    /**
     * Create a Mezmur Head user.
     */
    protected function createMezmurHeadUser(): User
    {
        return User::factory()->mezmurHead()->create();
    }

    /**
     * Create an AV Head user.
     */
    protected function createAvHeadUser(): User
    {
        return User::factory()->avHead()->create();
    }

    /**
     * Create a Charity Head user.
     */
    protected function createCharityHeadUser(): User
    {
        return User::factory()->charityHead()->create();
    }

    /**
     * Create a Tour Head user.
     */
    protected function createTourHeadUser(): User
    {
        return User::factory()->tourHead()->create();
    }

    /**
     * Create an Internal Relations Head user.
     */
    protected function createInternalRelationsHeadUser(): User
    {
        return User::factory()->internalRelationsHead()->create();
    }

    /**
     * Create a Department Secretary user.
     */
    protected function createDepartmentSecretaryUser(?string $departmentName = 'Internal Relations'): User
    {
        return User::factory()->departmentSecretary($departmentName)->create();
    }

    /**
     * Create a General Staff user.
     */
    protected function createStaffUser(?string $departmentName = 'Internal Relations'): User
    {
        return User::factory()->staff($departmentName)->create();
    }

    /**
     * Assert user has a specific role.
     */
    protected function assertUserHasRole(User $user, string $roleName): void
    {
        $this->assertTrue(
            $user->hasRole($roleName),
            "User {$user->name} should have role '{$roleName}'"
        );
    }

    /**
     * Assert user does not have a specific role.
     */
    protected function assertUserDoesNotHaveRole(User $user, string $roleName): void
    {
        $this->assertFalse(
            $user->hasRole($roleName),
            "User {$user->name} should not have role '{$roleName}'"
        );
    }

    /**
     * Assert user has permission.
     */
    protected function assertUserHasPermission(User $user, string $permission): void
    {
        $this->assertTrue(
            $user->hasPermissionTo($permission),
            "User {$user->name} should have permission '{$permission}'"
        );
    }

    /**
     * Assert user does not have permission.
     */
    protected function assertUserDoesNotHavePermission(User $user, string $permission): void
    {
        $this->assertFalse(
            $user->hasPermissionTo($permission),
            "User {$user->name} should not have permission '{$permission}'"
        );
    }

    /**
     * Assert user can access a route.
     */
    protected function assertUserCanAccessRoute(User $user, string $route, int $expectedStatus = 200): void
    {
        $response = $this->actingAs($user)->get($route);
        $response->assertStatus($expectedStatus);
    }

    /**
     * Assert user cannot access a route (403 Forbidden).
     */
    protected function assertUserCannotAccessRoute(User $user, string $route): void
    {
        $response = $this->actingAs($user)->get($route);
        $response->assertStatus(403);
    }

    /**
     * Assert user is redirected (401 Unauthorized).
     */
    protected function assertUserRedirectedToLogin(User $user, string $route): void
    {
        $response = $this->actingAs($user)->get($route);
        $response->assertRedirect('/login');
    }

    /**
     * Get an array of all test roles.
     */
    protected function getAllTestRoles(): array
    {
        return [
            'superadmin',
            'admin',
            'hr_head',
            'finance_head',
            'nibret_hisab_head',
            'inventory_staff',
            'education_head',
            'education_monitor',
            'worship_monitor',
            'mezmur_head',
            'av_head',
            'charity_head',
            'tour_head',
            'internal_relations_head',
            'department_secretary',
            'staff',
        ];
    }

    /**
     * Get roles that should have full system access.
     */
    protected function getAdminRoles(): array
    {
        return ['superadmin', 'admin'];
    }

    /**
     * Get roles that are department heads.
     */
    protected function getDepartmentHeadRoles(): array
    {
        return [
            'hr_head',
            'finance_head',
            'nibret_hisab_head',
            'education_head',
            'worship_monitor',
            'mezmur_head',
            'av_head',
            'charity_head',
            'tour_head',
            'internal_relations_head',
        ];
    }

    /**
     * Get roles that can manage users.
     */
    protected function getUserManagementRoles(): array
    {
        return ['superadmin', 'admin'];
    }

    /**
     * Get roles that can view audit logs.
     */
    protected function getAuditLogViewRoles(): array
    {
        return ['superadmin', 'admin'];
    }

    /**
     * Get roles that can export audit logs.
     */
    protected function getAuditLogExportRoles(): array
    {
        return ['superadmin'];
    }

    /**
     * Get roles that can view financial data.
     */
    protected function getFinancialViewRoles(): array
    {
        return ['superadmin', 'admin', 'finance_head', 'nibret_hisab_head', 'charity_head'];
    }

    /**
     * Get roles that can manage inventory.
     */
    protected function getInventoryManagementRoles(): array
    {
        return ['superadmin', 'admin', 'inventory_staff', 'nibret_hisab_head'];
    }

    /**
     * Get roles that can manage education.
     */
    protected function getEducationManagementRoles(): array
    {
        return ['superadmin', 'admin', 'education_head'];
    }

    /**
     * Get roles that can manage tours.
     */
    protected function getTourManagementRoles(): array
    {
        return ['superadmin', 'admin', 'tour_head'];
    }

    /**
     * Get roles that can manage charity.
     */
    protected function getCharityManagementRoles(): array
    {
        return ['superadmin', 'admin', 'charity_head'];
    }

    /**
     * Get roles that can manage members.
     */
    protected function getMemberManagementRoles(): array
    {
        return ['superadmin', 'admin', 'hr_head', 'internal_relations_head'];
    }
}
