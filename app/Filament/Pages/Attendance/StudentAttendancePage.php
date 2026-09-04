<?php

namespace App\Filament\Pages\Attendance;

use App\Models\AttendanceSession;
use App\Models\ClassModel;
use App\Models\StudentAttendance;
use App\Models\StudentEnrollment;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StudentAttendancePage extends Page
{
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-user-group';
    }

    public static function getNavigationLabel(): string
    {
        return 'Student Attendance';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Attendance';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    protected string $view = 'filament.pages.attendance.student-attendance';

    protected static ?string $title = 'Student Attendance';

    public ?int $classId = null;

    public ?int $sessionId = null;

    public array $sessions = [];

    public array $students = [];

    public array $attendance = [];

    public array $selectedStudents = [];

    public static function canAccess(): bool
    {
        return \App\Support\RoleGate::can('page.attendance.student');
    }

    public function mount(): void
    {
        $this->loadSessions();
    }

    public function updatedClassId(): void
    {
        $this->reset(['sessionId', 'students', 'attendance', 'selectedStudents']);
        $this->loadSessions();
    }

    public function updatedSessionId(): void
    {
        $this->reset(['students', 'attendance', 'selectedStudents']);
        $this->loadAttendance();
    }

    public function loadSessions(): void
    {
        if (! $this->classId) {
            $this->sessions = [];

            return;
        }

        $this->sessions = AttendanceSession::query()
            ->with('classes')
            ->where('status', 'Open')
            ->whereHas('classes', fn ($q) => $q->where('class_id', $this->classId))
            ->orderBy('session_date', 'desc')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'label' => $s->session_date->format('M j, Y')
                    . ' — ' . $s->classes->pluck('name')->join(', '),
            ])
            ->pluck('label', 'id')
            ->toArray();
    }

    public function loadAttendance(): void
    {
        if (! $this->sessionId) {
            return;
        }

        $session = AttendanceSession::with('academicYear')->find($this->sessionId);

        if (! $session || ! $this->classId) {
            return;
        }

        $enrollments = StudentEnrollment::query()
            ->with('member')
            ->where('class_id', $this->classId)
            ->where('academic_year_id', $session->academic_year_id)
            ->where('status', 'Enrolled')
            ->get();

        $existing = StudentAttendance::query()
            ->where('session_id', $session->id)
            ->pluck('status', 'student_id')
            ->toArray();

        $this->students = [];
        $this->attendance = [];

        foreach ($enrollments as $enrollment) {
            if (! $enrollment->member) {
                continue;
            }

            $studentId = $enrollment->member_id;

            $this->students[$studentId] = [
                'id' => $studentId,
                'name' => $enrollment->member->full_name,
                'code' => $enrollment->member->member_code,
            ];

            $this->attendance[$studentId] = $existing[$studentId] ?? null;
        }
    }

    public function applyBulkStatus(string $status): void
    {
        if (empty($this->selectedStudents)) {
            foreach ($this->attendance as $studentId => $x) {
                $this->attendance[$studentId] = $status;
            }
        } else {
            foreach ($this->selectedStudents as $studentId) {
                if (array_key_exists($studentId, $this->attendance)) {
                    $this->attendance[$studentId] = $status;
                }
            }
        }
    }

    public function toggleSelectAll(): void
    {
        if (count($this->selectedStudents) === count($this->students)) {
            $this->selectedStudents = [];
        } else {
            $this->selectedStudents = array_keys($this->students);
        }
    }

    public function saveAttendance(): void
    {
        if (! $this->sessionId) {
            return;
        }

        if (! Auth::user()?->can('attendance_records.mark')) {
            Notification::make()->title('Access denied')->danger()->send();

            return;
        }

        DB::transaction(function (): void {
            foreach ($this->attendance as $studentId => $status) {
                if ($status === null) {
                    continue;
                }

                StudentAttendance::updateOrCreate(
                    ['student_id' => $studentId, 'session_id' => $this->sessionId],
                    [
                        'status' => $status,
                        'marked_by' => Auth::id(),
                        'marked_at' => now(),
                    ]
                );
            }
        });

        Notification::make()
            ->title('Student attendance saved successfully')
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
            'Excused' => 'gray',
            default => 'gray',
        };
    }

    public function getClassesProperty()
    {
        return ClassModel::query()->active()->orderBy('name')->pluck('name', 'id');
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
