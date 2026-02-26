<?php

namespace App\Filament\Pages\Education;

use App\Models\Member;
use App\Models\MemberEducationHistory;
use App\Models\ClassModel;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\TestResult;
use Filament\Pages\Page;
use Filament\Forms;
use Filament\Schemas\Schema;

class StudentProgressReport extends Page
{
    public static function getNavigationIcon(): ?string { return 'heroicono-academic-cap'; }

    protected string $view = 'filament.pages.education.student-progress-report';

    public static function getNavigationGroup(): ?string { return 'Education'; }

    public static function getNavigationSort(): ?int { return 2; }

    public ?array $filters = [];

    public function mount(): void
    {
        $this->form->fill([
            'academic_year_id' => AcademicYear::where('is_active', true)->first()?->id,
            'class_id' => null,
            'member_id' => null,
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
                        
                        return ClassModel::where('academic_year_id', $yearId)
                            ->with('subject')
                            ->get()
                            ->mapWithKeys(fn ($class) => [$class->id => "{$class->subject->name} - {$class->name}"]);
                    })
                    ->reactive()
                    ->afterStateUpdated(fn ($state, callable $set) => $set('member_id', null)),

                Forms\Components\Select::make('member_id')
                    ->label('Student')
                    ->options(function (callable $get) {
                        $classId = $get('class_id');
                        if (!$classId) return [];
                        
                        return Member::whereHas('educationHistory', function ($query) use ($classId) {
                            $query->where('class_id', $classId);
                        })->pluck('full_name', 'id');
                    })
                    ->required(),
            ])
            ->columns(3);
    }

    public function getProgressData(): array
    {
        $filters = $this->form->getState();
        
        if (!$filters['member_id']) {
            return [];
        }

        $member = Member::with(['educationHistory.class.subject', 'educationHistory.academicYear'])
            ->findOrFail($filters['member_id']);

        // Get current education record
        $currentEducation = $member->educationHistory()
            ->where('academic_year_id', $filters['academic_year_id'])
            ->with(['class.subject'])
            ->first();

        if (!$currentEducation) {
            return [];
        }

        // Attendance data
        $attendanceData = Attendance::where('member_id', $member->id)
            ->whereHas('session', function ($query) use ($filters) {
                $query->where('academic_year_id', $filters['academic_year_id']);
                if ($filters['class_id']) {
                    $query->where('class_id', $filters['class_id']);
                }
            })
            ->with('session')
            ->get();

        // Test results data
        $testResults = TestResult::where('member_id', $member->id)
            ->whereHas('test', function ($query) use ($filters) {
                $query->where('academic_year_id', $filters['academic_year_id']);
                if ($filters['class_id']) {
                    $query->where('class_id', $filters['class_id']);
                }
            })
            ->with('test')
            ->orderBy('created_at', 'desc')
            ->get();

        // Calculate statistics
        $totalSessions = $attendanceData->count();
        $presentCount = $attendanceData->where('status', 'Present')->count();
        $attendanceRate = $totalSessions > 0 ? round(($presentCount / $totalSessions) * 100, 2) : 0;

        $testScores = $testResults->pluck('score');
        $averageScore = $testScores->count() > 0 ? round($testScores->avg(), 2) : 0;
        $highestScore = $testScores->max() ?? 0;
        $lowestScore = $testScores->min() ?? 0;

        // Progress trend
        $monthlyProgress = $testResults->groupBy(function ($result) {
            return \Carbon\Carbon::parse($result->created_at)->format('Y-m');
        })->map(function ($monthResults) {
            return [
                'month' => \Carbon\Carbon::parse($monthResults->first()->created_at)->format('M Y'),
                'average_score' => round($monthResults->pluck('score')->avg(), 2),
                'test_count' => $monthResults->count(),
            ];
        })->sortBy('month');

        return [
            'student' => $member,
            'current_education' => $currentEducation,
            'attendance' => [
                'total_sessions' => $totalSessions,
                'present' => $presentCount,
                'rate' => $attendanceRate,
                'details' => $attendanceData->groupBy('status'),
            ],
            'tests' => [
                'total_tests' => $testResults->count(),
                'average_score' => $averageScore,
                'highest_score' => $highestScore,
                'lowest_score' => $lowestScore,
                'results' => $testResults,
            ],
            'progress_trend' => $monthlyProgress,
        ];
    }

    public function generateReportCard()
    {
        $data = $this->getProgressData();
        
        // Implementation for PDF report card generation
        return response()->json($data);
    }
}

