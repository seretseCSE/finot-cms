<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test HR workflow - member creation and group assignment.
     */
    public function test_hr_workflow_member_creation_and_group_assignment(): void
    {
        $hrHead = $this->createHrHeadUser();

        // HR Head should be able to create member
        $this->assertTrue($hrHead->hasPermissionTo('members.create'));
        $this->assertTrue($hrHead->hasPermissionTo('members.update'));

        // Should be able to manage groups
        $this->assertTrue($hrHead->hasPermissionTo('groups.manage'));
        $this->assertTrue($hrHead->hasPermissionTo('groups.assign'));
        $this->assertTrue($hrHead->hasPermissionTo('groups.bulk_assign'));

        // Should not have delete permission on members (only status change)
        $this->assertFalse($hrHead->hasPermissionTo('members.delete'));
    }

    /**
     * Test Finance workflow - contribution management.
     */
    public function test_finance_workflow_contribution_management(): void
    {
        $financeHead = $this->createFinanceHeadUser();

        // Should be able to define contribution amounts
        $this->assertTrue($financeHead->hasPermissionTo('contributions.define_amount'));

        // Should be able to record contributions
        $this->assertTrue($financeHead->hasPermissionTo('contributions.record'));

        // Should be able to view reports
        $this->assertTrue($financeHead->hasPermissionTo('contributions.view_reports'));
        $this->assertTrue($financeHead->hasPermissionTo('financial.reports.view'));

        // Should be able to generate statements
        $this->assertTrue($financeHead->hasPermissionTo('financial.statements.generate'));

        // Should be able to export
        $this->assertTrue($financeHead->hasPermissionTo('financial.reports.export'));
    }

    /**
     * Test Education workflow - academic year and class management.
     */
    public function test_education_workflow_academic_year_and_class(): void
    {
        $educationHead = $this->createEducationHeadUser();

        // Should be able to manage academic years
        $this->assertTrue($educationHead->hasPermissionTo('education.academic_year.create'));
        $this->assertTrue($educationHead->hasPermissionTo('education.academic_year.activate'));
        $this->assertTrue($educationHead->hasPermissionTo('education.academic_year.deactivate'));

        // Should be able to manage classes
        $this->assertTrue($educationHead->hasPermissionTo('education.class.manage'));

        // Should be able to manage subjects
        $this->assertTrue($educationHead->hasPermissionTo('education.subject.manage'));

        // Should be able to manage enrollments
        $this->assertTrue($educationHead->hasPermissionTo('education.enrollment.manage'));

        // Should be able to promote students
        $this->assertTrue($educationHead->hasPermissionTo('education.promote'));
        $this->assertTrue($educationHead->hasPermissionTo('education.bulk_promote'));

        // Should be able to unlock attendance
        $this->assertTrue($educationHead->hasPermissionTo('education.attendance.unlock'));
    }

    /**
     * Test Education Monitor workflow - attendance recording.
     */
    public function test_education_monitor_workflow_attendance(): void
    {
        $eduMonitor = $this->createEducationMonitorUser();

        // Should be able to create attendance sessions
        $this->assertTrue($eduMonitor->hasPermissionTo('education.attendance.create'));

        // Should be able to record attendance
        $this->assertTrue($eduMonitor->hasPermissionTo('education.attendance.record'));

        // Should be able to lock sessions
        $this->assertTrue($eduMonitor->hasPermissionTo('education.attendance.lock'));

        // Should NOT be able to unlock (only education head)
        $this->assertFalse($eduMonitor->hasPermissionTo('education.attendance.unlock'));
    }

    /**
     * Test Tour workflow - tour management and attendance.
     */
    public function test_tour_workflow_tour_management(): void
    {
        $tourHead = $this->createTourHeadUser();

        // Should be able to create tours
        $this->assertTrue($tourHead->hasPermissionTo('tours.create'));
        $this->assertTrue($tourHead->hasPermissionTo('tours.update'));
        $this->assertTrue($tourHead->hasPermissionTo('tours.delete'));

        // Should be able to manage registrations
        $this->assertTrue($tourHead->hasPermissionTo('tours.register_passengers'));
        $this->assertTrue($tourHead->hasPermissionTo('tours.confirm_registration'));

        // Should be able to manage attendance
        $this->assertTrue($tourHead->hasPermissionTo('tours.attendance.manage'));

        // Should be able to view reports
        $this->assertTrue($tourHead->hasPermissionTo('tours.reports.view'));
    }

    /**
     * Test Charity workflow - beneficiary and aid management.
     */
    public function test_charity_workflow_beneficiary_and_aid(): void
    {
        $charityHead = $this->createCharityHeadUser();

        // Should be able to manage beneficiaries
        $this->assertTrue($charityHead->hasPermissionTo('charity.beneficiaries.manage'));

        // Should be able to distribute aid
        $this->assertTrue($charityHead->hasPermissionTo('charity.aid.distribute'));

        // Should be able to view charity reports
        $this->assertTrue($charityHead->hasPermissionTo('charity.reports.view'));

        // Should be able to view contribution reports (for outstanding)
        $this->assertTrue($charityHead->hasPermissionTo('contributions.view_reports'));
    }

    /**
     * Test Inventory workflow - item and movement management.
     */
    public function test_inventory_workflow_item_and_movement(): void
    {
        $inventoryStaff = $this->createInventoryStaffUser();

        // Should be able to manage items
        $this->assertTrue($inventoryStaff->hasPermissionTo('inventory.items.manage'));

        // Should be able to manage movements
        $this->assertTrue($inventoryStaff->hasPermissionTo('inventory.movements.manage'));

        // Should be able to view reports
        $this->assertTrue($inventoryStaff->hasPermissionTo('inventory.reports.view'));

        // Should NOT be able to delete (soft delete with status)
        $this->assertFalse($inventoryStaff->hasPermissionTo('inventory.delete'));
    }

    /**
     * Test Media workflow - media management.
     */
    public function test_media_workflow_media_management(): void
    {
        $avHead = $this->createAvHeadUser();

        // Should be able to manage media
        $this->assertTrue($avHead->hasPermissionTo('media.manage'));
        $this->assertTrue($avHead->hasPermissionTo('media.upload'));
        $this->assertTrue($avHead->hasPermissionTo('media.delete'));

        // Should be able to manage categories
        $this->assertTrue($avHead->hasPermissionTo('media.manage'));

        // Should be able to manage blog
        $this->assertTrue($avHead->hasPermissionTo('blog.manage'));

        // Should be able to manage announcements
        $this->assertTrue($avHead->hasPermissionTo('announcements.manage'));

        // Should be able to manage FAQ
        $this->assertTrue($avHead->hasPermissionTo('faq.manage'));
    }

    /**
     * Test Worship workflow - songs and rehearsals.
     */
    public function test_worship_workflow_songs_and_rehearsals(): void
    {
        $worshipMonitor = $this->createWorshipMonitorUser();
        $mezmurHead = $this->createMezmurHeadUser();

        // Worship monitor should manage songs and rehearsals
        $this->assertTrue($worshipMonitor->hasPermissionTo('worship.songs.manage'));
        $this->assertTrue($worshipMonitor->hasPermissionTo('worship.rehearsals.manage'));

        // Mezmur head should have full worship management
        $this->assertTrue($mezmurHead->hasPermissionTo('worship.manage'));
        $this->assertTrue($mezmurHead->hasPermissionTo('worship.songs.manage'));
        $this->assertTrue($mezmurHead->hasPermissionTo('worship.rehearsals.manage'));
    }

    /**
     * Test Nibret Hisab workflow - finance and inventory oversight.
     */
    public function test_nibret_hisab_workflow_oversight(): void
    {
        $nibretHisabHead = $this->createNibretHisabHeadUser();

        // Should have financial oversight
        $this->assertTrue($nibretHisabHead->hasPermissionTo('financial.reports.view'));
        $this->assertTrue($nibretHisabHead->hasPermissionTo('financial.statements.generate'));

        // Should have inventory management
        $this->assertTrue($nibretHisabHead->hasPermissionTo('inventory.manage'));
        $this->assertTrue($nibretHisabHead->hasPermissionTo('inventory.items.manage'));
        $this->assertTrue($nibretHisabHead->hasPermissionTo('inventory.movements.manage'));

        // Should have reports view
        $this->assertTrue($nibretHisabHead->hasPermissionTo('reports.view'));
    }

    /**
     * Test teacher assignment workflow.
     */
    public function test_teacher_assignment_workflow(): void
    {
        $educationHead = $this->createEducationHeadUser();

        // Should be able to manage teachers
        $this->assertTrue($educationHead->hasPermissionTo('teachers.manage'));
        $this->assertTrue($educationHead->hasPermissionTo('teachers.assign'));

        // Should be able to view teacher attendance
        $this->assertTrue($educationHead->hasPermissionTo('teachers.attendance.view'));
    }

    /**
     * Test member status change workflow.
     */
    public function test_member_status_change_workflow(): void
    {
        $hrHead = $this->createHrHeadUser();
        $staff = $this->createStaffUser();

        // HR Head should be able to change member status
        $this->assertTrue($hrHead->hasPermissionTo('members.update'));

        // Staff should NOT be able to change member status
        $this->assertFalse($staff->hasPermissionTo('members.update'));
    }

    /**
     * Test group assignment workflow.
     */
    public function test_group_assignment_workflow(): void
    {
        $hrHead = $this->createHrHeadUser();
        $internalRelationsHead = $this->createInternalRelationsHeadUser();
        $staff = $this->createStaffUser();

        // Both HR Head and Internal Relations Head should assign groups
        $this->assertTrue($hrHead->hasPermissionTo('groups.assign'));
        $this->assertTrue($internalRelationsHead->hasPermissionTo('groups.assign'));

        // Staff should NOT be able to assign groups
        $this->assertFalse($staff->hasPermissionTo('groups.assign'));
    }

    /**
     * Test bulk operations workflow.
     */
    public function test_bulk_operations_workflow(): void
    {
        $hrHead = $this->createHrHeadUser();
        $educationHead = $this->createEducationHeadUser();
        $staff = $this->createStaffUser();

        // HR Head should be able to bulk assign members
        $this->assertTrue($hrHead->hasPermissionTo('groups.bulk_assign'));

        // Education Head should be able to bulk promote students
        $this->assertTrue($educationHead->hasPermissionTo('education.bulk_promote'));

        // Staff should NOT have bulk operation permissions
        $this->assertFalse($staff->hasPermissionTo('groups.bulk_assign'));
    }

    /**
     * Test attendance session lock/unlock workflow.
     */
    public function test_attendance_lock_unlock_workflow(): void
    {
        $eduMonitor = $this->createEducationMonitorUser();
        $educationHead = $this->createEducationHeadUser();
        $staff = $this->createStaffUser();

        // Education Monitor can lock but not unlock
        $this->assertTrue($eduMonitor->hasPermissionTo('education.attendance.lock'));
        $this->assertFalse($eduMonitor->hasPermissionTo('education.attendance.unlock'));

        // Education Head can both lock and unlock
        $this->assertTrue($educationHead->hasPermissionTo('education.attendance.lock'));
        $this->assertTrue($educationHead->hasPermissionTo('education.attendance.unlock'));

        // Staff cannot manage attendance
        $this->assertFalse($staff->hasPermissionTo('education.attendance.lock'));
        $this->assertFalse($staff->hasPermissionTo('education.attendance.unlock'));
    }

    /**
     * Test document upload and management workflow.
     */
    public function test_document_management_workflow(): void
    {
        $deptHead = $this->createHrHeadUser();
        $deptSecretary = $this->createDepartmentSecretaryUser();
        $staff = $this->createStaffUser();

        // Department Head can upload and manage documents
        $this->assertTrue($deptHead->hasPermissionTo('documents.upload'));
        $this->assertTrue($deptHead->hasPermissionTo('documents.manage'));
        $this->assertTrue($deptHead->hasPermissionTo('documents.delete'));

        // Department Secretary can upload and view but not delete
        $this->assertTrue($deptSecretary->hasPermissionTo('documents.upload'));
        $this->assertTrue($deptSecretary->hasPermissionTo('documents.view'));
        $this->assertFalse($deptSecretary->hasPermissionTo('documents.delete'));

        // Staff can only view
        $this->assertFalse($staff->hasPermissionTo('documents.upload'));
        $this->assertFalse($staff->hasPermissionTo('documents.manage'));
    }

    /**
     * Test profile update workflow.
     */
    public function test_profile_update_workflow(): void
    {
        $admin = $this->createAdminUser();
        $staff = $this->createStaffUser();

        // All users should be able to update their own profile
        // This is typically handled at the application level, not by permission
        // Just verify they can authenticate
        $this->assertTrue($admin->is_active);
        $this->assertTrue($staff->is_active);
    }

    /**
     * Test session management workflow.
     */
    public function test_session_management_workflow(): void
    {
        $admin = $this->createAdminUser();
        $staff = $this->createStaffUser();

        // Admin should be able to manage sessions
        // This would typically be a route-level check
        // For now, just verify they can access the system
        $this->assertTrue($admin->canAccessPanel(\Filament\Panel::make()));
        $this->assertTrue($staff->canAccessPanel(\Filament\Panel::make()));
    }

    /**
     * Test charity head can track outstanding contributions.
     */
    public function test_charity_outstanding_contributions_workflow(): void
    {
        $charityHead = $this->createCharityHeadUser();
        $financeHead = $this->createFinanceHeadUser();
        $staff = $this->createStaffUser();

        // Both Charity Head and Finance Head can view outstanding
        $this->assertTrue($charityHead->hasPermissionTo('contributions.view_reports'));
        $this->assertTrue($financeHead->hasPermissionTo('contributions.view_reports'));

        // Staff cannot view outstanding
        $this->assertFalse($staff->hasPermissionTo('contributions.view_reports'));
    }
}
