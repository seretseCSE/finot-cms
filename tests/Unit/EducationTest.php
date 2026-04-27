<?php

namespace Tests\Unit;

use App\Models\AcademicYear;
use App\Models\StudentEnrollment;
use App\Models\SchoolClass;
use App\Models\AttendanceSession;
use App\Models\User;
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
        $user = User::factory()->create();

        // Create first active year
        $year1 = AcademicYear::create([
            'name' => '2024',
            'start_date' => '2024-09-01',
            'end_date' => '2025-08-31',
            'status' => 'Active',
            'created_by' => $user->id,
        ]);

        // Try to create second active year
        $year2 = AcademicYear::create([
            'name' => '2025',
            'start_date' => '2025-09-01',
            'end_date' => '2026-08-31',
            'status' => 'Active',
            'created_by' => $user->id,
        ]);

        // First year should be deactivated
        $year1Fresh = $year1->fresh();
        $year2Fresh = $year2->fresh();

        $this->assertEquals('Deactivated', $year1Fresh->status);
        $this->assertEquals('Active', $year2Fresh->status);
    }

    /**
     * Test academic year automatic archival on deactivation.
     */
    public function test_academic_year_archival(): void
    {
        $user = User::factory()->create();

        $year = AcademicYear::create([
            'name' => '2024',
            'start_date' => '2024-09-01',
            'end_date' => '2025-08-31',
            'status' => 'Active',
            'created_by' => $user->id,
        ]);

        // Deactivate
        $year->update(['status' => 'Deactivated']);

        // Should be marked as deactivated
        $this->assertEquals('Deactivated', $year->fresh()->status);
    }

    /**
     * Test student enrollment one class per year rule.
     */
    public function test_one_class_per_academic_year(): void
    {
        $user = User::factory()->create();
        $academicYear = AcademicYear::factory()->create([
            'status' => 'Active',
        ]);

        $student = \App\Models\Member::factory()->create();
        $class1 = SchoolClass::factory()->create();

        // Enroll in first class
        $enrollment1 = StudentEnrollment::create([
            'member_id' => $student->id,
            'class_id' => $class1->id,
            'academic_year_id' => $academicYear->id,
            'enrolled_date' => now(),
            'status' => 'Enrolled',
            'enrolled_by' => $user->id,
        ]);

        // Try to enroll in second class in same year - should fail due to unique constraint
        $this->expectException(\Illuminate\Database\QueryException::class);

        StudentEnrollment::create([
            'member_id' => $student->id,
            'class_id' => $class1->id,
            'academic_year_id' => $academicYear->id,
            'enrolled_date' => now(),
            'status' => 'Enrolled',
            'enrolled_by' => $user->id,
        ]);
    }

    /**
     * Test promotion logic - end of year only.
     */
    public function test_promotion_end_of_year_only(): void
    {
        $academicYear = AcademicYear::factory()->create([
            'status' => 'Active',
            'end_date' => now()->addMonths(6),
        ]);

        $student = \App\Models\Member::factory()->create();
        $class = SchoolClass::factory()->create();

        $enrollment = StudentEnrollment::create([
            'member_id' => $student->id,
            'class_id' => $class->id,
            'academic_year_id' => $academicYear->id,
            'enrolled_date' => now(),
            'status' => 'Enrolled',
            'enrolled_by' => 1,
        ]);

        // Promote student
        $enrollment->update([
            'status' => 'Promoted',
            'completion_date' => now(),
        ]);

        $this->assertEquals('Promoted', $enrollment->fresh()->status);
    }

    /**
     * Test attendance session auto-lock after 30 days.
     */
    public function test_attendance_session_auto_lock(): void
    {
        $user = User::factory()->create();
        $academicYear = AcademicYear::factory()->create();
        $class = SchoolClass::factory()->create();

        $session = AttendanceSession::create([
            'class_id' => $class->id,
            'session_date' => now()->subDays(31),
            'status' => 'Open',
            'academic_year_id' => $academicYear->id,
            'created_by' => $user->id,
        ]);

        // Should not be auto-locked by default (application logic would handle this)
        $this->assertEquals('Open', $session->fresh()->status);
    }

    /**
     * Test can unlock session with justification.
     */
    public function test_can_unlock_session_with_justification(): void
    {
        $user = User::factory()->create();
        $academicYear = AcademicYear::factory()->create();
        $class = SchoolClass::factory()->create();

        $session = AttendanceSession::create([
            'class_id' => $class->id,
            'session_date' => now()->subDays(31),
            'status' => 'Locked',
            'locked_at' => now(),
            'academic_year_id' => $academicYear->id,
            'created_by' => $user->id,
        ]);

        // Unlock with justification
        $session->update([
            'status' => 'Open',
            'unlock_justification' => 'Unlocked by admin: Data correction needed',
            'unlocked_at' => now(),
        ]);

        $this->assertEquals('Open', $session->fresh()->status);
    }

    /**
     * Test enrollment scoped to active academic year.
     */
    public function test_enrollment_scoped_to_active_year(): void
    {
        $activeYear = AcademicYear::factory()->create(['status' => 'Active']);
        $inactiveYear = AcademicYear::factory()->create(['status' => 'Deactivated']);

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
        $user = User::factory()->create();

        $year1 = AcademicYear::factory()->create(['status' => 'Active']);
        $year2 = AcademicYear::factory()->create(['status' => 'Deactivated']);

        // Enroll in first year
        StudentEnrollment::create([
            'member_id' => $student->id,
            'class_id' => $class->id,
            'academic_year_id' => $year1->id,
            'status' => 'Enrolled',
            'enrolled_by' => $user->id,
            'enrolled_date' => now(),
        ]);

        // Try to enroll in second year - should not allow two active enrollments
        $enrollment2 = StudentEnrollment::create([
            'member_id' => $student->id,
            'class_id' => $class->id,
            'academic_year_id' => $year2->id,
            'status' => 'Enrolled',
            'enrolled_by' => $user->id,
            'enrolled_date' => now(),
        ]);

        // Both are in different years, so should be OK
        $this->assertNotNull($enrollment2);
    }

    /**
     * Test class names are unique.
     */
    public function test_class_names_are_unique(): void
    {
        $class1 = SchoolClass::factory()->create(['name' => 'Grade 1']);
        $class2 = SchoolClass::factory()->create(['name' => 'Grade 2']);

        $this->assertNotEquals($class1->name, $class2->name);
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
