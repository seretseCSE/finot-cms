<?php

namespace App\Filament\Resources\AttendanceSessionResource\Pages;

use App\Filament\Resources\AttendanceSessionResource;
use App\Helpers\EthiopianDateHelper;
use App\Models\AttendanceSession;
use App\Models\StudentAttendance;
use App\Models\StudentEnrollment;
use App\Models\TeacherAttendance;
use App\Models\TeacherAssignment;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

class MarkAttendance extends Page
{
    protected static string $resource = AttendanceSessionResource::class;

    protected string $view = 'filament.resources.attendance-session-resource.pages.mark-attendance';

    protected static ?string $title = 'Mark Attendance';

    public AttendanceSession $record;

    #[Locked]
    public string $activeTab = 'students';

    public array $teacherAttendance = [];

    public array $studentAttendance = [];

    public array $selectedStudents = [];

    public array $selectedTeachers = [];

    #[Locked]
    public bool $sessionCancelled = false;

    public ?int $selectedClassId = null;

    public function mount(AttendanceSession $record): void
    {
        $this->record = $record;

        if (! $this->record->canBeMarked()) {
            Notification::make()
                ->title('Session cannot be marked')
                ->body('This session is locked or not in Open status.')
                ->danger()
                ->send();

            $this->redirect(AttendanceSessionResource::getUrl('index'));

            return;
        }

        $this->loadTeacherAttendance();
        $this->loadStudentAttendance();
    }

    protected function loadTeacherAttendance(): void
    {
        $sessionAssignmentIds = $this->record->teacherAssignmentsPivot()
            ->pluck('teacher_assignments.id')
            ->toArray();

        if (empty($sessionAssignmentIds)) {
            return;
        }

        $existing = TeacherAttendance::query()
            ->where('session_id', $this->record->getKey())
            ->get()
            ->keyBy('teacher_assignment_id')
            ->toArray();

        $assignments = TeacherAssignment::query()
            ->with(['teacher', 'subject', 'class'])
            ->whereIn('id', $sessionAssignmentIds)
            ->active()
            ->get();

        foreach ($assignments as $assignment) {
            $existingData = $existing[$assignment->id] ?? null;

            $this->teacherAttendance[$assignment->id] = [
                'assignment_id' => $assignment->id,
                'teacher_id' => $assignment->teacher_id,
                'teacher_name' => $assignment->teacher->full_name,
                'class_name' => $assignment->class->name ?? '',
                'subject_name' => $assignment->subject->name ?? '',
                'attendance_status' => $existingData['attendance_status'] ?? null,
                'notes' => $existingData['notes'] ?? null,
            ];
        }
    }

