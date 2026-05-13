<?php

namespace App\Filament\Pages\Education;

use App\Models\Member;
use Filament\Schemas\Schema;
use App\Models\ClassModel;
use App\Models\AcademicYear;
use App\Models\Contribution;
use App\Models\StudentAttendance;
use Filament\Pages\Page;
use Filament\Forms;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

class StudentProgressReport extends Page
{
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-academic-cap';
    }

    protected string $view = 'filament.pages.education.student-progress-report';

    public static function getNavigationGroup(): ?string
    {
        return 'Education';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    // FIX: removed unused $filters property; Filament stores form state in $data automatically.
    // Declaring it explicitly avoids "property not found" warnings.
    public ?array $data = [];

    // FIX: replaces calling getProgressData() twice per render — store result here after
    // the button is clicked so the blade reads a single cached value.
    public ?array $reportData = null;

    public static function canAccess(array $parameters = []): bool
    {
        return Auth::user()?->hasRole(['education_head', 'admin', 'superadmin']);
    }

    public function mount(): void
    {
        $this->form->fill([
            'academic_year_id' => AcademicYear::where('status', 'Active')->first()?->id,
            'class_id'         => null,
            'member_id'        => null,
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
                    ->live()   // FIX: ->reactive() is removed in Filament v5, use ->live()
                    ->afterStateUpdated(function (Set $set) {
                        // FIX: v5 afterStateUpdated uses typed Set/Get, not ($state, callable $set)
                        $set('class_id', null);
                        $set('member_id', null);
                        $this->reportData = null; // clear stale results on filter change
                    }),

                Forms\Components\Select::make('class_id')
                    ->label('Class')
                    ->options(function (Get $get) {
                        $yearId = $get('academic_year_id');
                        if (! $yearId) {
                            return [];
                        }

                        return ClassModel::active()
                            ->orderBy('name')
                            ->get()
                            ->pluck('name', 'id');
                    })
                    ->live()   // FIX: ->reactive() → ->live()
                    ->afterStateUpdated(function (Set $set) {
                        $set('member_id', null);
                        $this->reportData = null;
                    })
                    ->placeholder('Select a class'),

                Forms\Components\Select::make('member_id')
                    ->label('Student')
                    ->options(function (Get $get) {
                        $classId = $get('class_id');
                        $yearId  = $get('academic_year_id');
                        if (! $classId || ! $yearId) {
                            return [];
                        }

                        return Member::whereHas('studentEnrollments', function ($query) use ($classId, $yearId) {
                            $query->where('class_id', $classId)
                                  ->where('academic_year_id', $yearId);
                        })
                            ->get()
                            ->pluck('full_name', 'id');
                    })
                    ->required()
                    ->searchable()
                    ->placeholder('Select a student'),
            ])
            ->statePath('data')  // FIX: explicit statePath so $this->data is always the source of truth
            ->columns(3);
    }

    // FIX: now builds and caches into $this->reportData instead of being a computed getter.
    // The blade reads $this->reportData (a plain property), not a method called twice.
    public function generateProgressReport(): void
    {
        $this->form->validate();

        $filters = $this->data;

        if (empty($filters['member_id'])) {
            Notification::make()
                ->title('Please select a student first.')
                ->warning()
                ->send();
            return;
        }

        $member = Member::with(['studentEnrollments.class'])
            ->findOrFail($filters['member_id']);

        $currentEnrollment = $member->studentEnrollments()
            ->where('academic_year_id', $filters['academic_year_id'])
            ->when(! empty($filters['class_id']), fn ($q) => $q->where('class_id', $filters['class_id']))
            ->with('class')
            ->first();

        if (! $currentEnrollment) {
            Notification::make()
                ->title('No enrollment found for the selected filters.')
                ->warning()
                ->send();
            return;
        }

        $attendanceData = StudentAttendance::where('student_id', $member->id)
            ->whereHas('session', function ($query) use ($filters) {
                $query->where('academic_year_id', $filters['academic_year_id']);
                if (! empty($filters['class_id'])) {
                    $query->where('class_id', $filters['class_id']);
                }
            })
            ->with('session')
            ->get();

        $testResults = collect();

        $totalSessions  = $attendanceData->count();
        $presentCount   = $attendanceData->where('status', 'Present')->count();
        $attendanceRate = $totalSessions > 0
            ? round(($presentCount / $totalSessions) * 100, 2)
            : 0;

        $testScores   = $testResults->pluck('score');
        $averageScore = $testScores->count() > 0 ? round($testScores->avg(), 2) : 0;
        $highestScore = $testScores->max() ?? 0;
        $lowestScore  = $testScores->min() ?? 0;

        // Monthly trend — populate once tests are wired up
        $monthlyProgress = collect();

        // Contributions
        $contributions = Contribution::where('member_id', $member->id)
            ->where('academic_year_id', $filters['academic_year_id'])
            ->orderBy('month')
            ->get();

        $totalContributions = $contributions->count();
        $paidCount = $contributions->where('status', 'Paid')->count();
        $unpaidCount = $totalContributions - $paidCount;
        $totalAmount = $contributions->where('status', 'Paid')->sum('amount');
        $expectedAmount = $contributions->sum('amount');
        $paymentRate = $totalContributions > 0 ? round(($paidCount / $totalContributions) * 100, 2) : 0;

        $monthlyDetail = $contributions->map(fn ($c) => [
            'month_name' => $c->month_name,
            'month' => $c->month,
            'amount' => $c->amount,
            'is_paid' => $c->is_paid,
            'status' => $c->status,
            'payment_date' => $c->payment_date,
            'payment_method' => $c->payment_method,
        ])->values()->toArray();

        $this->reportData = [
            'student'            => $member,
            'current_enrollment' => $currentEnrollment,
            'attendance'         => [
                'total_sessions' => $totalSessions,
                'present'        => $presentCount,
                'rate'           => $attendanceRate,
                'details'        => $attendanceData->groupBy('status'),
            ],
            'tests' => [
                'total_tests'   => $testResults->count(),
                'average_score' => $averageScore,
                'highest_score' => $highestScore,
                'lowest_score'  => $lowestScore,
                'results'       => $testResults,
            ],
            'progress_trend' => $monthlyProgress,
            'contributions' => [
                'total_months' => $totalContributions,
                'paid_count' => $paidCount,
                'unpaid_count' => $unpaidCount,
                'total_paid' => $totalAmount,
                'expected_total' => $expectedAmount,
                'payment_rate' => $paymentRate,
                'monthly' => $monthlyDetail,
            ],
        ];
    }

    // FIX: Livewire component methods cannot return an HTTP Response.
    // Raise a notification for now; swap for a PDF stream / redirect when ready.
    public function generateReportCard(): void
    {
        if (! $this->reportData) {
            Notification::make()
                ->title('Generate a progress report first.')
                ->warning()
                ->send();
            return;
        }

        Notification::make()
            ->title('Report card export coming soon.')
            ->info()
            ->send();
    }
}
