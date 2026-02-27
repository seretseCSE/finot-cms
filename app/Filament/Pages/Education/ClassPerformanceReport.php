<?php

namespace App\Filament\Pages\Education;

use App\Models\ClassModel;
use App\Models\AcademicYear;
use App\Models\Member;
use App\Models\Attendance;
use App\Models\TestResult;
use Filament\Pages\Page;
use Filament\Forms;
use Filament\Schemas\Schema;

class ClassPerformanceReport extends Page
{
    public static function getNavigationIcon(): ?string { return 'heroicon-o-presentation-chart-bar'; }

    protected string $view = 'filament.pages.education.class-performance-report';

    public static function getNavigationGroup(): ?string { return 'Education'; }

    public static function getNavigationSort(): ?int { return 3; }

    public ?array $filters = [];

    public function mount(): void
    {
        $this->form->fill([
            'academic_year_id' => AcademicYear::where('status', 'Active')->first()?->id,
            'class_id' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('academic_year_id')
                    ->label('Academic Year')
                    ->options(AcademicYear::pluck('name', 'id'))
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(fn ($state, callable $set) => $set('class_id', null)),

                Forms\Components\Select::make('class_id')
                    ->label('Class')
                    ->options(function (callable $get) {
                        $yearId = $get('academic_year_id');
                        if (!$yearId) return [];

                        return ClassModel::whereHas('attendanceSessions', function ($query) use ($yearId) {
                            $query->where('academic_year_id', $yearId);
                        })
                            ->get()
                            ->mapWithKeys(fn ($class) => [$class->id => $class->name]);
                    })
                    ->required(),
            ])
            ->columns(2);
    }

    public function getClassPerformanceData(): array
    {
        $filters = $this->form->getState();

        if (!$filters['class_id']) {
            return [];
        }

        $class = ClassModel::with(['subject', 'academicYear', 'teacher'])
            ->findOrFail($filters['class_id']);

        // Get all students in this class
        $students = Member::whereHas('educationHistory', function ($query) use ($filters) {
            $query->where('class_id', $filters['class_id'])
                  ->where('academic_year_id', $filters['academic_year_id']);
        })->get();

        // Calculate attendance data for each student
        $studentAttendance = [];
        foreach ($students as $student) {
            $attendance = Attendance::where('member_id', $student->id)
                ->whereHas('session', function ($query) use ($filters) {
                    $query->where('class_id', $filters['class_id'])
                          ->where('academic_year_id', $filters['academic_year_id']);
                })
                ->get();

            $totalSessions = $attendance->count();
            $presentCount = $attendance->where('status', 'Present')->count();
            $attendanceRate = $totalSessions > 0 ? round(($presentCount / $totalSessions) * 100, 2) : 0;

            $studentAttendance[$student->id] = [
                'student' => $student,
                'total_sessions' => $totalSessions,
                'present' => $presentCount,
                'attendance_rate' => $attendanceRate,
                'attendance_details' => $attendance->groupBy('status'),
            ];
        }

        // Calculate test performance
        $studentTests = [];
        foreach ($students as $student) {
            $testResults = TestResult::where('member_id', $student->id)
                ->whereHas('test', function ($query) use ($filters) {
                    $query->where('class_id', $filters['class_id'])
                          ->where('academic_year_id', $filters['academic_year_id']);
                })
                ->with('test')
                ->get();

            $scores = $testResults->pluck('score');
            $averageScore = $scores->count() > 0 ? round($scores->avg(), 2) : 0;
            $highestScore = $scores->max() ?? 0;
            $lowestScore = $scores->min() ?? 0;

            $studentTests[$student->id] = [
                'total_tests' => $testResults->count(),
                'average_score' => $averageScore,
                'highest_score' => $highestScore,
                'lowest_score' => $lowestScore,
                'test_results' => $testResults,
            ];
        }

        // Class-level statistics
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

        // Performance distribution
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

        return [
            'class' => $class,
            'students' => $students,
            'student_attendance' => $studentAttendance,
            'student_tests' => $studentTests,
            'class_stats' => $classStats,
            'attendance_distribution' => $attendanceDistribution,
            'test_distribution' => $testDistribution,
        ];
    }

    public function exportClassReport()
    {
        $data = $this->getClassPerformanceData();

        // Implementation for Excel export
        return response()->json($data);
    }
}

