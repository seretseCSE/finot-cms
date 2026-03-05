<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleAccessControlTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test superadmin can access all admin routes.
     */
    public function test_superadmin_can_access_all_admin_routes(): void
    {
        $user = $this->createSuperadminUser();

        // Should access all admin routes
        $this->assertTrue($user->hasPermissionTo('users.manage'));
        $this->assertTrue($user->hasPermissionTo('audit.export'));
        $this->assertTrue($user->hasPermissionTo('backup.restore'));
    }

    /**
     * Test admin cannot access system settings.
     */
    public function test_admin_cannot_access_system_settings(): void
    {
        $user = $this->createAdminUser();

        $this->assertFalse($user->hasPermissionTo('audit.export'));
        $this->assertFalse($user->hasPermissionTo('backup.restore'));
        $this->assertFalse($user->hasPermissionTo('system.oversight'));
    }

    /**
     * Test HR head can only access HR-related routes.
     */
    public function test_hr_head_access_to_hr_routes(): void
    {
        $user = $this->createHrHeadUser();

        // Should have access
        $this->assertTrue($user->hasPermissionTo('members.create'));
        $this->assertTrue($user->hasPermissionTo('members.update'));
        $this->assertTrue($user->hasPermissionTo('groups.manage'));

        // Should not have access
        $this->assertFalse($user->hasPermissionTo('education.manage'));
        $this->assertFalse($user->hasPermissionTo('tours.manage'));
        $this->assertFalse($user->hasPermissionTo('inventory.manage'));
        $this->assertFalse($user->hasPermissionTo('users.manage'));
    }

    /**
     * Test finance head can access financial routes.
     */
    public function test_finance_head_access_to_financial_routes(): void
    {
        $user = $this->createFinanceHeadUser();

        // Should have access
        $this->assertTrue($user->hasPermissionTo('contributions.manage'));
        $this->assertTrue($user->hasPermissionTo('donations.manage'));
        $this->assertTrue($user->hasPermissionTo('financial.reports.view'));

        // Should not have access
        $this->assertFalse($user->hasPermissionTo('education.manage'));
        $this->assertFalse($user->hasPermissionTo('tours.manage'));
    }

    /**
     * Test education head can access education routes.
     */
    public function test_education_head_access_to_education_routes(): void
    {
        $user = $this->createEducationHeadUser();

        // Should have access
        $this->assertTrue($user->hasPermissionTo('education.manage'));
        $this->assertTrue($user->hasPermissionTo('education.class.manage'));
        $this->assertTrue($user->hasPermissionTo('teachers.manage'));

        // Should not have access
        $this->assertFalse($user->hasPermissionTo('contributions.manage'));
        $this->assertFalse($user->hasPermissionTo('tours.manage'));
    }

    /**
     * Test education monitor has limited education access.
     */
    public function test_education_monitor_has_limited_access(): void
    {
        $user = $this->createEducationMonitorUser();

        // Should have access
        $this->assertTrue($user->hasPermissionTo('education.attendance.create'));
        $this->assertTrue($user->hasPermissionTo('education.attendance.record'));

        // Should not have access
        $this->assertFalse($user->hasPermissionTo('education.manage'));
        $this->assertFalse($user->hasPermissionTo('education.class.manage'));
        $this->assertFalse($user->hasPermissionTo('teachers.manage'));
    }

    /**
     * Test tour head can access tour routes.
     */
    public function test_tour_head_access_to_tour_routes(): void
    {
        $user = $this->createTourHeadUser();

        // Should have access
        $this->assertTrue($user->hasPermissionTo('tours.manage'));
        $this->assertTrue($user->hasPermissionTo('tours.create'));
        $this->assertTrue($user->hasPermissionTo('tours.attendance.manage'));

        // Should not have access
        $this->assertFalse($user->hasPermissionTo('education.manage'));
        $this->assertFalse($user->hasPermissionTo('contributions.manage'));
    }

    /**
     * Test charity head can access charity routes.
     */
    public function test_charity_head_access_to_charity_routes(): void
    {
        $user = $this->createCharityHeadUser();

        // Should have access
        $this->assertTrue($user->hasPermissionTo('charity.manage'));
        $this->assertTrue($user->hasPermissionTo('charity.beneficiaries.manage'));
        $this->assertTrue($user->hasPermissionTo('charity.aid.distribute'));

        // Should not have access
        $this->assertFalse($user->hasPermissionTo('education.manage'));
        $this->assertFalse($user->hasPermissionTo('tours.manage'));
    }

    /**
     * Test inventory staff can access inventory routes.
     */
    public function test_inventory_staff_access_to_inventory_routes(): void
    {
        $user = $this->createInventoryStaffUser();

        // Should have access
        $this->assertTrue($user->hasPermissionTo('inventory.manage'));
        $this->assertTrue($user->hasPermissionTo('inventory.items.manage'));
        $this->assertTrue($user->hasPermissionTo('inventory.movements.manage'));

        // Should not have access
        $this->assertFalse($user->hasPermissionTo('education.manage'));
        $this->assertFalse($user->hasPermissionTo('tours.manage'));
    }

    /**
     * Test AV head can access media routes.
     */
    public function test_av_head_access_to_media_routes(): void
    {
        $user = $this->createAvHeadUser();

        // Should have access
        $this->assertTrue($user->hasPermissionTo('media.manage'));
        $this->assertTrue($user->hasPermissionTo('blog.manage'));
        $this->assertTrue($user->hasPermissionTo('announcements.manage'));

        // Should not have access
        $this->assertFalse($user->hasPermissionTo('education.manage'));
        $this->assertFalse($user->hasPermissionTo('contributions.manage'));
    }

    /**
     * Test department secretary has limited access (no delete).
     */
    public function test_department_secretary_limited_access(): void
    {
        $user = $this->createDepartmentSecretaryUser();

        // Should have create/update access
        $this->assertTrue($user->hasPermissionTo('members.create'));
        $this->assertTrue($user->hasPermissionTo('members.update'));

        // Should not have delete or management access
        $this->assertFalse($user->hasPermissionTo('members.delete'));
        $this->assertFalse($user->hasPermissionTo('groups.manage'));
        $this->assertFalse($user->hasPermissionTo('users.manage'));
    }

    /**
     * Test general staff has minimal access.
     */
    public function test_general_staff_minimal_access(): void
    {
        $user = $this->createStaffUser();

        // Should have view access
        $this->assertTrue($user->hasPermissionTo('members.view'));
        $this->assertTrue($user->hasPermissionTo('reports.view'));

        // Should not have create/update/delete or management access
        $this->assertFalse($user->hasPermissionTo('members.create'));
        $this->assertFalse($user->hasPermissionTo('members.update'));
        $this->assertFalse($user->hasPermissionTo('groups.manage'));
        $this->assertFalse($user->hasPermissionTo('users.manage'));
    }

    /**
     * Test user cannot access routes outside their permissions.
     */
    public function test_user_cannot_access_unauthorized_routes(): void
    {
        $staffUser = $this->createStaffUser();

        // Staff should not be able to manage users
        $this->assertFalse($staffUser->hasPermissionTo('users.manage'));
        $this->assertFalse($staffUser->hasPermissionTo('users.assign_roles'));
        $this->assertFalse($staffUser->hasPermissionTo('education.manage'));
    }

    /**
     * Test department-scoped data access.
     */
    public function test_department_scoped_data_access(): void
    {
        $educationUser = $this->createEducationHeadUser();
        $financeUser = $this->createFinanceHeadUser();

        // Users should have department_id set
        $this->assertNotNull($educationUser->department_id);
        $this->assertNotNull($financeUser->department_id);

        // Different departments
        $this->assertNotEquals(
            $educationUser->department_id,
            $financeUser->department_id
        );
    }

    /**
     * Test superadmin has no department scope.
     */
    public function test_superadmin_has_no_department_scope(): void
    {
        $superadmin = $this->createSuperadminUser();

        $this->assertNull($superadmin->department_id);
    }

    /**
     * Test admin has no department scope.
     */
    public function test_admin_has_no_department_scope(): void
    {
        $admin = $this->createAdminUser();

        $this->assertNull($admin->department_id);
    }

    /**
     * Test staff user has department scope.
     */
    public function test_staff_user_has_department_scope(): void
    {
        $staff = $this->createStaffUser('Education');

        $this->assertNotNull($staff->department_id);
    }

    /**
     * Test all roles can access dashboard.
     */
    public function test_all_roles_can_access_dashboard(): void
    {
        $roles = $this->getAllTestRoles();

        foreach ($roles as $role) {
            $user = $this->createUserWithRole($role, 'Internal Relations');

            // Each role should be able to authenticate
            $this->assertTrue($user->is_active);
            $this->assertTrue($user->hasRole($role));
        }
    }

    /**
     * Test role hierarchy - admin roles can manage users.
     */
    public function test_admin_roles_can_manage_users(): void
    {
        $superadmin = $this->createSuperadminUser();
        $admin = $this->createAdminUser();
        $hrHead = $this->createHrHeadUser();
        $staff = $this->createStaffUser();

        // Superadmin and admin should have user management permissions
        $this->assertTrue($superadmin->hasPermissionTo('users.manage'));
        $this->assertTrue($admin->hasPermissionTo('users.manage'));

        // Other roles should not
        $this->assertFalse($hrHead->hasPermissionTo('users.manage'));
        $this->assertFalse($staff->hasPermissionTo('users.manage'));
    }

    /**
     * Test role-based dashboard content visibility.
     */
    public function test_role_based_dashboard_content_visibility(): void
    {
        $educationHead = $this->createEducationHeadUser();
        $financeHead = $this->createFinanceHeadUser();

        // Education head should see education-related dashboard widgets
        $this->assertTrue($educationHead->hasPermissionTo('education.manage'));

        // Finance head should see finance-related dashboard widgets
        $this->assertTrue($financeHead->hasPermissionTo('contributions.manage'));
    }

    /**
     * Test cross-department access is restricted.
     */
    public function test_cross_department_access_is_restricted(): void
    {
        $educationUser = $this->createEducationHeadUser();
        $financeUser = $this->createFinanceHeadUser();

        // Education user should not have finance permissions
        $this->assertFalse($educationUser->hasPermissionTo('contributions.manage'));

        // Finance user should not have education permissions
        $this->assertFalse($financeUser->hasPermissionTo('education.manage'));
    }

    /**
     * Test department secretary cannot delete.
     */
    public function test_department_secretary_cannot_delete(): void
    {
        $secretary = $this->createDepartmentSecretaryUser();

        // Should not have any delete permissions
        $this->assertFalse($secretary->hasPermissionTo('members.delete'));
        $this->assertFalse($secretary->hasPermissionTo('groups.manage'));
        $this->assertFalse($secretary->hasPermissionTo('documents.delete'));
    }

    /**
     * Test worship and mezmur roles access.
     */
    public function test_worship_mezmur_roles_access(): void
    {
        $worshipMonitor = $this->createWorshipMonitorUser();
        $mezmurHead = $this->createMezmurHeadUser();

        // Worship monitor should have worship permissions
        $this->assertTrue($worshipMonitor->hasPermissionTo('worship.songs.manage'));
        $this->assertTrue($worshipMonitor->hasPermissionTo('worship.rehearsals.manage'));

        // Mezmur head should have all worship permissions
        $this->assertTrue($mezmurHead->hasPermissionTo('worship.manage'));
        $this->assertTrue($mezmurHead->hasPermissionTo('worship.songs.manage'));
    }

    /**
     * Test internal relations head has combined permissions.
     */
    public function test_internal_relations_head_has_combined_permissions(): void
    {
        $irHead = $this->createInternalRelationsHeadUser();

        // Should have HR permissions
        $this->assertTrue($irHead->hasPermissionTo('members.create'));
        $this->assertTrue($irHead->hasPermissionTo('groups.manage'));

        // Should have AV permissions
        $this->assertTrue($irHead->hasPermissionTo('media.manage'));
        $this->assertTrue($irHead->hasPermissionTo('blog.manage'));

        // Should not have finance or education permissions
        $this->assertFalse($irHead->hasPermissionTo('contributions.manage'));
        $this->assertFalse($irHead->hasPermissionTo('education.manage'));
    }
}
