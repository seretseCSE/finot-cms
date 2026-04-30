<?php

namespace App\Filament\Pages;

use App\Models\StudentAttendance;
use App\Models\StudentEnrollment;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EducationReport extends Page
{
    protected static ?string $title = 'Education Report';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-chart-bar';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Reports';
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }

    public function getView(): string
    {
        return 'filament.pages.education-report';
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(['education_head', 'education_monitor', 'admin', 'superadmin']);
    }

    public ?string $report_type = 'enrollment';

    public ?string $date_from = null;

    public ?string $date_to = null;

    public ?int $teacher_id = null;

    public ?int $class_id = null;

    public function mount(): void
    {
        $this->date_from = now()->subMonth()->format('Y-m-d');
        $this->date_to = now()->format('Y-m-d');
    }

    public function getEnrollmentData(): array
    {
        $query = StudentEnrollment::query()
            ->when($this->date_from, fn ($q) => $q->whereDate('enrolled_date', '>=', $this->date_from))
            ->when($this->date_to, fn ($q) => $q->whereDate('enrolled_date', '<=', $this->date_to));

        return [
            'total_enrollments' => $query->count(),
            'active_enrollments' => (clone $query)->where('status', 'Enrolled')->count(),
            'withdrawn' => (clone $query)->where('status', 'Withdrawn')->count(),
            'completed' => (clone $query)->where('status', 'Completed')->count(),
            'by_class' => (clone $query)
                ->where('status', 'Enrolled')
                ->select('class_id', DB::raw('count(*) as count'))
                ->with('class')
                ->groupBy('class_id')
                ->get()
                ->mapWithKeys(fn ($item) => [$item->class->name ?? 'Unknown' => $item->count])
                ->toArray(),
        ];
    }

    public function getTeacherAttendanceData(): array
    {
        $query = TeacherAttendance::query()
            ->when($this->teacher_id, fn ($q) => $q->where('teacher_id', $this->teacher_id))
            ->when($this->date_from, fn ($q) => $q->whereHas('session', fn ($sq) => $sq->whereDate('session_date', '>=', $this->date_from)))
            ->when($this->date_to, fn ($q) => $q->whereHas('session', fn ($sq) => $sq->whereDate('session_date', '<=', $this->date_to)));

        $total = $query->count();

        return [
            'total_records' => $total,
            'present' => (clone $query)->where('attendance_status', 'Present')->count(),
            'absent' => (clone $query)->where('attendance_status', 'Absent')->count(),
            'late' => (clone $query)->where('attendance_status', 'Late')->count(),
            'permission' => (clone $query)->where('attendance_status', 'Permission')->count(),
            'attendance_rate' => $total > 0
                ? round(((clone $query)->where('attendance_status', 'Present')->count() / $total) * 100, 2)
                : 0,
            'by_teacher' => Teacher::query()
                ->where('status', 'Active')
                ->withCount(['teacherAttendance as present_count' => fn ($q) => $q->where('attendance_status', 'Present')])
                ->withCount(['teacherAttendance as total_count'])
                ->get()
                ->map(fn ($t) => [
                    'name' => $t->full_name,
                    'rate' => $t->total_count > 0 ? round(($t->present_count / $t->total_count) * 100, 2) : 0,
                ])
                ->toArray(),
        ];
    }

    public function getStudentAttendanceData(): array
    {
        $query = StudentAttendance::query()
            ->when($this->date_from, fn ($q) => $q->whereHas('session', fn ($sq) => $sq->whereDate('session_date', '>=', $this->date_from)))
            ->when($this->date_to, fn ($q) => $q->whereHas('session', fn ($sq) => $sq->whereDate('session_date', '<=', $this->date_to)));

        $total = $query->count();

        return [
            'total_records' => $total,
            'present' => (clone $query)->where('status', 'Present')->count(),
            'absent' => (clone $query)->where('status', 'Absent')->count(),
            'excused' => (clone $query)->where('status', 'Excused')->count(),
            'late' => (clone $query)->where('status', 'Late')->count(),
            'attendance_rate' => $total > 0
                ? round(((clone $query)->where('status', 'Present')->count() / $total) * 100, 2)
                : 0,
        ];
    }

    public function getReportData(): array
    {
        return match ($this->report_type) {
            'enrollment' => $this->getEnrollmentData(),
            'teacher_attendance' => $this->getTeacherAttendanceData(),
            'student_attendance' => $this->getStudentAttendanceData(),
            default => [],
        };
    }

    public function updatedDateTo($value): void
    {
        if ($this->date_from && $value && $value < $this->date_from) {
            $this->date_to = $this->date_from;
            Notification::make()
                ->title('Invalid date range')
                ->body('The end date must be on or after the start date.')
                ->danger()
                ->send();
        }
    }

    public function updatedDateFrom($value): void
    {
        if ($value && $this->date_to && $this->date_to < $value) {
            $this->date_to = $value;
            Notification::make()
                ->title('Invalid date range')
                ->body('The end date must be on or after the start date.')
                ->danger()
                ->send();
        }
    }
}