    protected function loadStudentAttendance(): void
    {
        $classIds = $this->record->classes()->pluck('class_id')->toArray();

        if (empty($classIds)) {
            return;
        }

        $existing = StudentAttendance::query()
            ->where('session_id', $this->record->getKey())
            ->get()
            ->keyBy('student_id')
            ->toArray();

        $enrollments = StudentEnrollment::query()
            ->with(['member'])
            ->whereIn('class_id', $classIds)
            ->where('academic_year_id', $this->record->academic_year_id)
            ->where('status', 'Enrolled')
            ->get();

        foreach ($enrollments as $enrollment) {
            if (! $enrollment->member) {
                continue;
            }

            $existingData = $existing[$enrollment->member_id] ?? null;

            $this->studentAttendance[$enrollment->member_id] = [
                'student_id' => $enrollment->member_id,
                'student_name' => $enrollment->member->full_name,
                'member_code' => $enrollment->member->member_code,
                'class_id' => $enrollment->class_id,
                'class_name' => $enrollment->class->name ?? '',
                'status' => $existingData['status'] ?? null,
            ];
        }
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, ['students', 'teachers'])) {
            return;
        }

        $this->activeTab = $tab;
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
            foreach ($this->teacherAttendance as $assignmentId => $data) {
                TeacherAttendance::updateOrCreate(
                    ['teacher_assignment_id' => $assignmentId, 'session_id' => $this->record->getKey()],
                    [
                        'attendance_status' => $data['attendance_status'],
                        'notes' => $data['notes'] ?? null,
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

    public function applyBulkStatus(string $status): void
    {
        if ($this->activeTab === 'students') {
            if (empty($this->selectedStudents)) {
                foreach ($this->studentAttendance as $studentId => $data) {
                    $this->studentAttendance[$studentId]['status'] = $status;
                }
            } else {
                foreach ($this->selectedStudents as $studentId) {
                    if (array_key_exists($studentId, $this->studentAttendance)) {
                        $this->studentAttendance[$studentId]['status'] = $status;
                    }
                }
            }
        } elseif ($this->activeTab === 'teachers') {
            if (empty($this->selectedTeachers)) {
                foreach ($this->teacherAttendance as $assignmentId => $data) {
                    $this->teacherAttendance[$assignmentId]['attendance_status'] = $status;
                }
            } else {
                foreach ($this->selectedTeachers as $assignmentId) {
                    if (array_key_exists($assignmentId, $this->teacherAttendance)) {
                        $this->teacherAttendance[$assignmentId]['attendance_status'] = $status;
                    }
                }
            }
        }
    }

    public function toggleSelectAll(): void
    {
        if ($this->activeTab === 'students') {
            $ids = array_keys($this->filteredStudentAttendance);

            if (empty($ids)) {
                return;
            }

            if (count(array_intersect($this->selectedStudents, $ids)) === count($ids)) {
                $this->selectedStudents = array_values(array_diff($this->selectedStudents, $ids));
            } else {
                $this->selectedStudents = array_values(array_unique(array_merge($this->selectedStudents, $ids)));
            }
        } elseif ($this->activeTab === 'teachers') {
            $ids = array_keys($this->teacherAttendance);

            if (empty($ids)) {
                return;
            }

            if (count(array_intersect($this->selectedTeachers, $ids)) === count($ids)) {
                $this->selectedTeachers = array_values(array_diff($this->selectedTeachers, $ids));
            } else {
                $this->selectedTeachers = array_values(array_unique(array_merge($this->selectedTeachers, $ids)));
            }
        }
    }

    #[Computed]
    public function availableClasses(): array
    {
        return $this->record->classes()->pluck('classes.name', 'classes.id')->toArray();
    }

    #[Computed]
    public function filteredStudentAttendance(): array
    {
        if (! $this->selectedClassId) {
            return $this->studentAttendance;
        }

        return array_filter(
            $this->studentAttendance,
            fn ($student) => ($student['class_id'] ?? null) == $this->selectedClassId
        );
    }

    #[Computed]
    public function attendanceSummary(): string
    {
        $counts = array_count_values(array_filter(array_column($this->filteredStudentAttendance, 'status')));
        $present = $counts['Present'] ?? 0;
        $absent = $counts['Absent'] ?? 0;
        $excused = $counts['Excused'] ?? 0;
        $late = $counts['Late'] ?? 0;
        $permission = $counts['Permission'] ?? 0;

        return "{$present} Present / {$absent} Absent / {$excused} Excused / {$late} Late / {$permission} Permission";
    }

    #[Computed]
    public function teacherAttendanceSummary(): string
    {
        $counts = array_count_values(array_filter(array_column($this->teacherAttendance, 'attendance_status')));
        $present = $counts['Present'] ?? 0;
        $absent = $counts['Absent'] ?? 0;
        $late = $counts['Late'] ?? 0;
        $permission = $counts['Permission'] ?? 0;

        return "{$present} Present / {$absent} Absent / {$late} Late / {$permission} Permission";
    }

    #[Computed]
    public function isSessionCancelled(): bool
    {
        return false;
    }

    public function getTitle(): string
    {
        $date = app(EthiopianDateHelper::class)->toString($this->record->session_date);

        return "Mark Attendance — {$date}";
    }
}
