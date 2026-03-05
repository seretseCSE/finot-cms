<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Set up the test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
    }

    /**
     * Seed roles and permissions for testing.
     */
    protected function seedRolesAndPermissions(): void
    {
        // Create all roles
        $roles = [
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

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        // Create permissions based on userstory.md
        $permissions = [
            // User Management
            'users.manage',
            'users.assign_roles',
            'users.lock',
            'users.unlock',
            'users.reset_password',
            'users.activate',
            'users.deactivate',

            // Member Management
            'members.create',
            'members.update',
            'members.delete',
            'members.view',
            'members.export',
            'groups.manage',
            'groups.assign',
            'groups.bulk_assign',

            // Education Management
            'education.manage',
            'education.academic_year.create',
            'education.academic_year.activate',
            'education.academic_year.deactivate',
            'education.class.manage',
            'education.subject.manage',
            'education.enrollment.manage',
            'education.promote',
            'education.bulk_promote',
            'education.attendance.create',
            'education.attendance.record',
            'education.attendance.lock',
            'education.attendance.unlock',

            // Financial Management
            'contributions.manage',
            'contributions.define_amount',
            'contributions.record',
            'contributions.view_reports',
            'donations.manage',
            'donations.record',
            'donations.view_reports',
            'financial.reports.view',
            'financial.reports.export',
            'financial.statements.generate',

            // Tour Management
            'tours.manage',
            'tours.create',
            'tours.update',
            'tours.delete',
            'tours.register_passengers',
            'tours.confirm_registration',
            'tours.attendance.manage',
            'tours.reports.view',

            // Charity Management
            'charity.manage',
            'charity.beneficiaries.manage',
            'charity.aid.distribute',
            'charity.reports.view',

            // Inventory Management
            'inventory.manage',
            'inventory.items.manage',
            'inventory.movements.manage',
            'inventory.reports.view',

            // Worship & Media
            'worship.manage',
            'worship.songs.manage',
            'worship.rehearsals.manage',
            'media.manage',
            'media.upload',
            'media.delete',
            'blog.manage',
            'announcements.manage',
            'faq.manage',

            // Documents & Archives
            'documents.upload',
            'documents.manage',
            'documents.delete',
            'documents.view',
            'library.manage',

            // Events & Fundraising
            'events.manage',
            'fundraising.manage',

            // System & Security
            'system.oversight',
            'system.health',
            'system.errors.view',
            'audit.view',
            'audit.export',
            'backup.manage',
            'backup.export',
            'backup.restore',

            // Reports
            'reports.view',
            'reports.export',

            // Teachers
            'teachers.manage',
            'teachers.assign',
            'teachers.attendance.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Assign permissions to roles based on userstory.md
        $this->assignPermissionsToRoles();
    }

    /**
     * Assign permissions to roles based on userstory.md.
     */
    protected function assignPermissionsToRoles(): void
    {
        // Superadmin - Full system access
        $superadmin = Role::findByName('superadmin');
        $superadmin->givePermissionTo(Permission::all());

        // Admin - All operational access except system settings/backups
        $admin = Role::findByName('admin');
        $admin->givePermissionTo([
            'users.manage', 'users.assign_roles', 'users.lock', 'users.unlock',
            'users.reset_password', 'users.activate', 'users.deactivate',
            'members.create', 'members.update', 'members.view', 'members.export',
            'groups.manage', 'groups.assign', 'groups.bulk_assign',
            'education.manage', 'education.class.manage', 'education.subject.manage',
            'education.enrollment.manage', 'education.promote', 'education.bulk_promote',
            'education.attendance.unlock',
            'contributions.manage', 'contributions.define_amount', 'contributions.record',
            'contributions.view_reports', 'donations.manage', 'donations.record',
            'donations.view_reports', 'financial.reports.view', 'financial.statements.generate',
            'tours.manage', 'tours.create', 'tours.update', 'tours.delete',
            'tours.register_passengers', 'tours.confirm_registration',
            'tours.attendance.manage', 'tours.reports.view',
            'charity.manage', 'charity.beneficiaries.manage', 'charity.aid.distribute',
            'charity.reports.view',
            'inventory.manage', 'inventory.items.manage', 'inventory.movements.manage',
            'inventory.reports.view',
            'worship.manage', 'worship.songs.manage', 'worship.rehearsals.manage',
            'media.manage', 'media.upload', 'media.delete',
            'blog.manage', 'announcements.manage', 'faq.manage',
            'documents.upload', 'documents.manage', 'documents.delete', 'documents.view',
            'library.manage', 'events.manage', 'fundraising.manage',
            'audit.view', 'reports.view', 'reports.export',
            'teachers.manage', 'teachers.assign', 'teachers.attendance.view',
        ]);

        // HR Head - Manages member profiles, groups
        $hrHead = Role::findByName('hr_head');
        $hrHead->givePermissionTo([
            'members.create', 'members.update', 'members.view',
            'groups.manage', 'groups.assign', 'groups.bulk_assign',
            'members.export', 'reports.view',
        ]);

        // Finance Head - Records contributions, donations, financial reports
        $financeHead = Role::findByName('finance_head');
        $financeHead->givePermissionTo([
            'contributions.manage', 'contributions.define_amount', 'contributions.record',
            'contributions.view_reports', 'donations.manage', 'donations.record',
            'donations.view_reports', 'financial.reports.view', 'financial.reports.export',
            'financial.statements.generate', 'reports.view', 'reports.export',
        ]);

        // Nibret Hisab Head - Finance and Inventory oversight
        $nibretHisabHead = Role::findByName('nibret_hisab_head');
        $nibretHisabHead->givePermissionTo([
            'contributions.view_reports', 'donations.view_reports',
            'financial.reports.view', 'financial.reports.export',
            'financial.statements.generate', 'reports.view', 'reports.export',
            'inventory.manage', 'inventory.items.manage', 'inventory.movements.manage',
            'inventory.reports.view',
        ]);

        // Inventory Staff - Manages inventory items, movements
        $inventoryStaff = Role::findByName('inventory_staff');
        $inventoryStaff->givePermissionTo([
            'inventory.manage', 'inventory.items.manage', 'inventory.movements.manage',
            'inventory.reports.view', 'reports.view',
        ]);

        // Education Head - Education management
        $educationHead = Role::findByName('education_head');
        $educationHead->givePermissionTo([
            'education.manage', 'education.academic_year.create',
            'education.academic_year.activate', 'education.academic_year.deactivate',
            'education.class.manage', 'education.subject.manage',
            'education.enrollment.manage', 'education.promote', 'education.bulk_promote',
            'education.attendance.unlock', 'teachers.manage', 'teachers.assign',
            'teachers.attendance.view', 'library.manage', 'reports.view',
        ]);

        // Education Monitor - Records class and teacher attendance
        $educationMonitor = Role::findByName('education_monitor');
        $educationMonitor->givePermissionTo([
            'education.attendance.create', 'education.attendance.record',
            'education.attendance.lock', 'reports.view',
        ]);

        // Worship Monitor - Manages songs, rehearsals, rehearsal attendance
        $worshipMonitor = Role::findByName('worship_monitor');
        $worshipMonitor->givePermissionTo([
            'worship.songs.manage', 'worship.rehearsals.manage', 'reports.view',
        ]);

        // Mezmur Head - Mezmur/Worship department head
        $mezmurHead = Role::findByName('mezmur_head');
        $mezmurHead->givePermissionTo([
            'worship.manage', 'worship.songs.manage', 'worship.rehearsals.manage',
            'reports.view',
        ]);

        // AV Head - Manages media, blog, announcements
        $avHead = Role::findByName('av_head');
        $avHead->givePermissionTo([
            'media.manage', 'media.upload', 'media.delete',
            'blog.manage', 'announcements.manage', 'faq.manage',
            'reports.view',
        ]);

        // Charity Head - Manages beneficiaries, aid distribution
        $charityHead = Role::findByName('charity_head');
        $charityHead->givePermissionTo([
            'charity.manage', 'charity.beneficiaries.manage', 'charity.aid.distribute',
            'charity.reports.view', 'contributions.view_reports', 'reports.view',
        ]);

        // Tour Head - Creates tours, manages registrations and attendance
        $tourHead = Role::findByName('tour_head');
        $tourHead->givePermissionTo([
            'tours.manage', 'tours.create', 'tours.update', 'tours.delete',
            'tours.register_passengers', 'tours.confirm_registration',
            'tours.attendance.manage', 'tours.reports.view', 'reports.view',
            'education.attendance.create', 'education.attendance.record',
        ]);

        // Internal Relations Head - Manages HR and AV
        $internalRelationsHead = Role::findByName('internal_relations_head');
        $internalRelationsHead->givePermissionTo([
            'members.create', 'members.update', 'members.view',
            'groups.manage', 'groups.assign', 'groups.bulk_assign',
            'members.export',
            'media.manage', 'media.upload', 'media.delete',
            'blog.manage', 'announcements.manage', 'faq.manage',
            'reports.view',
        ]);

        // Department Secretary - Create/Update only (NO Delete)
        $departmentSecretary = Role::findByName('department_secretary');
        $departmentSecretary->givePermissionTo([
            'members.create', 'members.update', 'members.view',
            'groups.assign',
            'documents.upload', 'documents.view',
            'reports.view',
        ]);

        // General Staff - Basic access
        $staff = Role::findByName('staff');
        $staff->givePermissionTo([
            'members.view', 'reports.view',
        ]);
    }

    /**
     * Test superadmin has all permissions.
     */
    public function test_superadmin_has_all_permissions(): void
    {
        $user = $this->createSuperadminUser();

        // Superadmin should have all permissions
        $this->assertTrue($user->hasPermissionTo('users.manage'));
        $this->assertTrue($user->hasPermissionTo('audit.export'));
        $this->assertTrue($user->hasPermissionTo('backup.restore'));
        $this->assertTrue($user->hasPermissionTo('members.create'));
        $this->assertTrue($user->hasPermissionTo('education.manage'));
    }

    /**
     * Test admin has correct permissions.
     */
    public function test_admin_has_correct_permissions(): void
    {
        $user = $this->createAdminUser();

        // Admin should have most permissions except system settings
        $this->assertTrue($user->hasPermissionTo('users.manage'));
        $this->assertTrue($user->hasPermissionTo('audit.view'));
        $this->assertFalse($user->hasPermissionTo('audit.export'));
        $this->assertFalse($user->hasPermissionTo('backup.manage'));
        $this->assertTrue($user->hasPermissionTo('members.create'));
        $this->assertTrue($user->hasPermissionTo('education.manage'));
    }

    /**
     * Test HR Head has member and group management permissions.
     */
    public function test_hr_head_has_member_permissions(): void
    {
        $user = $this->createHrHeadUser();

        $this->assertTrue($user->hasPermissionTo('members.create'));
        $this->assertTrue($user->hasPermissionTo('members.update'));
        $this->assertTrue($user->hasPermissionTo('members.view'));
        $this->assertTrue($user->hasPermissionTo('groups.manage'));
        $this->assertTrue($user->hasPermissionTo('groups.assign'));
        $this->assertTrue($user->hasPermissionTo('groups.bulk_assign'));

        // Should not have other department permissions
        $this->assertFalse($user->hasPermissionTo('education.manage'));
        $this->assertFalse($user->hasPermissionTo('tours.manage'));
        $this->assertFalse($user->hasPermissionTo('inventory.manage'));
    }

    /**
     * Test Finance Head has financial permissions.
     */
    public function test_finance_head_has_financial_permissions(): void
    {
        $user = $this->createFinanceHeadUser();

        $this->assertTrue($user->hasPermissionTo('contributions.manage'));
        $this->assertTrue($user->hasPermissionTo('contributions.define_amount'));
        $this->assertTrue($user->hasPermissionTo('contributions.record'));
        $this->assertTrue($user->hasPermissionTo('contributions.view_reports'));
        $this->assertTrue($user->hasPermissionTo('donations.manage'));
        $this->assertTrue($user->hasPermissionTo('donations.view_reports'));
        $this->assertTrue($user->hasPermissionTo('financial.reports.view'));
        $this->assertTrue($user->hasPermissionTo('financial.statements.generate'));

        // Should not have education or inventory permissions
        $this->assertFalse($user->hasPermissionTo('education.manage'));
        $this->assertFalse($user->hasPermissionTo('inventory.manage'));
    }

    /**
     * Test Education Head has education management permissions.
     */
    public function test_education_head_has_education_permissions(): void
    {
        $user = $this->createEducationHeadUser();

        $this->assertTrue($user->hasPermissionTo('education.manage'));
        $this->assertTrue($user->hasPermissionTo('education.academic_year.create'));
        $this->assertTrue($user->hasPermissionTo('education.academic_year.activate'));
        $this->assertTrue($user->hasPermissionTo('education.class.manage'));
        $this->assertTrue($user->hasPermissionTo('education.subject.manage'));
        $this->assertTrue($user->hasPermissionTo('education.enrollment.manage'));
        $this->assertTrue($user->hasPermissionTo('education.promote'));
        $this->assertTrue($user->hasPermissionTo('teachers.manage'));

        // Should not have financial permissions
        $this->assertFalse($user->hasPermissionTo('contributions.manage'));
    }

    /**
     * Test Education Monitor has attendance permissions.
     */
    public function test_education_monitor_has_attendance_permissions(): void
    {
        $user = $this->createEducationMonitorUser();

        $this->assertTrue($user->hasPermissionTo('education.attendance.create'));
        $this->assertTrue($user->hasPermissionTo('education.attendance.record'));
        $this->assertTrue($user->hasPermissionTo('education.attendance.lock'));

        // Should not have education management permissions
        $this->assertFalse($user->hasPermissionTo('education.manage'));
        $this->assertFalse($user->hasPermissionTo('education.class.manage'));
    }

    /**
     * Test Tour Head has tour management permissions.
     */
    public function test_tour_head_has_tour_permissions(): void
    {
        $user = $this->createTourHeadUser();

        $this->assertTrue($user->hasPermissionTo('tours.manage'));
        $this->assertTrue($user->hasPermissionTo('tours.create'));
        $this->assertTrue($user->hasPermissionTo('tours.update'));
        $this->assertTrue($user->hasPermissionTo('tours.delete'));
        $this->assertTrue($user->hasPermissionTo('tours.register_passengers'));
        $this->assertTrue($user->hasPermissionTo('tours.confirm_registration'));
        $this->assertTrue($user->hasPermissionTo('tours.attendance.manage'));
        $this->assertTrue($user->hasPermissionTo('tours.reports.view'));

        // Should not have education or financial permissions
        $this->assertFalse($user->hasPermissionTo('education.manage'));
        $this->assertFalse($user->hasPermissionTo('contributions.manage'));
    }

    /**
     * Test Charity Head has charity permissions.
     */
    public function test_charity_head_has_charity_permissions(): void
    {
        $user = $this->createCharityHeadUser();

        $this->assertTrue($user->hasPermissionTo('charity.manage'));
        $this->assertTrue($user->hasPermissionTo('charity.beneficiaries.manage'));
        $this->assertTrue($user->hasPermissionTo('charity.aid.distribute'));
        $this->assertTrue($user->hasPermissionTo('charity.reports.view'));
        $this->assertTrue($user->hasPermissionTo('contributions.view_reports'));

        // Should not have education or tour permissions
        $this->assertFalse($user->hasPermissionTo('education.manage'));
        $this->assertFalse($user->hasPermissionTo('tours.manage'));
    }

    /**
     * Test Inventory Staff has inventory permissions.
     */
    public function test_inventory_staff_has_inventory_permissions(): void
    {
        $user = $this->createInventoryStaffUser();

        $this->assertTrue($user->hasPermissionTo('inventory.manage'));
        $this->assertTrue($user->hasPermissionTo('inventory.items.manage'));
        $this->assertTrue($user->hasPermissionTo('inventory.movements.manage'));
        $this->assertTrue($user->hasPermissionTo('inventory.reports.view'));

        // Should not have other permissions
        $this->assertFalse($user->hasPermissionTo('education.manage'));
        $this->assertFalse($user->hasPermissionTo('tours.manage'));
    }

    /**
     * Test AV Head has media permissions.
     */
    public function test_av_head_has_media_permissions(): void
    {
        $user = $this->createAvHeadUser();

        $this->assertTrue($user->hasPermissionTo('media.manage'));
        $this->assertTrue($user->hasPermissionTo('media.upload'));
        $this->assertTrue($user->hasPermissionTo('media.delete'));
        $this->assertTrue($user->hasPermissionTo('blog.manage'));
        $this->assertTrue($user->hasPermissionTo('announcements.manage'));
        $this->assertTrue($user->hasPermissionTo('faq.manage'));

        // Should not have education or financial permissions
        $this->assertFalse($user->hasPermissionTo('education.manage'));
        $this->assertFalse($user->hasPermissionTo('contributions.manage'));
    }

    /**
     * Test Department Secretary has limited permissions (no delete).
     */
    public function test_department_secretary_has_limited_permissions(): void
    {
        $user = $this->createDepartmentSecretaryUser();

        $this->assertTrue($user->hasPermissionTo('members.create'));
        $this->assertTrue($user->hasPermissionTo('members.update'));
        $this->assertTrue($user->hasPermissionTo('members.view'));
        $this->assertTrue($user->hasPermissionTo('groups.assign'));

        // Should not have delete permissions
        $this->assertFalse($user->hasPermissionTo('members.delete'));
        $this->assertFalse($user->hasPermissionTo('groups.manage'));
        $this->assertFalse($user->hasPermissionTo('users.manage'));
    }

    /**
     * Test General Staff has minimal permissions.
     */
    public function test_staff_has_minimal_permissions(): void
    {
        $user = $this->createStaffUser();

        $this->assertTrue($user->hasPermissionTo('members.view'));
        $this->assertTrue($user->hasPermissionTo('reports.view'));

        // Should not have create, update, or management permissions
        $this->assertFalse($user->hasPermissionTo('members.create'));
        $this->assertFalse($user->hasPermissionTo('members.update'));
        $this->assertFalse($user->hasPermissionTo('groups.manage'));
        $this->assertFalse($user->hasPermissionTo('users.manage'));
    }

    /**
     * Test all roles can be created.
     */
    public function test_all_roles_can_be_created(): void
    {
        $roles = $this->getAllTestRoles();

        foreach ($roles as $role) {
            $user = $this->createUserWithRole($role, 'Internal Relations');
            $this->assertTrue($user->hasRole($role));
        }
    }

    /**
     * Test inactive user cannot access panel.
     */
    public function test_inactive_user_cannot_access_panel(): void
    {
        $user = User::factory()->create([
            'is_active' => false,
            'temp_password_changed' => true,
        ]);
        $user->assignRole('admin');

        $this->assertFalse($user->canAccessPanel(\Filament\Panel::make()));
    }

    /**
     * Test user with temp password cannot access panel.
     */
    public function test_user_needing_password_change_cannot_access_panel(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'temp_password_changed' => false,
        ]);
        $user->assignRole('admin');

        $this->assertFalse($user->canAccessPanel(\Filament\Panel::make()));
    }

    /**
     * Test active user with changed temp password can access panel.
     */
    public function test_active_user_with_changed_password_can_access_panel(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'temp_password_changed' => true,
        ]);
        $user->assignRole('admin');

        $this->assertTrue($user->canAccessPanel(\Filament\Panel::make()));
    }

    /**
     * Test user can be assigned multiple roles.
     */
    public function test_user_can_be_assigned_multiple_roles(): void
    {
        $user = User::factory()->create();

        $user->assignRole('hr_head');
        $user->assignRole('staff');

        $this->assertTrue($user->hasRole('hr_head'));
        $this->assertTrue($user->hasRole('staff'));
    }

    /**
     * Test user can be revoked a role.
     */
    public function test_user_can_be_revoked_role(): void
    {
        $user = User::factory()->create();

        $user->assignRole('admin');
        $this->assertTrue($user->hasRole('admin'));

        $user->removeRole('admin');
        $this->assertFalse($user->hasRole('admin'));
    }

    /**
     * Test user can sync roles.
     */
    public function test_user_can_sync_roles(): void
    {
        $user = User::factory()->create();

        $user->syncRoles(['admin', 'hr_head']);

        $this->assertTrue($user->hasRole('admin'));
        $this->assertTrue($user->hasRole('hr_head'));
        $this->assertFalse($user->hasRole('staff'));
    }
}
