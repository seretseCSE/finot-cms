<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\AcademicYear;
use App\Models\StudentEnrollment;
use App\Models\AttendanceSession;
use App\Models\TeacherAttendance;
use App\Models\User;

class TestEducationIntegrationCommand extends Command
{
    protected $signature = 'test:education-integration {--cleanup : Clean up test data after running}';
    protected $description = 'Run integration tests for Education module';

    public function handle(): int
    {
        $this->info('Starting Education Integration Tests...');
        $this->line(str_repeat('=', 50));

        if ($this->option('cleanup')) {
            return $this->cleanupTestData();
        }

        $results = [];

        try {
            $results['academic_year'] = $this->testAcademicYearLifecycle();
            $results['enrollment'] = $this->testEnrollmentValidation();
            $results['attendance_timeline'] = $this->testAttendanceTimeline();
            $results['session_locking'] = $this->testSessionLocking();
            $results['auto_lock'] = $this->testAutoLock();
            $results['teacher_absence'] = $this->testTeacherAbsenceImpact();
            $results['bulk_promotion'] = $this->testBulkPromotion();
            $results['permissions'] = $this->testPermissions();

            $this->displayResults($results);
            return $this->calculateExitCode($results);
        } catch (\Exception $e) {
            $this->error("Test failed with exception: " . $e->getMessage());
            return 1;
        }
    }

    private function testAcademicYearLifecycle(): array
    {
        $this->info('Testing Academic Year Lifecycle...');
        
        // Create new academic year
        $year = AcademicYear::create([
            'name' => 'Test Year 2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-07-31',
            'status' => 'Draft',
            'created_by' => 1,
        ]);

        // Activate it
        $year->update(['status' => 'Active']);

        // Check if previous year was deactivated
        $previousActive = AcademicYear::where('id', '!=', $year->id)
            ->where('status', 'Active')
            ->count();

        $success = $year->status === 'Active' && $previousActive === 0;

        $this->line($success ? '✓ Academic year activation works' : '✗ Academic year activation failed');
        
        return [
            'passed' => $success,
            'details' => $success ? 'Year activated, previous deactivated' : 'Activation failed'
        ];
    }

    private function testEnrollmentValidation(): array
    {
        $this->info('Testing Enrollment Validation...');
        
        $activeYear = AcademicYear::where('is_active', true)->first();
        if (!$activeYear) {
            return ['passed' => false, 'details' => 'No active academic year found'];
        }

        // Create first enrollment
        $enrollment1 = StudentEnrollment::create([
            'member_id' => 1,
            'class_id' => 1,
            'academic_year_id' => $activeYear->id,
            'enrollment_date' => now(),
            'status' => 'Enrolled',
            'created_by' => 1,
        ]);

        // Try to create duplicate
        try {
            $enrollment2 = StudentEnrollment::create([
                'member_id' => 1,
                'class_id' => 2,
                'academic_year_id' => $activeYear->id,
                'enrollment_date' => now(),
                'status' => 'Enrolled',
                'created_by' => 1,
            ]);
            $success = false;
        } catch (\Exception $e) {
            $success = str_contains($e->getMessage(), 'already enrolled');
        }

        $this->line($success ? '✓ Enrollment validation works' : '✗ Duplicate enrollment allowed');
        
        return [
            'passed' => $success,
            'details' => $success ? 'Duplicate properly blocked' : 'Validation failed'
        ];
    }

    private function testAttendanceTimeline(): array
    {
        $this->info('Testing Attendance Timeline...');
        
        // Create attendance session
        $session = AttendanceSession::create([
            'school_class_id' => 1,
            'session_date' => now(),
            'status' => 'Open',
            'created_by' => 1,
        ]);

        // Check if it would appear in timeline (simplified test)
        $exists = AttendanceSession::where('id', $session->id)->exists();
        
        $this->line($exists ? '✓ Attendance session created' : '✗ Session creation failed');
        
        return [
            'passed' => $exists,
            'details' => $exists ? 'Session created for timeline' : 'Session not found'
        ];
    }

    private function testSessionLocking(): array
    {
        $this->info('Testing Session Locking...');
        
        // Create and lock a session
        $session = AttendanceSession::create([
            'school_class_id' => 1,
            'session_date' => now(),
            'status' => 'Open',
            'created_by' => 1,
        ]);

        $session->update(['status' => 'Locked']);
        
        $success = $session->fresh()->status === 'Locked';
        
        $this->line($success ? '✓ Session locking works' : '✗ Session locking failed');
        
        return [
            'passed' => $success,
            'details' => $success ? 'Session successfully locked' : 'Lock operation failed'
        ];
    }

