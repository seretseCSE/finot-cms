<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EducationTestingChecklistCommand extends Command
{
    protected $signature = 'checklist:education-testing';
    protected $description = 'Display interactive Education testing checklist';

    public function handle(): int
    {
        $this->info('📚 Education Module Integration Testing Checklist');
        $this->line(str_repeat('=', 60));
        $this->line('');

        $checklist = [
            'Academic Year Lifecycle' => [
                'Create academic year with Draft status',
                'Activate academic year',
                'Verify previous year automatically deactivated',
                'Check deactivation modal with summary stats',
                'Verify audit log entry (Tier-2)',
                'Confirm old enrollments marked Completed',
                'Confirm old sessions marked Completed',
            ],
            'Enrollment Validation' => [
                'Create student enrollment in active year',
                'Attempt duplicate enrollment for same student/year',
                'Verify validation error message',
                'Check EnrollmentUniquePerYear rule',
            ],
            'Attendance Timeline' => [
                'Create attendance session',
                'Navigate to student timeline',
                'Verify session appears in timeline',
                'Check proper date formatting',
                'Verify status badges',
            ],
            'Session Locking' => [
                'Mark student attendance',
                'Change session status to Completed',
                'Change session status to Locked',
                'Attempt to edit locked session',
                'Verify read-only enforcement',
                'Check UI prevents edits',
            ],
            'Session Unlock' => [
                'As Education Head, find locked session',
                'Click Unlock action',
                'Enter justification and notes',
                'Confirm unlock',
                'Verify session status changed',
                'Check Tier-2 audit log entry',
                'Verify justification recorded',
            ],
            'Auto-Lock Commands' => [
                'Create test sessions (>30 days and <30 days old)',
                'Run: php artisan attendance:auto-lock --dry-run',
                'Verify correct sessions selected',
                'Run: php artisan attendance:auto-lock',
                'Verify old sessions locked',
                'Check Tier-1 audit logs',
                'Run: php artisan attendance:send-lock-reminders',
                'Verify notifications created',
            ],
            'Teacher Absence Impact' => [
                'Create attendance session',
                'Mark teacher as absent',
                'Verify session status changes to Cancelled',
                'Check student attendance disabled',
                'Verify visual indicators',
            ],
            'Bulk Promotion' => [
                'Select class with enrolled students',
                'Click Promote Students bulk action',
                'Fill promotion form',
                'Confirm promotion',
                'Verify old enrollment Completed',
                'Verify new enrollment created',
                'Check promotion audit trail',
            ],
            'Permission Checks' => [
                'Login as Education Monitor',
                'Check Teacher Attendance Report access',
                'Verify access denied',
                'Test other role permissions',
                'Check UI permission indicators',
            ],
            'Cross-Feature Integration' => [
                'Test Ethiopian date display',
                'Check dashboard widgets',
                'Verify role-based widget visibility',
                'Test date picker functionality',
                'Check timeline Ethiopian formatting',
            ],
        ];

        foreach ($checklist as $category => $items) {
            $this->displayCategory($category, $items);
        }

        $this->line('');
        $this->info('🔧 Helper Commands:');
        $this->line('  php artisan test:education-integration    Run automated tests');
        $this->line('  php artisan test:education-integration --cleanup  Clean test data');
        $this->line('');
        $this->info('📊 Test Data Status:');
        $this->displayTestDataStatus();
        $this->line('');
        $this->info('✅ Sign-off when all tests pass');
        
        return 0;
    }

    private function displayCategory(string $category, array $items): void
    {
        $this->line("📋 {$category}");
        foreach ($items as $index => $item) {
            $checkbox = $this->ask("  [ ] {$item} (Press Enter to mark as done)");
            $this->line("  [✓] {$item}");
        }
        $this->line('');
    }

    private function displayTestDataStatus(): void
    {
        $academicYears = \App\Models\AcademicYear::count();
        $activeYear = \App\Models\AcademicYear::where('is_active', true)->count();
        $enrollments = \App\Models\StudentEnrollment::count();
        $sessions = \App\Models\AttendanceSession::count();
        $lockedSessions = \App\Models\AttendanceSession::where('status', 'Locked')->count();

        $this->line("  Academic Years: {$academicYears} ({$activeYear} active)");
        $this->line("  Student Enrollments: {$enrollments}");
        $this->line("  Attendance Sessions: {$sessions} ({$lockedSessions} locked)");
        
        // Check for old sessions that should be locked
        $oldOpenSessions = \App\Models\AttendanceSession::where('status', 'Open')
            ->where('created_at', '<=', now()->subDays(30))
            ->count();
            
        if ($oldOpenSessions > 0) {
            $this->line("  ⚠️  {$oldOpenSessions} sessions older than 30 days still Open");
        } else {
            $this->line("  ✓ No overdue sessions requiring auto-lock");
        }
    }
}
