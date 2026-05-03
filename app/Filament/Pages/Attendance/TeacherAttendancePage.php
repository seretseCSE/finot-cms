<?php

namespace App\Filament\Pages\Attendance;

use App\Models\AttendanceSession;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\TeacherAssignment;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TeacherAttendancePage extends Page
{
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-academic-cap';
    }

    public static function getNavigationLabel(): string
    {
        return 'Teacher Attendance';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Attendance';
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }

    protected string $view = 'filament.pages.attendance.teacher-attendance';

    protected static ?string $title = 'Teacher Attendance';

    public ?int $sessionId = null;

    public array $sessions = [];

    public array $assignments = [];

    public array $attendance = [];

    public array $selectedAssignments = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(['hr_head', 'education_head', 'admin', 'superadmin']);
    }

    public function mount(): void
    {
        $this->loadSessions();
    }

    public function updatedSessionId(): void
    {
        $this->reset(['assignments', 'attendance', 'selectedAssignments']);
        $this->loadTeacherAttendance();
    }

    public function loadSessions(): void
    {
        $this->sessions = AttendanceSession::query()
            ->with(['classes', 'teacherAssignmentsPivot'])
            ->where('status', 'Open')
            ->whereDate('session_date', today())
            ->orderBy('session_date', 'desc')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'label' => $s->session_date->format('M j, Y')
                    . ' (' . $s->classes->count() . ' classes, '
                    . $s->teacherAssignmentsPivot->count() . ' teachers)',
            ])
            ->pluck('label', 'id')
            ->toArray();
    }

    public function loadTeacherAttendance(): void
    {
        if (! $this->sessionId) {
            return;
        }

        $session = AttendanceSession::with('teacherAssignmentsPivot.teacher', 'teacherAssignmentsPivot.subject')->find($this->sessionId);

        if (! $session) {
            return;
        }

        $existing = TeacherAttendance::query()
            ->where('session_id', $session->id)
            ->pluck('attendance_status', 'teacher_assignment_id')
            ->toArray();

        $this->assignments = [];
        $this->attendance = [];

        foreach ($session->teacherAssignmentsPivot as $assignment) {
            $assignmentId = $assignment->id;

            $this->assignments[$assignmentId] = [
                'id' => $assignmentId,
                'teacher_name' => $assignment->teacher?->full_name ?? 'N/A',
                'subject_name' => $assignment->subject?->name ?? 'N/A',
            ];

            $this->attendance[$assignmentId] = $existing[$assignmentId] ?? null;
        }
    }

    public function applyBulkStatus(string $status): void
    {
        if (empty($this->selectedAssignments)) {
            foreach ($this->attendance as $assignmentId => $x) {
                $this->attendance[$assignmentId] = $status;
            }
        } else {
            foreach ($this->selectedAssignments as $assignmentId) {
                if (array_key_exists($assignmentId, $this->attendance)) {
                    $this->attendance[$assignmentId] = $status;
                }
            }
        }
    }

    public function toggleSelectAll(): void
    {
        $ids = array_keys($this->assignments);

        if (empty($ids)) {
            return;
        }

        if (count(array_intersect($this->selectedAssignments, $ids)) === count($ids)) {
            $this->selectedAssignments = array_values(array_diff($this->selectedAssignments, $ids));
        } else {
            $this->selectedAssignments = array_values(array_unique(array_merge($this->selectedAssignments, $ids)));
        }
    }

    public function saveAttendance(): void
    {
        if (! $this->sessionId) {
            return;
        }

        if (! Auth::user()?->hasRole(['hr_head', 'education_head', 'admin', 'superadmin'])) {
            Notification::make()->title('Access denied')->danger()->send();

            return;
        }

        DB::transaction(function (): void {
            foreach ($this->attendance as $assignmentId => $status) {
                if ($status === null) {
                    continue;
                }

                TeacherAttendance::updateOrCreate(
                    ['teacher_assignment_id' => $assignmentId, 'session_id' => $this->sessionId],
                    [
                        'attendance_status' => $status,
                        'marked_by' => Auth::id(),
                        'marked_at' => now(),
                    ]
                );
            }
        });

        Notification::make()
            ->title('Teacher attendance saved successfully')
            ->success()
            ->send();
    }

    public function getStatusColor(string $status): string
    {
        return match ($status) {
            'Present' => 'green',
            'Absent' => 'red',
            'Late' => 'blue',
            'Permission' => 'yellow',
            default => 'gray',
        };
    }

    public function getAttendanceSummaryProperty(): string
    {
        $counts = array_count_values(array_map(fn ($v) => $v ?? '', $this->attendance));
        $parts = [];
        foreach (['Present' => 'green', 'Absent' => 'red', 'Late' => 'blue', 'Permission' => 'yellow'] as $key => $color) {
            $count = $counts[$key] ?? 0;
            if ($count > 0) {
                $parts[] = "<span class='font-semibold text-{$color}-600'>{$count} {$key}</span>";
            }
        }

        $total = count($this->attendance);
        $marked = $total - ($counts[''] ?? 0);

        return "{$marked}/{$total} marked — " . implode(' | ', $parts);
    }
}
