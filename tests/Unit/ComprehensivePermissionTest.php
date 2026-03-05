<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ComprehensivePermissionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Set up the test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAllPermissions();
    }

    /**
     * Seed all roles and permissions.
     */
    protected function seedAllPermissions(): void
    {
        // Create all roles
        $roles = [
            'superadmin', 'admin', 'hr_head', 'finance_head', 'nibret_hisab_head',
            'inventory_staff', 'education_head', 'education_monitor', 'worship_monitor',
            'mezmur_head', 'av_head', 'charity_head', 'tour_head',
            'internal_relations_head', 'department_secretary', 'staff',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        // Create all permissions from userstory.md
        $allPermissions = $this->getAllUserstoryPermissions();

        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Assign permissions to roles
        $this->assignAllPermissionsToRoles();
    }

    /**
     * Get all permissions from userstory.md.
     */
    protected function getAllUserstoryPermissions(): array
    {
        return [
            // === SECURITY & GOVERNANCE ===
            'users.manage', 'users.assign_roles', 'users.lock', 'users.unlock',
            'users.reset_password', 'users.activate', 'users.deactivate',
            'system.oversight', 'system.health', 'system.errors.view',
            'audit.view', 'audit.export',
            'backup.manage', 'backup.export', 'backup.restore',

            // === MEMBERSHIP, PARENTS & GROUPS ===
            'members.create', 'members.update', 'members.delete',
            'members.change_status', 'members.view', 'members.export',
            'members.timeline.view',
            'groups.create', 'groups.update', 'groups.delete',
            'groups.assign', 'groups.bulk_assign', 'groups.remove',
            'parents.create', 'parents.update', 'parents.view',
            'members.search',

            // === EDUCATION & SUNDAY SCHOOL ===
            'education.manage',
            'academic_year.create', 'academic_year.activate', 'academic_year.deactivate', 'academic_year.reactivate',
            'class.create', 'class.update', 'class.delete',
            'subject.create', 'subject.update', 'subject.delete',
            'enrollment.create', 'enrollment.remove', 'enrollment.promote', 'enrollment.bulk_promote',
            'attendance.session.create', 'attendance.session.lock', 'attendance.session.unlock',
            'attendance.student.record', 'attendance.teacher.record',
            'attendance.sync_conflicts.view',
            'teachers.create', 'teachers.update', 'teachers.delete',
            'teachers.assign', 'teachers.unassign',
            'teachers.substitute.assign',
            'teachers.attendance.view', 'teachers.assignments.view',

            // === CONTRIBUTIONS, DONATIONS & REPORTS ===
            'contributions.define_amount',
            'contributions.record', 'contributions.view',
            'contributions.view_reports', 'contributions.outstanding.view',
            'donations.record', 'donations.view', 'donations.view_reports',
            'financial.reports.view', 'financial.statements.generate', 'financial.reports.export',
            'financial.audit.view',

            // === TOURS ===
            'tours.create', 'tours.update', 'tours.delete',
            'tours.registrations.view', 'tours.passengers.register_internal',
            'tours.registrations.confirm', 'tours.attendance.session.create',
            'tours.attendance.record', 'tours.call_button.use',
            'tours.reports.view',

            // === WORSHIP, REHEARSAL & MEDIA ===
            'songs.upload', 'songs.update', 'songs.delete',
            'songs.categories.manage',
            'rehearsals.create', 'rehearsals.update', 'rehearsals.delete',
            'rehearsals.attendance.record',
            'media.upload', 'media.update', 'media.delete',
            'media.categories.manage',
            'media.visibility.change',
            'blog.create', 'blog.update', 'blog.delete', 'blog.publish',
            'announcements.create', 'announcements.update', 'announcements.delete',
            'announcements.schedule', 'announcements.urgent.mark',
            'faq.manage',

            // === INVENTORY & ASSETS ===
            'inventory.create', 'inventory.update', 'inventory.dispose',
            'inventory.movements.record',
            'inventory.analytics.view',

            // === ARCHIVES & DOCUMENTS ===
            'documents.upload', 'documents.update', 'documents.delete',
            'documents.visibility.change', 'documents.search',
            'library.upload', 'library.categories.manage',
            'contact_messages.view',

            // === EVENTS & FUNDRAISING ===
            'events.create', 'events.update', 'events.delete',
            'fundraising.create', 'fundraising.update', 'fundraising.delete',
            'fundraising.progress.update',

            // === TEACHER MANAGEMENT ===
            'teachers.profile.create', 'teachers.profile.update',
            'teachers.assignments.manage', 'teachers.history.view',
            'teachers.performance.view',

            // === CHARITY & BENEFICIARIES ===
            'beneficiaries.create', 'beneficiaries.update', 'beneficiaries.delete',
            'aid.distribute', 'aid.reports.view',

            // === DEPARTMENT MANAGEMENT ===
            'departments.manage', 'departments.roles.assign',

            // === CONTENT WORKFLOW ===
            'content.schedule', 'content.publish',

            // === HELP & SUPPORT ===
            'help.view',

            // === GENERAL ===
            'reports.view', 'reports.export',
            'notifications.view', 'notifications.receive',
        ];
    }

    /**
     * Assign all permissions to roles based on userstory.md.
     */
    protected function assignAllPermissionsToRoles(): void
    {
        $superadmin = Role::findByName('superadmin');
        $superadmin->givePermissionTo(Permission::all());

        // Admin - all except system/backup
        $admin = Role::findByName('admin');
        $admin->givePermissionTo([
            // Users
            'users.manage', 'users.assign_roles', 'users.lock', 'users.unlock',
            'users.reset_password', 'users.activate', 'users.deactivate',
            'audit.view',
            // Members
            'members.create', 'members.update', 'members.view', 'members.export',
            'members.change_status', 'members.timeline.view', 'members.search',
            'groups.create', 'groups.update', 'groups.assign', 'groups.bulk_assign',
            'parents.create', 'parents.update', 'parents.view',
            // Education
            'education.manage', 'academic_year.create', 'academic_year.activate',
            'academic_year.deactivate', 'academic_year.reactivate',
            'class.create', 'class.update', 'class.delete',
            'subject.create', 'subject.update', 'subject.delete',
            'enrollment.create', 'enrollment.remove', 'enrollment.promote', 'enrollment.bulk_promote',
            'attendance.session.create', 'attendance.session.lock', 'attendance.session.unlock',
            'attendance.student.record', 'attendance.teacher.record',
            'attendance.sync_conflicts.view',
            'teachers.create', 'teachers.update', 'teachers.assign', 'teachers.unassign',
            'teachers.substitute.assign', 'teachers.attendance.view', 'teachers.assignments.view',
            // Contributions
            'contributions.define_amount', 'contributions.record', 'contributions.view',
            'contributions.view_reports', 'contributions.outstanding.view',
            'donations.record', 'donations.view', 'donations.view_reports',
            'financial.reports.view', 'financial.statements.generate', 'financial.audit.view',
            // Tours
            'tours.create', 'tours.update', 'tours.delete', 'tours.registrations.view',
            'tours.passengers.register_internal', 'tours.registrations.confirm',
            'tours.attendance.session.create', 'tours.attendance.record',
            'tours.call_button.use', 'tours.reports.view',
            // Charity
            'beneficiaries.create', 'beneficiaries.update', 'beneficiaries.delete',
            'aid.distribute', 'aid.reports.view',
            // Inventory
            'inventory.create', 'inventory.update', 'inventory.dispose',
            'inventory.movements.record', 'inventory.analytics.view',
            // Worship
            'songs.upload', 'songs.update', 'songs.delete', 'songs.categories.manage',
            'rehearsals.create', 'rehearsals.update', 'rehearsals.delete',
            'rehearsals.attendance.record',
            'media.upload', 'media.update', 'media.delete',
            'media.categories.manage', 'media.visibility.change',
            'blog.create', 'blog.update', 'blog.delete', 'blog.publish',
            'announcements.create', 'announcements.update', 'announcements.delete',
            'announcements.schedule', 'announcements.urgent.mark',
            'faq.manage',
            // Documents
            'documents.upload', 'documents.update', 'documents.delete',
            'documents.visibility.change', 'documents.search',
            'library.upload', 'library.categories.manage',
            'contact_messages.view',
            // Events
            'events.create', 'events.update', 'events.delete',
            'fundraising.create', 'fundraising.update', 'fundraising.delete',
            'fundraising.progress.update',
            // Reports
            'reports.view', 'reports.export',
        ]);

        // HR Head - Internal Relations department
        $hrHead = Role::findByName('hr_head');
        $hrHead->givePermissionTo([
            'members.create', 'members.update', 'members.view', 'members.export',
            'members.change_status', 'members.timeline.view', 'members.search',
            'groups.create', 'groups.update', 'groups.assign', 'groups.bulk_assign',
            'parents.create', 'parents.update', 'parents.view',
            'reports.view', 'reports.export',
        ]);

        // Finance Head - Nibret ena Hisab
        $financeHead = Role::findByName('finance_head');
        $financeHead->givePermissionTo([
            'contributions.define_amount', 'contributions.record', 'contributions.view',
            'contributions.view_reports', 'contributions.outstanding.view',
            'donations.record', 'donations.view', 'donations.view_reports',
            'financial.reports.view', 'financial.statements.generate',
            'financial.reports.export', 'financial.audit.view',
            'reports.view', 'reports.export',
        ]);

        // Nibret Hisab Head - Finance and Inventory
        $nibretHisabHead = Role::findByName('nibret_hisab_head');
        $nibretHisabHead->givePermissionTo([
            'contributions.view', 'contributions.view_reports',
            'donations.view', 'donations.view_reports',
            'financial.reports.view', 'financial.statements.generate',
            'financial.reports.export', 'financial.audit.view',
            'inventory.create', 'inventory.update', 'inventory.dispose',
            'inventory.movements.record', 'inventory.analytics.view',
            'reports.view', 'reports.export',
        ]);

        // Inventory Staff
        $inventoryStaff = Role::findByName('inventory_staff');
        $inventoryStaff->givePermissionTo([
            'inventory.create', 'inventory.update', 'inventory.dispose',
            'inventory.movements.record', 'inventory.analytics.view',
            'reports.view',
        ]);

        // Education Head
        $educationHead = Role::findByName('education_head');
        $educationHead->givePermissionTo([
            'education.manage',
            'academic_year.create', 'academic_year.activate', 'academic_year.deactivate',
            'class.create', 'class.update', 'class.delete',
            'subject.create', 'subject.update', 'subject.delete',
            'enrollment.create', 'enrollment.remove', 'enrollment.promote', 'enrollment.bulk_promote',
            'attendance.session.create', 'attendance.session.lock', 'attendance.session.unlock',
            'attendance.student.record', 'attendance.teacher.record',
            'teachers.create', 'teachers.update', 'teachers.assign', 'teachers.unassign',
            'teachers.attendance.view', 'teachers.assignments.view', 'teachers.history.view',
            'teachers.performance.view',
            'library.upload', 'library.categories.manage',
            'reports.view', 'reports.export',
        ]);

        // Education Monitor
        $educationMonitor = Role::findByName('education_monitor');
        $educationMonitor->givePermissionTo([
            'attendance.session.create', 'attendance.session.lock',
            'attendance.student.record', 'attendance.teacher.record',
            'attendance.sync_conflicts.view',
            'teachers.substitute.assign',
            'reports.view',
        ]);

        // Worship Monitor - Mezmur department
        $worshipMonitor = Role::findByName('worship_monitor');
        $worshipMonitor->givePermissionTo([
            'songs.upload', 'songs.update', 'songs.delete',
            'rehearsals.create', 'rehearsals.update', 'rehearsals.delete',
            'rehearsals.attendance.record',
            'reports.view',
        ]);

        // Mezmur Head
        $mezmurHead = Role::findByName('mezmur_head');
        $mezmurHead->givePermissionTo([
            'songs.upload', 'songs.update', 'songs.delete', 'songs.categories.manage',
            'rehearsals.create', 'rehearsals.update', 'rehearsals.delete',
            'rehearsals.attendance.record',
            'reports.view',
        ]);

        // AV Head - Internal Relations
        $avHead = Role::findByName('av_head');
        $avHead->givePermissionTo([
            'media.upload', 'media.update', 'media.delete',
            'media.categories.manage', 'media.visibility.change',
            'blog.create', 'blog.update', 'blog.delete', 'blog.publish',
            'announcements.create', 'announcements.update', 'announcements.delete',
            'announcements.schedule', 'announcements.urgent.mark',
            'faq.manage',
            'reports.view',
        ]);

        // Charity Head - Revenue & Charity
        $charityHead = Role::findByName('charity_head');
        $charityHead->givePermissionTo([
            'beneficiaries.create', 'beneficiaries.update', 'beneficiaries.delete',
            'aid.distribute', 'aid.reports.view',
            'contributions.view', 'contributions.view_reports',
            'contributions.outstanding.view',
            'reports.view',
        ]);

        // Tour Head - Revenue & Charity
        $tourHead = Role::findByName('tour_head');
        $tourHead->givePermissionTo([
            'tours.create', 'tours.update', 'tours.delete',
            'tours.registrations.view', 'tours.passengers.register_internal',
            'tours.registrations.confirm',
            'tours.attendance.session.create', 'tours.attendance.record',
            'tours.call_button.use', 'tours.reports.view',
            'attendance.session.create', 'attendance.student.record',
            'reports.view',
        ]);

        // Internal Relations Head
        $internalRelationsHead = Role::findByName('internal_relations_head');
        $internalRelationsHead->givePermissionTo([
            // HR
            'members.create', 'members.update', 'members.view', 'members.export',
            'members.change_status', 'members.timeline.view', 'members.search',
            'groups.create', 'groups.update', 'groups.assign', 'groups.bulk_assign',
            // AV
            'media.upload', 'media.update', 'media.delete',
            'media.categories.manage', 'media.visibility.change',
            'blog.create', 'blog.update', 'blog.delete', 'blog.publish',
            'announcements.create', 'announcements.update', 'announcements.delete',
            'announcements.schedule', 'announcements.urgent.mark',
            'faq.manage',
            'contact_messages.view',
            'reports.view',
        ]);

        // Department Secretary - limited to create/update only
        $departmentSecretary = Role::findByName('department_secretary');
        $departmentSecretary->givePermissionTo([
            'members.create', 'members.update', 'members.view',
            'groups.assign',
            'documents.upload', 'documents.update', 'documents.view', 'documents.search',
            'reports.view',
        ]);

        // General Staff
        $staff = Role::findByName('staff');
        $staff->givePermissionTo([
            'members.view',
            'reports.view',
        ]);
    }

    // =========================================================================
    // COMPREHENSIVE PERMISSION TESTS FOR EACH ROLE
    // =========================================================================

    /**
     * Test superadmin has all permissions.
     */
    public function test_superadmin_has_all_permissions(): void
    {
        $user = $this->createSuperadminUser();

        // Should have ALL permissions
        $allPermissions = Permission::all();

        foreach ($allPermissions as $permission) {
            $this->assertTrue(
                $user->hasPermissionTo($permission),
                "Superadmin should have permission: {$permission->name}"
            );
        }
    }

    /**
     * Test admin has correct permissions (excludes system/backup).
     */
    public function test_admin_has_all_operational_permissions(): void
    {
        $user = $this->createAdminUser();

        // Should have user management
        $this->assertTrue($user->hasPermissionTo('users.manage'));
        $this->assertTrue($user->hasPermissionTo('users.assign_roles'));

        // Should have audit view
        $this->assertTrue($user->hasPermissionTo('audit.view'));

        // Should NOT have system/backup
        $this->assertFalse($user->hasPermissionTo('system.oversight'));
        $this->assertFalse($user->hasPermissionTo('backup.manage'));
        $this->assertFalse($user->hasPermissionTo('audit.export'));
    }

    /**
     * Test HR Head permissions.
     */
    public function test_hr_head_has_all_member_permissions(): void
    {
        $user = $this->createHrHeadUser();

        // Member management
        $this->assertTrue($user->hasPermissionTo('members.create'));
        $this->assertTrue($user->hasPermissionTo('members.update'));
        $this->assertTrue($user->hasPermissionTo('members.view'));
        $this->assertTrue($user->hasPermissionTo('members.export'));
        $this->assertTrue($user->hasPermissionTo('members.change_status'));
        $this->assertTrue($user->hasPermissionTo('members.timeline.view'));
        $this->assertTrue($user->hasPermissionTo('members.search'));

        // Groups
        $this->assertTrue($user->hasPermissionTo('groups.create'));
        $this->assertTrue($user->hasPermissionTo('groups.update'));
        $this->assertTrue($user->hasPermissionTo('groups.assign'));
        $this->assertTrue($user->hasPermissionTo('groups.bulk_assign'));

        // Reports
        $this->assertTrue($user->hasPermissionTo('reports.view'));

        // Should NOT have other dept permissions
        $this->assertFalse($user->hasPermissionTo('education.manage'));
        $this->assertFalse($user->hasPermissionTo('tours.create'));
        $this->assertFalse($user->hasPermissionTo('inventory.create'));
    }

    /**
     * Test Finance Head permissions.
     */
    public function test_finance_head_has_all_financial_permissions(): void
    {
        $user = $this->createFinanceHeadUser();

        // Contributions
        $this->assertTrue($user->hasPermissionTo('contributions.define_amount'));
        $this->assertTrue($user->hasPermissionTo('contributions.record'));
        $this->assertTrue($user->hasPermissionTo('contributions.view'));
        $this->assertTrue($user->hasPermissionTo('contributions.view_reports'));
        $this->assertTrue($user->hasPermissionTo('contributions.outstanding.view'));

        // Donations
        $this->assertTrue($user->hasPermissionTo('donations.record'));
        $this->assertTrue($user->hasPermissionTo('donations.view'));
        $this->assertTrue($user->hasPermissionTo('donations.view_reports'));

        // Financial reports
        $this->assertTrue($user->hasPermissionTo('financial.reports.view'));
        $this->assertTrue($user->hasPermissionTo('financial.statements.generate'));
        $this->assertTrue($user->hasPermissionTo('financial.reports.export'));

        // Should NOT have other dept permissions
        $this->assertFalse($user->hasPermissionTo('education.manage'));
        $this->assertFalse($user->hasPermissionTo('tours.create'));
    }

    /**
     * Test Nibret Hisab Head permissions.
     */
    public function test_nibret_hisab_head_has_oversight_permissions(): void
    {
        $user = $this->createNibretHisabHeadUser();

        // Financial view
        $this->assertTrue($user->hasPermissionTo('contributions.view'));
        $this->assertTrue($user->hasPermissionTo('contributions.view_reports'));
        $this->assertTrue($user->hasPermissionTo('donations.view'));
        $this->assertTrue($user->hasPermissionTo('financial.reports.view'));
        $this->assertTrue($user->hasPermissionTo('financial.statements.generate'));

        // Inventory
        $this->assertTrue($user->hasPermissionTo('inventory.create'));
        $this->assertTrue($user->hasPermissionTo('inventory.update'));
        $this->assertTrue($user->hasPermissionTo('inventory.dispose'));
        $this->assertTrue($user->hasPermissionTo('inventory.movements.record'));
        $this->assertTrue($user->hasPermissionTo('inventory.analytics.view'));

        // Reports
        $this->assertTrue($user->hasPermissionTo('reports.view'));
        $this->assertTrue($user->hasPermissionTo('reports.export'));
    }

    /**
     * Test Inventory Staff permissions.
     */
    public function test_inventory_staff_has_inventory_permissions(): void
    {
        $user = $this->createInventoryStaffUser();

        $this->assertTrue($user->hasPermissionTo('inventory.create'));
        $this->assertTrue($user->hasPermissionTo('inventory.update'));
        $this->assertTrue($user->hasPermissionTo('inventory.dispose'));
        $this->assertTrue($user->hasPermissionTo('inventory.movements.record'));
        $this->assertTrue($user->hasPermissionTo('inventory.analytics.view'));
        $this->assertTrue($user->hasPermissionTo('reports.view'));

        // Should NOT have other permissions
        $this->assertFalse($user->hasPermissionTo('education.manage'));
        $this->assertFalse($user->hasPermissionTo('tours.create'));
    }

    /**
     * Test Education Head permissions.
     */
    public function test_education_head_has_all_education_permissions(): void
    {
        $user = $this->createEducationHeadUser();

        // Education manage
        $this->assertTrue($user->hasPermissionTo('education.manage'));

        // Academic year
        $this->assertTrue($user->hasPermissionTo('academic_year.create'));
        $this->assertTrue($user->hasPermissionTo('academic_year.activate'));
        $this->assertTrue($user->hasPermissionTo('academic_year.deactivate'));

        // Class
        $this->assertTrue($user->hasPermissionTo('class.create'));
        $this->assertTrue($user->hasPermissionTo('class.update'));
        $this->assertTrue($user->hasPermissionTo('class.delete'));

        // Subject
        $this->assertTrue($user->hasPermissionTo('subject.create'));
        $this->assertTrue($user->hasPermissionTo('subject.update'));
        $this->assertTrue($user->hasPermissionTo('subject.delete'));

        // Enrollment
        $this->assertTrue($user->hasPermissionTo('enrollment.create'));
        $this->assertTrue($user->hasPermissionTo('enrollment.remove'));
        $this->assertTrue($user->hasPermissionTo('enrollment.promote'));
        $this->assertTrue($user->hasPermissionTo('enrollment.bulk_promote'));

        // Attendance
        $this->assertTrue($user->hasPermissionTo('attendance.session.create'));
        $this->assertTrue($user->hasPermissionTo('attendance.session.lock'));
        $this->assertTrue($user->hasPermissionTo('attendance.session.unlock'));

        // Teachers
        $this->assertTrue($user->hasPermissionTo('teachers.create'));
        $this->assertTrue($user->hasPermissionTo('teachers.update'));
        $this->assertTrue($user->hasPermissionTo('teachers.assign'));
        $this->assertTrue($user->hasPermissionTo('teachers.attendance.view'));

        // Library
        $this->assertTrue($user->hasPermissionTo('library.upload'));

        // Reports
        $this->assertTrue($user->hasPermissionTo('reports.view'));
    }

    /**
     * Test Education Monitor permissions.
     */
    public function test_education_monitor_has_attendance_permissions(): void
    {
        $user = $this->createEducationMonitorUser();

        $this->assertTrue($user->hasPermissionTo('attendance.session.create'));
        $this->assertTrue($user->hasPermissionTo('attendance.session.lock'));
        $this->assertTrue($user->hasPermissionTo('attendance.student.record'));
        $this->assertTrue($user->hasPermissionTo('attendance.teacher.record'));
        $this->assertTrue($user->hasPermissionTo('attendance.sync_conflicts.view'));
        $this->assertTrue($user->hasPermissionTo('teachers.substitute.assign'));

        // Should NOT have education management
        $this->assertFalse($user->hasPermissionTo('education.manage'));
        $this->assertFalse($user->hasPermissionTo('class.create'));
    }

    /**
     * Test Tour Head permissions.
     */
    public function test_tour_head_has_all_tour_permissions(): void
    {
        $user = $this->createTourHeadUser();

        $this->assertTrue($user->hasPermissionTo('tours.create'));
        $this->assertTrue($user->hasPermissionTo('tours.update'));
        $this->assertTrue($user->hasPermissionTo('tours.delete'));
        $this->assertTrue($user->hasPermissionTo('tours.registrations.view'));
        $this->assertTrue($user->hasPermissionTo('tours.passengers.register_internal'));
        $this->assertTrue($user->hasPermissionTo('tours.registrations.confirm'));
        $this->assertTrue($user->hasPermissionTo('tours.attendance.session.create'));
        $this->assertTrue($user->hasPermissionTo('tours.attendance.record'));
        $this->assertTrue($user->hasPermissionTo('tours.call_button.use'));
        $this->assertTrue($user->hasPermissionTo('tours.reports.view'));
        $this->assertTrue($user->hasPermissionTo('reports.view'));
    }

    /**
     * Test Charity Head permissions.
     */
    public function test_charity_head_has_all_charity_permissions(): void
    {
        $user = $this->createCharityHeadUser();

        $this->assertTrue($user->hasPermissionTo('beneficiaries.create'));
        $this->assertTrue($user->hasPermissionTo('beneficiaries.update'));
        $this->assertTrue($user->hasPermissionTo('beneficiaries.delete'));
        $this->assertTrue($user->hasPermissionTo('aid.distribute'));
        $this->assertTrue($user->hasPermissionTo('aid.reports.view'));
        $this->assertTrue($user->hasPermissionTo('contributions.view'));
        $this->assertTrue($user->hasPermissionTo('contributions.outstanding.view'));
    }

    /**
     * Test AV Head permissions.
     */
    public function test_av_head_has_all_media_permissions(): void
    {
        $user = $this->createAvHeadUser();

        $this->assertTrue($user->hasPermissionTo('media.upload'));
        $this->assertTrue($user->hasPermissionTo('media.update'));
        $this->assertTrue($user->hasPermissionTo('media.delete'));
        $this->assertTrue($user->hasPermissionTo('media.categories.manage'));
        $this->assertTrue($user->hasPermissionTo('media.visibility.change'));

        $this->assertTrue($user->hasPermissionTo('blog.create'));
        $this->assertTrue($user->hasPermissionTo('blog.update'));
        $this->assertTrue($user->hasPermissionTo('blog.delete'));
        $this->assertTrue($user->hasPermissionTo('blog.publish'));

        $this->assertTrue($user->hasPermissionTo('announcements.create'));
        $this->assertTrue($user->hasPermissionTo('announcements.update'));
        $this->assertTrue($user->hasPermissionTo('announcements.delete'));
        $this->assertTrue($user->hasPermissionTo('announcements.schedule'));
        $this->assertTrue($user->hasPermissionTo('announcements.urgent.mark'));

        $this->assertTrue($user->hasPermissionTo('faq.manage'));
    }

    /**
     * Test Mezmur Head permissions.
     */
    public function test_mezmur_head_has_worship_permissions(): void
    {
        $user = $this->createMezmurHeadUser();

        $this->assertTrue($user->hasPermissionTo('songs.upload'));
        $this->assertTrue($user->hasPermissionTo('songs.update'));
        $this->assertTrue($user->hasPermissionTo('songs.delete'));
        $this->assertTrue($user->hasPermissionTo('songs.categories.manage'));

        $this->assertTrue($user->hasPermissionTo('rehearsals.create'));
        $this->assertTrue($user->hasPermissionTo('rehearsals.update'));
        $this->assertTrue($user->hasPermissionTo('rehearsals.delete'));
        $this->assertTrue($user->hasPermissionTo('rehearsals.attendance.record'));
    }

    /**
     * Test Worship Monitor permissions.
     */
    public function test_worship_monitor_has_songs_rehearsal_permissions(): void
    {
        $user = $this->createWorshipMonitorUser();

        $this->assertTrue($user->hasPermissionTo('songs.upload'));
        $this->assertTrue($user->hasPermissionTo('songs.update'));

        $this->assertTrue($user->hasPermissionTo('rehearsals.create'));
        $this->assertTrue($user->hasPermissionTo('rehearsals.attendance.record'));
    }

    /**
     * Test Internal Relations Head permissions.
     */
    public function test_internal_relations_head_has_combined_permissions(): void
    {
        $user = $this->createInternalRelationsHeadUser();

        // HR permissions
        $this->assertTrue($user->hasPermissionTo('members.create'));
        $this->assertTrue($user->hasPermissionTo('groups.create'));

        // AV permissions
        $this->assertTrue($user->hasPermissionTo('media.upload'));
        $this->assertTrue($user->hasPermissionTo('blog.create'));
        $this->assertTrue($user->hasPermissionTo('announcements.create'));

        // Contact messages
        $this->assertTrue($user->hasPermissionTo('contact_messages.view'));
    }

    /**
     * Test Department Secretary permissions (no delete).
     */
    public function test_department_secretary_has_no_delete_permissions(): void
    {
        $user = $this->createDepartmentSecretaryUser();

        // Create/Update/View
        $this->assertTrue($user->hasPermissionTo('members.create'));
        $this->assertTrue($user->hasPermissionTo('members.update'));
        $this->assertTrue($user->hasPermissionTo('members.view'));
        $this->assertTrue($user->hasPermissionTo('groups.assign'));

        // Documents
        $this->assertTrue($user->hasPermissionTo('documents.upload'));
        $this->assertTrue($user->hasPermissionTo('documents.update'));
        $this->assertTrue($user->hasPermissionTo('documents.view'));

        // Should NOT have delete
        $this->assertFalse($user->hasPermissionTo('members.delete'));
        $this->assertFalse($user->hasPermissionTo('groups.delete'));
        $this->assertFalse($user->hasPermissionTo('documents.delete'));
        $this->assertFalse($user->hasPermissionTo('users.manage'));
    }

    /**
     * Test Staff permissions (minimal).
     */
    public function test_staff_has_minimal_permissions(): void
    {
        $user = $this->createStaffUser();

        $this->assertTrue($user->hasPermissionTo('members.view'));
        $this->assertTrue($user->hasPermissionTo('reports.view'));

        // Should NOT have any management permissions
        $this->assertFalse($user->hasPermissionTo('members.create'));
        $this->assertFalse($user->hasPermissionTo('members.update'));
        $this->assertFalse($user->hasPermissionTo('groups.assign'));
        $this->assertFalse($user->hasPermissionTo('users.manage'));
    }

    /**
     * Test that all roles have at least basic access.
     */
    public function test_all_roles_have_basic_access(): void
    {
        $roles = [
            'superadmin', 'admin', 'hr_head', 'finance_head', 'nibret_hisab_head',
            'inventory_staff', 'education_head', 'education_monitor', 'worship_monitor',
            'mezmur_head', 'av_head', 'charity_head', 'tour_head',
            'internal_relations_head', 'department_secretary', 'staff',
        ];

        foreach ($roles as $role) {
            $user = $this->createUserWithRole($role, 'Internal Relations');

            // Every role should have reports.view
            $this->assertTrue(
                $user->hasPermissionTo('reports.view'),
                "Role {$role} should have reports.view permission"
            );
        }
    }

    /**
     * Test permission count for each role.
     */
    public function test_role_permission_counts(): void
    {
        $admin = $this->createAdminUser();
        $hrHead = $this->createHrHeadUser();
        $staff = $this->createStaffUser();

        // Admin should have many permissions
        $adminCount = $admin->getPermissionNames()->count();
        $this->assertGreaterThan(50, $adminCount, 'Admin should have more than 50 permissions');

        // HR Head should have moderate permissions
        $hrCount = $hrHead->getPermissionNames()->count();
        $this->assertGreaterThan(5, $hrCount, 'HR Head should have more than 5 permissions');
        $this->assertLessThan($adminCount, $hrCount, 'HR Head should have fewer permissions than Admin');

        // Staff should have minimal permissions
        $staffCount = $staff->getPermissionNames()->count();
        $this->assertLessThan(10, $staffCount, 'Staff should have less than 10 permissions');
    }
}
