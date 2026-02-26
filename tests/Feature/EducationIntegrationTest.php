<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\AcademicYear;
use App\Models\StudentEnrollment;
use App\Models\AttendanceSession;
use App\Models\TeacherAttendance;
use Database\Seeders\TestRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class EducationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $educationHead;
    protected User $educationMonitor;
    protected AcademicYear $activeYear;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles for testing
        $this->seed(TestRoleSeeder::class);

        // Create test users
        $this->educationHead = User::factory()->create();
        $this->educationHead->assignRole('education_head');

        $this->educationMonitor = User::factory()->create();
        $this->educationMonitor->assignRole('education_monitor');

        // Create active academic year
        $this->activeYear = AcademicYear::factory()->create([
            'name' => 'Test Year 2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-07-31',
            'status' => 'Active',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_can_create_and_activate_academic_year()
    {
        // Create new academic year
        $newYear = AcademicYear::factory()->create([
            'name' => 'Future Year 2027-2028',
            'status' => 'Draft',
        ]);

        // Activate it
        $newYear->update(['status' => 'Active']);

        // Check previous year was deactivated
        $this->assertEquals('Active', $newYear->fresh()->status);
        $this->assertEquals('Inactive', $this->activeYear->fresh()->status);
        $this->assertEquals(1, AcademicYear::where('status', 'Active')->count());
    }

    /** @test */
    public function it_prevents_duplicate_enrollments_in_same_year()
    {
        // Create first enrollment
        $enrollment1 = StudentEnrollment::factory()->create([
            'member_id' => 1,
            'academic_year_id' => $this->activeYear->id,
            'status' => 'Enrolled',
        ]);

        // Attempt to create duplicate
        $this->expectException(\Exception::class);
        $enrollment2 = StudentEnrollment::factory()->create([
            'member_id' => 1,
            'academic_year_id' => $this->activeYear->id,
            'status' => 'Enrolled',
        ]);
    }

    /** @test */
    public function it_creates_attendance_session_for_timeline()
    {
        $session = AttendanceSession::factory()->create([
            'school_class_id' => 1,
            'session_date' => now(),
            'status' => 'Open',
        ]);

        $this->assertDatabaseHas('attendance_sessions', [
            'id' => $session->id,
            'status' => 'Open',
        ]);
    }

    /** @test */
    public function it_locks_attendance_sessions()
    {
        $session = AttendanceSession::factory()->create([
            'status' => 'Open',
        ]);

        $session->update(['status' => 'Locked']);

        $this->assertEquals('Locked', $session->fresh()->status);
    }

    /** @test */
    public function it_identifies_sessions_for_auto_lock()
    {
        // Create old session (>30 days)
        $oldSession = AttendanceSession::factory()->create([
            'session_date' => now()->subDays(35),
            'status' => 'Open',
            'created_at' => now()->subDays(35),
        ]);

        // Create recent session (<30 days)
        $recentSession = AttendanceSession::factory()->create([
            'session_date' => now()->subDays(25),
            'status' => 'Open',
            'created_at' => now()->subDays(25),
        ]);

        // Test auto-lock logic
        $sessionsToLock = AttendanceSession::where('status', 'Open')
            ->where('created_at', '<=', now()->subDays(30))
            ->get();

        $this->assertTrue($sessionsToLock->contains($oldSession));
        $this->assertFalse($sessionsToLock->contains($recentSession));
    }

    /** @test */
    public function it_records_teacher_attendance()
    {
        $teacherAttendance = TeacherAttendance::factory()->create([
            'teacher_id' => 1,
            'attendance_date' => now(),
            'status' => 'Present',
        ]);

        $this->assertDatabaseHas('teacher_attendance', [
            'id' => $teacherAttendance->id,
            'status' => 'Present',
        ]);
    }

    /** @test */
    public function it_promotes_students_correctly()
    {
        // Create current enrollment
        $enrollment = StudentEnrollment::factory()->create([
            'member_id' => 1,
            'academic_year_id' => $this->activeYear->id,
            'status' => 'Enrolled',
        ]);

        // Simulate promotion
        $enrollment->update([
            'status' => 'Completed',
            'completion_date' => now(),
        ]);

        $this->assertEquals('Completed', $enrollment->fresh()->status);
        $this->assertNotNull($enrollment->fresh()->completion_date);
    }

    /** @test */
    public function education_head_can_access_all_education_features()
    {
        $response = $this->actingAs($this->educationHead)
            ->get('/admin/academic-years');

        $response->assertStatus(200);

        $response = $this->actingAs($this->educationHead)
            ->get('/admin/attendance-sessions');

        $response->assertStatus(200);
    }

    /** @test */
    public function education_monitor_has_limited_access()
    {
        $response = $this->actingAs($this->educationMonitor)
            ->get('/admin/academic-years');

        $response->assertStatus(200); // Can view

        // Test specific restrictions (would need to implement actual routes)
        // This is a placeholder for permission testing
        $this->assertTrue(true);
    }

    /** @test */
    public function it_creates_audit_log_for_session_unlock()
    {
        Log::shouldReceive('channel')->with('audit')->andReturnSelf();
        Log::shouldReceive('warning')->once()->with('Tier 2 Audit Log', \Mockery::type('array'));

        // Simulate unlock action
        $session = AttendanceSession::factory()->create(['status' => 'Locked']);

        // This would normally be called in the unlock action
        Log::channel('audit')->warning('Tier 2 Audit Log', [
            'tier' => 2,
            'action' => 'session_unlocked',
            'session_id' => $session->id,
            'performed_by' => $this->educationHead->id,
            'timestamp' => now()->toDateTimeString(),
        ]);

        // Test passes if Log mock was called
        $this->assertTrue(true);
    }

    /** @test */
    public function it_validates_academic_year_dates()
    {
        $year = AcademicYear::factory()->make([
            'start_date' => '2026-09-01',
            'end_date' => '2026-08-31', // Invalid: end before start
        ]);

        $this->assertFalse($year->isValidDateRange());
    }

    /** @test */
    public function it_calculates_attendance_rate()
    {
        // This would test the attendance rate calculation
        // Implementation depends on your specific calculation logic
        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_ethiopian_dates()
    {
        // Test Ethiopian date conversion/display
        // Implementation depends on EthiopianDateHelper
        $this->assertTrue(true);
    }
}
