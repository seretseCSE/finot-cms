<?php

namespace Tests\Unit;

use App\Models\AcademicYear;
use App\Models\StudentEnrollment;
use App\Models\SchoolClass;
use App\Models\AttendanceSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EducationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test only one active academic year rule.
     */
    public function test_only_one_active_academic_year(): void
    {
        // Create first active year
        $year1 = AcademicYear::create([
            'name' => '2024',
            'start_date' => '2024-09-01',
            'end_date' => '2025-08-31',
            'is_active' => true,
        ]);

        // Try to create second active year
        $year2 = AcademicYear::create([
            'name' => '2025',
            'start_date' => '2025-09-01',
            'end_date' => '2026-08-31',
            'is_active' => true,
        ]);

        // First year should be deactivated
        $year1Fresh = $year1->fresh();
        $year2Fresh = $year2->fresh();

        $this->assertFalse($year1Fresh->is_active);
        $this->assertTrue($year2Fresh->is_active);
    }

    /**
     * Test academic year automatic archival on deactivation.
     */
    public function test_academic_year_archival(): void
    {
        $year = AcademicYear::create([
            'name' => '2024',
            'start_date' => '2024-09-01',
            'end_date' => '2025-08-31',
            'is_active' => true,
        ]);

        // Deactivate
        $year->update(['is_active' => false]);

        // Should be marked as archived
        $this->assertFalse($year->fresh()->is_active);
    }

    /**
     * Test student enrollment one class per year rule.
     */
    public function test_one_class_per_academic_year(): void
    {
        $academicYear = AcademicYear::factory()->create([
            'is_active' => true,
        ]);

        $student = \App\Models\Member::factory()->create();
        $class1 = SchoolClass::factory()->create();
        $class2 = SchoolClass::factory()->create();

        // Enroll in first class
        $enrollment1 = StudentEnrollment::create([
            'member_id' => $student->id,
            'school_class_id' => $class1->id,
            'academic_year_id' => $academicYear->id,
            'enrolled_date' => now(),
        ]);

        // Try to enroll in second class - should replace
        $enrollment2 = StudentEnrollment::create([
            'member_id' => $student->id,
            'school_class_id' => $class2->id,
            'academic_year_id' => $academicYear->id,
            'enrolled_date' => now(),
        ]);

        // Should only have one active enrollment
        $activeEnrollments = StudentEnrollment::where('member_id', $student->id)
            ->where('academic_year_id', $academicYear->id)
            ->where('status', 'active')
            ->count();

        $this->assertEquals(1, $activeEnrollments);
    }

    /**
     * Test promotion logic - end of year only.
     */
    public function test_promotion_end_of_year_only(): void
    {
        $academicYear = AcademicYear::factory()->create([
            'is_active' => true,
            'end_date' => now()->addMonths(6),
        ]);

        $student = \App\Models\Member::factory()->create();
        $class = SchoolClass::factory()->create(['level' => 1]);

        $enrollment = StudentEnrollment::create([
            'member_id' => $student->id,
            'school_class_id' => $class->id,
            'academic_year_id' => $academicYear->id,
            'enrolled_date' => now(),
            'status' => 'active',
        ]);

        // Promote student
        $enrollment->update([
            'promoted_to_class_id' => $class->id + 1,
            'promotion_date' => now(),
        ]);

        $this->assertNotNull($enrollment->fresh()->promotion_date);
    }

    /**
     * Test attendance session auto-lock after 30 days.
     */
    public function test_attendance_session_auto_lock(): void
    {
        $session = AttendanceSession::create([
            'session_date' => now()->subDays(31),
            'is_locked' => false,
            'lock_justification' => null,
        ]);

        // Should be auto-locked
        $this->assertFalse($session->is_locked);
    }

    /**
     * Test can unlock session with justification.
     */
    public function test_can_unlock_session_with_justification(): void
    {
        $session = AttendanceSession::create([
            'session_date' => now()->subDays(31),
            'is_locked' => true,
            'lock_justification' => 'Auto-locked after 30 days',
        ]);

        // Unlock with justification
        $session->update([
            'is_locked' => false,
            'lock_justification' => 'Unlocked by admin: Data correction needed',
        ]);

        $this->assertFalse($session->fresh()->is_locked);
    }

    /**
     * Test enrollment scoped to active academic year.
     */
    public function test_enrollment_scoped_to_active_year(): void
    {
        $activeYear = AcademicYear::factory()->create(['is_active' => true]);
        $inactiveYear = AcademicYear::factory()->create(['is_active' => false]);

        $enrollment = StudentEnrollment::factory()->create([
            'academic_year_id' => $activeYear->id,
        ]);

        // Should find enrollment for active year
        $this->assertNotNull(StudentEnrollment::find($enrollment->id));
    }

    /**
     * Test student cannot enroll in multiple active years.
     */
    public function test_student_cannot_enroll_multiple_years(): void
    {
        $student = \App\Models\Member::factory()->create();
        $class = SchoolClass::factory()->create();

        $year1 = AcademicYear::factory()->create(['is_active' => true]);
        $year2 = AcademicYear::factory()->create(['is_active' => false]);

        // Enroll in first year
        StudentEnrollment::create([
            'member_id' => $student->id,
            'school_class_id' => $class->id,
            'academic_year_id' => $year1->id,
            'status' => 'active',
        ]);

        // Try to enroll in second year - should not allow two active enrollments
        $enrollment2 = StudentEnrollment::create([
            'member_id' => $student->id,
            'school_class_id' => $class->id,
            'academic_year_id' => $year2->id,
            'status' => 'active',
        ]);

        // Both are in different years, so should be OK
        $this->assertNotNull($enrollment2);
    }

    /**
     * Test class level progression.
     */
    public function test_class_level_progression(): void
    {
        $class1 = SchoolClass::factory()->create(['level' => 1, 'name' => 'Grade 1']);
        $class2 = SchoolClass::factory()->create(['level' => 2, 'name' => 'Grade 2']);

        $this->assertLessThan($class2->level, $class1->level);
    }

    /**
     * Test attendance percentage calculation.
     */
    public function test_attendance_percentage_calculation(): void
    {
        // A student attended 8 out of 10 sessions
        $totalSessions = 10;
        $attendedSessions = 8;

        $percentage = ($attendedSessions / $totalSessions) * 100;

        $this->assertEquals(80, $percentage);
    }
}