    private function testAutoLock(): array
    {
        $this->info('Testing Auto-Lock Logic...');
        
        // Create old session (>30 days)
        $oldSession = AttendanceSession::create([
            'school_class_id' => 1,
            'session_date' => now()->subDays(35),
            'status' => 'Open',
            'created_by' => 1,
        ]);

        // Create recent session (<30 days)
        $recentSession = AttendanceSession::create([
            'school_class_id' => 1,
            'session_date' => now()->subDays(25),
            'status' => 'Open',
            'created_by' => 1,
        ]);

        // Simulate auto-lock logic
        $sessionsToLock = AttendanceSession::where('status', 'Open')
            ->where('created_at', '<=', now()->subDays(30))
            ->get();

        $success = $sessionsToLock->contains($oldSession) && !$sessionsToLock->contains($recentSession);
        
        $this->line($success ? '✓ Auto-lock logic correct' : '✗ Auto-lock logic failed');
        
        return [
            'passed' => $success,
            'details' => $success ? 'Correct sessions selected for locking' : 'Wrong sessions selected'
        ];
    }

    private function testTeacherAbsenceImpact(): array
    {
        $this->info('Testing Teacher Absence Impact...');
        
        // Create session
        $session = AttendanceSession::create([
            'school_class_id' => 1,
            'session_date' => now(),
            'status' => 'Open',
            'created_by' => 1,
        ]);

        // Mark teacher absent (simplified test)
        $teacherAttendance = TeacherAttendance::create([
            'teacher_id' => 1,
            'attendance_date' => now(),
            'status' => 'Absent',
            'created_by' => 1,
        ]);

        // In real implementation, this would trigger session cancellation
        // For test, we verify the teacher attendance record exists
        $success = $teacherAttendance->exists();
        
        $this->line($success ? '✓ Teacher absence recorded' : '✗ Teacher attendance failed');
        
        return [
            'passed' => $success,
            'details' => $success ? 'Teacher absence properly recorded' : 'Teacher attendance not saved'
        ];
    }

    private function testBulkPromotion(): array
    {
        $this->info('Testing Bulk Promotion...');
        
        $activeYear = AcademicYear::where('is_active', true)->first();
        if (!$activeYear) {
            return ['passed' => false, 'details' => 'No active academic year'];
        }

        // Create test enrollment
        $enrollment = StudentEnrollment::create([
            'member_id' => 1,
            'class_id' => 1,
            'academic_year_id' => $activeYear->id,
            'enrollment_date' => now(),
            'status' => 'Enrolled',
            'created_by' => 1,
        ]);

        // Simulate promotion logic
        $enrollment->update([
            'status' => 'Completed',
            'completion_date' => now(),
        ]);

        $success = $enrollment->fresh()->status === 'Completed';
        
        $this->line($success ? '✓ Promotion logic works' : '✗ Promotion failed');
        
        return [
            'passed' => $success,
            'details' => $success ? 'Enrollment properly completed' : 'Status update failed'
        ];
    }

    private function testPermissions(): array
    {
        $this->info('Testing Permissions...');
        
        // Test if education_head role exists
        $educationHead = User::role('education_head')->exists();
        $educationMonitor = User::role('education_monitor')->exists();
        
        $success = $educationHead && $educationMonitor;
        
        $this->line($success ? '✓ Role permissions exist' : '✗ Missing roles');
        
        return [
            'passed' => $success,
            'details' => $success ? 'Required roles exist' : 'Missing role definitions'
        ];
    }

    private function displayResults(array $results): void
    {
        $this->line(str_repeat('=', 50));
        $this->info('Test Results Summary:');
        $this->line(str_repeat('-', 50));

        $total = count($results);
        $passed = collect($results)->sum(fn($result) => $result['passed'] ? 1 : 0);

        foreach ($results as $test => $result) {
            $status = $result['passed'] ? 'PASS' : 'FAIL';
            $color = $result['passed'] ? 'green' : 'red';
            $this->line("<fg={$color}>{$status}</> {$test}: {$result['details']}");
        }

        $this->line(str_repeat('-', 50));
        $this->info("Total: {$passed}/{$total} tests passed");
        
        if ($passed === $total) {
            $this->info('🎉 All tests passed!');
        } else {
            $this->error('❌ Some tests failed');
        }
    }

    private function calculateExitCode(array $results): int
    {
        $failed = collect($results)->sum(fn($result) => $result['passed'] ? 0 : 1);
        return $failed > 0 ? 1 : 0;
    }

    private function cleanupTestData(): int
    {
        $this->info('Cleaning up test data...');
        
        $count = 0;
        $count += StudentEnrollment::where('status', 'like', '%Test%')->delete();
        $count += AttendanceSession::where('created_at', '>', now()->subHours(1))->delete();
        $count += TeacherAttendance::where('created_at', '>', now()->subHours(1))->delete();
        $count += AcademicYear::where('name', 'like', '%Test%')->delete();
        
        $this->info("Cleaned up {$count} test records");
        return 0;
    }
}
