<?php

namespace App\Filament\Pages\Attendance;

use App\Models\AcademicYear;
use App\Models\AttendanceSession;
use App\Models\ClassModel;
use App\Models\TeacherAssignment;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreateAttendanceSessionPage extends Page
{
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-plus-circle';
    }

    public static function getNavigationLabel(): string
    {
        return 'Create Session';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Attendance';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    protected string $view = 'filament.pages.attendance.create-attendance-session';

    protected static ?string $title = 'Create Attendance Session';

    public ?string $sessionDate = null;

    public bool $todaySessionExists = false;

    public array $classEntries = [];

    public array $allSessions = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(['education_head', 'admin', 'superadmin']);
    }

    public function mount(): void
    {
        $this->sessionDate = now()->format('Y-m-d');
        $this->classEntries = [
            ['classId' => null, 'assignments' => [], 'selectedAssignments' => []],
        ];
        $this->checkTodaySession();
        $this->loadAllSessions();
    }

    public function updatedSessionDate(): void
    {
        $this->checkTodaySession();
    }

    private function checkTodaySession(): void
    {
        $activeYear = $this->getActiveYear();

        if (! $activeYear || ! $this->sessionDate) {
            $this->todaySessionExists = false;

            return;
        }

        $this->todaySessionExists = AttendanceSession::query()
            ->where('session_date', $this->sessionDate)
            ->where('academic_year_id', $activeYear->id)
            ->exists();
    }

    private function getActiveYear(): ?AcademicYear
    {
        return AcademicYear::query()
            ->where('status', 'Active')
            ->where('phase', 'current')
            ->first()
            ?? AcademicYear::query()->where('status', 'Active')->orderBy('start_date', 'desc')->first();
    }

    public function addClassEntry(): void
    {
        $this->classEntries[] = ['classId' => null, 'assignments' => [], 'selectedAssignments' => []];
    }

    public function removeClassEntry(int $index): void
    {
        if (count($this->classEntries) <= 1) {
            return;
        }
        unset($this->classEntries[$index]);
        $this->classEntries = array_values($this->classEntries);
    }

    public function onClassSelected(int $index, $value = null): void
    {
        $value = $value ? (int) $value : null;
        $this->classEntries[$index]['classId'] = $value;
        $this->classEntries[$index]['assignments'] = [];
        $this->classEntries[$index]['selectedAssignments'] = [];

        if (! $value) {
            return;
        }

        $activeYear = $this->getActiveYear();

        if (! $activeYear) {
            return;
        }

        $this->classEntries[$index]['assignments'] = TeacherAssignment::query()
            ->with(['teacher', 'subject'])
            ->where('class_id', $value)
            ->where('academic_year_id', $activeYear->id)
            ->active()
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'teacher_name' => $a->teacher->full_name,
                'subject_name' => $a->subject->name ?? 'N/A',
            ])
            ->toArray();
    }

    public function createSession(): void
    {
        if ($this->todaySessionExists) {
            Notification::make()
                ->title('Session already exists')
                ->body("A session already exists for {$this->sessionDate}. Only one session per day is allowed.")
                ->warning()
                ->send();

            return;
        }

        $activeYear = $this->getActiveYear();

        if (! $activeYear) {
            Notification::make()
                ->title('No active academic year found')
                ->danger()
                ->send();

            return;
        }

        $validEntries = array_filter($this->classEntries, fn ($e) => ! empty($e['classId']));

        if (empty($validEntries)) {
            Notification::make()
                ->title('No classes selected')
                ->body('Select at least one class to create a session.')
                ->warning()
                ->send();

            return;
        }

        DB::transaction(function () use ($activeYear, $validEntries): void {
            $session = AttendanceSession::create([
                'session_date' => $this->sessionDate,
                'academic_year_id' => $activeYear->id,
                'status' => 'Open',
                'created_by' => Auth::id(),
            ]);

            $classIds = [];
            $assignmentIds = [];

            foreach ($validEntries as $entry) {
                $classIds[] = $entry['classId'];

                if (! empty($entry['selectedAssignments'])) {
                    foreach ($entry['selectedAssignments'] as $assId) {
                        $assignmentIds[] = $assId;
                    }
                }
            }

            $session->classes()->sync($classIds);
            $session->teacherAssignmentsPivot()->sync($assignmentIds);
        });

        $classCount = count($validEntries);
        $assignmentCount = collect($validEntries)->sum(fn ($e) => count($e['selectedAssignments'] ?? []));

        Notification::make()
            ->title('Session created')
            ->body("Session for {$this->sessionDate} created with {$classCount} class(es) and {$assignmentCount} teacher assignment(s).")
            ->success()
            ->send();

        $this->sessionDate = now()->format('Y-m-d');
        $this->classEntries = [
            ['classId' => null, 'assignments' => [], 'selectedAssignments' => []],
        ];
        $this->checkTodaySession();
        $this->loadAllSessions();
    }

    public function loadAllSessions(): void
    {
        $this->allSessions = AttendanceSession::query()
            ->with(['classes', 'teacherAssignmentsPivot'])
            ->latest('session_date')
            ->limit(50)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'date' => $s->session_date->format('M j, Y'),
                'date_raw' => $s->session_date->format('Y-m-d'),
                'class_count' => $s->classes->count(),
                'teacher_count' => $s->teacherAssignmentsPivot->count(),
                'status' => $s->status,
                'created_at' => $s->created_at->format('M j, Y g:i A'),
            ])
            ->toArray();
    }

    public function deleteSession(int $id): void
    {
        $session = AttendanceSession::find($id);

        if (! $session) {
            return;
        }

        if (! Auth::user()?->hasRole(['admin', 'superadmin'])) {
            Notification::make()
                ->title('Access denied')
                ->danger()
                ->send();

            return;
        }

        $session->delete();

        Notification::make()
            ->title('Session deleted')
            ->success()
            ->send();

        $this->loadAllSessions();
        $this->checkTodaySession();
    }

    public function getClassesProperty()
    {
        return ClassModel::query()->active()->orderBy('name')->pluck('name', 'id');
    }
}
