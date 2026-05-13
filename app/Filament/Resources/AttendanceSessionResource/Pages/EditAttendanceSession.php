<?php

namespace App\Filament\Resources\AttendanceSessionResource\Pages;

use App\Filament\Resources\AttendanceSessionResource;
use App\Models\AcademicYear;
use App\Models\AttendanceSession;
use App\Models\ClassModel;
use App\Models\TeacherAssignment;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;

class EditAttendanceSession extends Page
{
    protected static string $resource = AttendanceSessionResource::class;

    protected string $view = 'filament.resources.attendance-session-resource.pages.edit-attendance-session';

    public AttendanceSession $record;

    public ?string $sessionDate = null;

    public array $selectedClassIds = [];

    public array $selectedAssignmentIds = [];

    public array $availableAssignments = [];

    public array $classAssignmentsMap = [];

    public function mount(AttendanceSession $record): void
    {
        $this->record = $record;

        $this->sessionDate = $record->session_date->format('Y-m-d');
        $this->selectedClassIds = $record->classes()->pluck('class_id')->toArray();
        $this->selectedAssignmentIds = $record->teacherAssignmentsPivot()->pluck('teacher_assignments.id')->toArray();

        $this->loadAssignments();
    }

    public function updatedSessionDate(): void
    {
        // No-op for edit; date change handled via validation
    }

    public function updatedSelectedClassIds(): void
    {
        $activeYear = $this->getActiveYear();

        if (! $activeYear) {
            $this->availableAssignments = [];
            $this->classAssignmentsMap = [];
            $this->selectedAssignmentIds = [];

            return;
        }

        $this->loadAssignments();
    }

    private function loadAssignments(): void
    {
        $activeYear = $this->getActiveYear();

        if (! $activeYear || empty($this->selectedClassIds)) {
            $this->availableAssignments = [];
            $this->classAssignmentsMap = [];
            return;
        }

        $allAssignments = TeacherAssignment::query()
            ->with(['teacher', 'subject', 'class'])
            ->whereIn('class_id', $this->selectedClassIds)
            ->where('academic_year_id', $activeYear->id)
            ->active()
            ->get();

        $this->availableAssignments = $allAssignments
            ->mapWithKeys(fn ($a) => [
                $a->id => [
                    'id' => $a->id,
                    'teacher_name' => $a->teacher->full_name,
                    'subject_name' => $a->subject->name ?? 'N/A',
                    'class_id' => $a->class_id,
                    'class_name' => $a->class->name,
                ],
            ])
            ->toArray();

        $this->classAssignmentsMap = $allAssignments
            ->groupBy('class_id')
            ->map(fn ($group) => $group->pluck('id')->toArray())
            ->toArray();

        $assignmentIdsForSelectedClasses = $allAssignments->pluck('id')->toArray();

        $this->selectedAssignmentIds = array_values(array_intersect(
            $this->selectedAssignmentIds,
            $assignmentIdsForSelectedClasses
        ));
    }

    private function getActiveYear(): ?AcademicYear
    {
        return AcademicYear::query()
            ->where('status', 'Active')
            ->where('phase', 'current')
            ->first()
            ?? AcademicYear::query()->where('status', 'Active')->orderBy('start_date', 'desc')->first();
    }

    public function toggleClassAssignments(int $classId): void
    {
        if (! isset($this->classAssignmentsMap[$classId])) {
            return;
        }

        $assignmentIds = $this->classAssignmentsMap[$classId];
        $allSelected = count(array_intersect($assignmentIds, $this->selectedAssignmentIds)) === count($assignmentIds);

        if ($allSelected) {
            $this->selectedAssignmentIds = array_values(array_diff($this->selectedAssignmentIds, $assignmentIds));
        } else {
            foreach ($assignmentIds as $aid) {
                if (! in_array($aid, $this->selectedAssignmentIds)) {
                    $this->selectedAssignmentIds[] = $aid;
                }
            }
        }
    }

    public function selectAllAssignments(): void
    {
        $this->selectedAssignmentIds = array_map('intval', array_keys($this->availableAssignments));
    }

    public function deselectAllAssignments(): void
    {
        $this->selectedAssignmentIds = [];
    }

    public function removeClassTag(int $classId): void
    {
        $this->selectedClassIds = array_values(array_diff($this->selectedClassIds, [$classId]));
        $this->updatedSelectedClassIds();
    }

    public function updateSession(): void
    {
        $activeYear = $this->getActiveYear();

        if (! $activeYear) {
            Notification::make()
                ->title('No active academic year found')
                ->danger()
                ->send();

            return;
        }

        $validClassIds = array_filter($this->selectedClassIds);

        if (empty($validClassIds)) {
            Notification::make()
                ->title('No classes selected')
                ->body('Select at least one class.')
                ->warning()
                ->send();

            return;
        }

        $validAssignmentIds = array_filter($this->selectedAssignmentIds);

        DB::transaction(function () use ($activeYear, $validClassIds, $validAssignmentIds): void {
            $this->record->update([
                'session_date' => $this->sessionDate,
                'academic_year_id' => $activeYear->id,
            ]);

            $this->record->classes()->sync($validClassIds);
            $this->record->teacherAssignmentsPivot()->sync($validAssignmentIds);
        });

        $classCount = count($validClassIds);
        $assignmentCount = count($validAssignmentIds);

        Notification::make()
            ->title('Session updated')
            ->body("Session updated with {$classCount} class(es) and {$assignmentCount} teacher assignment(s).")
            ->success()
            ->send();

        $this->redirect(AttendanceSessionResource::getUrl('index'));
    }

    public function getClassesProperty()
    {
        return ClassModel::query()->active()->orderBy('name')->pluck('name', 'id');
    }

    public function getSelectedClassesProperty()
    {
        return ClassModel::query()
            ->whereIn('id', $this->selectedClassIds)
            ->pluck('name', 'id');
    }
}
