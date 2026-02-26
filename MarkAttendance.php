<?php

namespace App\Filament\Resources\AttendanceSessionResource\Pages;

use App\Filament\Resources\AttendanceSessionResource;
use App\Models\AttendanceSession;
use App\Models\StudentAttendance;
use App\Models\TeacherAttendance;
use App\Models\TeacherAssignment;
use App\Models\Teacher;
use App\Models\StudentEnrollment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;

class MarkAttendance extends Page
{
    protected static string $resource = AttendanceSessionResource::class;

    protected string $view = 'filament.resources.attendance-session-resource.pages.mark-attendance';

    protected static ?string $title = 'Mark Attendance';

    public AttendanceSession $record;

    #[Locked]
    public array $teacherAttendance = [];

    public array $studentAttendance = [];

    #[Locked]
    public bool $sessionCancelled = false;

    public function mount(int|string $record): void
    {
        $this->record = AttendanceSession::query()
            ->with(['class', 'academicYear'])
            ->findOrFail($record);

        if (! $this->record->canBeMarked()) {
            Notification::make()
                ->title('Session cannot be marked')
                ->body('This session is locked or not in Open status.')
                ->danger()
                ->send();

            $this->redirect(route('filament.admin.resources.attendance-sessions.index'));
            return;
        }

        $this->loadTeacherAttendance();
        $this->loadStudentAttendance();
    }

    protected function loadTeacherAttendance(): void
    {
        $activeYear = $this->record->academicYear;
        $classId = $this->record->class_id;

        $assignments = TeacherAssignment::query()
            ->with(['teacher', 'subject'])
            ->where('class_id', $classId)
            ->where('academic_year_id', $activeYear->id)
            ->active()
            ->get();

        foreach ($assignments as $assignment) {
            $this->teacherAttendance[$assignment->teacher_id] = [
                'teacher_id' => $assignment->teacher_id,
                'teacher_name' => $assignment->teacher->full_name,
                'subject_name' => $assignment->subject->name ?? '',
                'attendance_status' => null,
                'session_outcome' => 'Normal',
                'substitute_teacher_name' => null,
                'notes' => null,
            ];
        }
    }

    protected function loadStudentAttendance(): void
    {
        $enrollments = StudentEnrollment::query()
            ->with(['member'])
            ->where('class_id', $this->record->class_id)
            ->where('academic_year_id', $this->record->academic_year_id)
            ->where('status', 'Enrolled')
            ->get();

        foreach ($enrollments as $enrollment) {
            $this->studentAttendance[$enrollment->member_id] = [
                'student_id' => $enrollment->member_id,
                'student_name' => $enrollment->member->full_name,
                'member_code' => $enrollment->member->member_code,
                'status' => null,
            ];
        }
    }

    public function saveTeacherAttendance(): void
    {
        if (! Auth::user()?->hasRole(['education_monitor', 'admin', 'superadmin'])) {
            Notification::make()
                ->title('Access denied')
                ->danger()
                ->send();
            return;
        }

        DB::transaction(function (): void {
            foreach ($this->teacherAttendance as $teacherId => $data) {
                TeacherAttendance::updateOrCreate(
                    ['teacher_id' => $teacherId, 'session_id' => $this->record->getKey()],
                    [
                        'attendance_status' => $data['attendance_status'],
                        'session_outcome' => $data['session_outcome'],
                        'substitute_teacher_name' => $data['substitute_teacher_name'],
                        'notes' => $data['notes'],
                        'marked_by' => Auth::id(),
                        'marked_at' => now(),
                    ]
                );
            }
        });

        Notification::make()->title('Teacher attendance saved')->success()->send();
    }

    public function saveStudentAttendance(): void
    {
        if (! Auth::user()?->hasRole(['education_monitor', 'admin', 'superadmin'])) {
            Notification::make()
                ->title('Access denied')
                ->danger()
                ->send();
            return;
        }

        DB::transaction(function (): void {
            foreach ($this->studentAttendance as $studentId => $data) {
                StudentAttendance::updateOrCreate(
                    ['student_id' => $studentId, 'session_id' => $this->record->getKey()],
                    [
                        'status' => $data['status'],
                        'marked_by' => Auth::id(),
                        'marked_at' => now(),
                    ]
                );
            }
        });

        Notification::make()->title('Student attendance saved')->success()->send();
    }

    public function markAllPresent(): void
    {
        foreach ($this->studentAttendance as $studentId => &$data) {
            $data['status'] = 'Present';
        }
    }

    public function markAllAbsent(): void
    {
        foreach ($this->studentAttendance as $studentId => &$data) {
            $data['status'] = 'Absent';
        }
    }

    #[Computed]
    public function attendanceSummary(): string
    {
        $counts = array_count_values(array_column($this->studentAttendance, 'status'));
        $present = $counts['Present'] ?? 0;
        $absent = $counts['Absent'] ?? 0;
        $excused = $counts['Excused'] ?? 0;
        $late = $counts['Late'] ?? 0;
        $permission = $counts['Permission'] ?? 0;

        return "{$present} Present / {$absent} Absent / {$excused} Excused / {$late} Late / {$permission} Permission";
    }

    #[Computed]
    public function isSessionCancelled(): bool
    {
        foreach ($this->teacherAttendance as $data) {
            if (($data['attendance_status'] ?? null) === 'Absent' && ($data['session_outcome'] ?? 'Normal') === 'Cancelled') {
                return true;
            }
        }
        return false;
    }

    public function getTitle(): string
    {
        return "Mark Attendance - {$this->record->class->name} - " . app(\App\Helpers\EthiopianDateHelper::class)->toString($this->record->session_date);
    }
}
