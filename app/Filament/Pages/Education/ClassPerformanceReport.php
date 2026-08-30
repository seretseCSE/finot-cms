<?php

namespace App\Filament\Pages\Education;

use App\Models\ClassModel;
use Filament\Schemas\Schema;
use App\Models\AcademicYear;
use App\Models\Member;
use App\Models\StudentAttendance;
use Filament\Pages\Page;
use Filament\Forms;
use Illuminate\Support\Facades\Auth;

class ClassPerformanceReport extends Page
{
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-presentation-chart-bar';
    }

    protected string $view = 'filament.pages.education.class-performance-report';

    public static function getNavigationGroup(): ?string
    {
        return 'Attendance & Results';
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }

    public ?int $academic_year_id = null;

    public ?int $class_id = null;

    public ?array $reportData = null;

    public static function canAccess(array $parameters = []): bool
    {
        return \App\Support\RoleGate::can('page.report.class-performance');
    }

    public function mount(): void
    {
        $this->academic_year_id = AcademicYear::where('status', 'Active')->first()?->id;
        $this->class_id = null;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
                Forms\Components\Select::make('academic_year_id')
                    ->label('Academic Year')
                    ->options(fn () => AcademicYear::query()->orderByDesc('start_date')->pluck('name', 'id')->all())
                    ->live()
                    ->afterStateUpdated(fn ($state, callable $set) => $set('class_id', null)),

                Forms\Components\Select::make('class_id')
                    ->label('Class')
                    ->options(fn () => ClassModel::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->live(),
            ])
            ->columns(2);
    }

    public function generateClassReport(): void
    {
        if (! $this->class_id || ! $this->academic_year_id) {
            $this->reportData = null;
            return;
        }

        $class = ClassModel::find($this->class_id);

        if (! $class) {
            $this->reportData = null;
            return;
        }

        $students = Member::whereHas('studentEnrollments', function ($query) {
            $query->where('class_id', $this->class_id)
                  ->where('academic_year_id', $this->academic_year_id)
                  ->where('status', 'Enrolled');
        })->get();

        $studentAttendance = [];
        foreach ($students as $student) {
            $attendance = StudentAttendance::where('student_id', $student->id)
                ->whereHas('session', function ($query) {
                    $query->whereHas('classes', fn ($q) => $q->where('class_id', $this->class_id))
                          ->where('academic_year_id', $this->academic_year_id);
                })
                ->get();

            $totalSessions = $attendance->count();
            $presentCount = $attendance->where('status', 'Present')->count();
            $attendanceRate = $totalSessions > 0 ? round(($presentCount / $totalSessions) * 100, 2) : 0;

            $studentAttendance[$student->id] = [
                'total_sessions' => $totalSessions,
                'present' => $presentCount,
                'attendance_rate' => $attendanceRate,
            ];
        }

        $studentTests = [];
        foreach ($students as $student) {
            $studentTests[$student->id] = [
                'total_tests' => 0,
                'average_score' => 0,
                'highest_score' => 0,
                'lowest_score' => 0,
                'test_results' => [],
            ];
        }

        $allAttendanceRates = collect($studentAttendance)->pluck('attendance_rate');
        $allTestScores = collect($studentTests)->pluck('average_score');

        $classStats = [
            'total_students' => $students->count(),
            'average_attendance_rate' => $allAttendanceRates->count() > 0 ? round($allAttendanceRates->avg(), 2) : 0,
            'average_test_score' => $allTestScores->count() > 0 ? round($allTestScores->avg(), 2) : 0,
            'highest_attendance' => $allAttendanceRates->max() ?? 0,
            'lowest_attendance' => $allAttendanceRates->min() ?? 0,
            'highest_test_score' => $allTestScores->max() ?? 0,
            'lowest_test_score' => $allTestScores->min() ?? 0,
        ];

        $attendanceDistribution = [
            'excellent' => collect($studentAttendance)->where('attendance_rate', '>=', 90)->count(),
            'good' => collect($studentAttendance)->where('attendance_rate', '>=', 75)->where('attendance_rate', '<', 90)->count(),
            'fair' => collect($studentAttendance)->where('attendance_rate', '>=', 60)->where('attendance_rate', '<', 75)->count(),
            'poor' => collect($studentAttendance)->where('attendance_rate', '<', 60)->count(),
        ];

        $testDistribution = [
            'excellent' => collect($studentTests)->where('average_score', '>=', 90)->count(),
            'good' => collect($studentTests)->where('average_score', '>=', 80)->where('average_score', '<', 90)->count(),
            'fair' => collect($studentTests)->where('average_score', '>=', 70)->where('average_score', '<', 80)->count(),
            'poor' => collect($studentTests)->where('average_score', '<', 70)->count(),
        ];

        $this->reportData = [
            'class' => $class->only(['id', 'name']),
            'students' => $students->map(fn ($student) => [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'phone' => $student->phone,
            ])->toArray(),
            'student_attendance' => $studentAttendance,
            'student_tests' => $studentTests,
            'class_stats' => $classStats,
            'attendance_distribution' => $attendanceDistribution,
            'test_distribution' => $testDistribution,
        ];
    }

    public function exportClassReport()
    {
        if (! $this->reportData) {
            $this->generateClassReport();
        }

        return response()->json($this->reportData);
    }
}
