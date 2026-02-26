<?php

namespace App\Filament\Pages\Education;

use App\Models\Attendance;
use App\Models\ClassModel;
use App\Models\AcademicYear;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class AttendanceSummaryReport extends Page
{
    public static function getNavigationIcon(): ?string { return 'heroicon-o-chart-bar'; }

    protected string $view = 'filament.pages.education.attendance-summary-report';

    public static function getNavigationGroup(): ?string { return 'Education'; }

    public static function getNavigationSort(): ?int { return 1; }

    public ?array $filters = [];

    public function mount(): void
    {
        $this->form->fill([
            'academic_year_id' => AcademicYear::where('is_active', true)->first()?->id,
            'class_id' => null,
            'date_range' => 'month',
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
                    ->required(),

                Forms\Components\Select::make('date_range')
                    ->label('Date Range')
                    ->options([
                        'week' => 'Last Week',
                        'month' => 'Last Month',
                        'quarter' => 'Last Quarter',
                        'year' => 'Last Year',
                    ])
                    ->default('month')
                    ->required(),
            ])
            ->columns(3);
    }

    public function getReportData(): array
    {
        $filters = $this->form->getState();
        
        $query = Attendance::with(['member', 'session.class', 'session.academicYear'])
            ->whereHas('session', function (Builder $query) use ($filters) {
                if ($filters['academic_year_id']) {
                    $query->where('academic_year_id', $filters['academic_year_id']);
                }
                if ($filters['class_id']) {
                    $query->where('class_id', $filters['class_id']);
                }
            });

        // Apply date range filter
        $dateFilter = match($filters['date_range']) {
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'quarter' => now()->subQuarter(),
            'year' => now()->subYear(),
            default => now()->subMonth(),
        };
        
        $query->whereHas('session', function (Builder $query) use ($dateFilter) {
            $query->where('date', '>=', $dateFilter);
        });

        $attendances = $query->get();

        // Calculate statistics
        $totalSessions = $attendances->pluck('session_id')->unique()->count();
        $totalStudents = $attendances->pluck('member_id')->unique()->count();
        $presentCount = $attendances->where('status', 'Present')->count();
        $absentCount = $attendances->where('status', 'Absent')->count();
        $lateCount = $attendances->where('status', 'Late')->count();
        $excusedCount = $attendances->where('status', 'Excused')->count();

        // Calculate attendance rate by student
        $attendanceByStudent = $attendances->groupBy('member_id')->map(function ($studentAttendances) {
            $total = $studentAttendances->count();
            $present = $studentAttendances->where('status', 'Present')->count();
            $rate = $total > 0 ? ($present / $total) * 100 : 0;
            
            return [
                'member' => $studentAttendances->first()->member,
                'total_sessions' => $total,
                'present' => $present,
                'rate' => round($rate, 2),
            ];
        })->sortByDesc('rate');

        return [
            'summary' => [
                'total_sessions' => $totalSessions,
                'total_students' => $totalStudents,
                'present_rate' => $totalStudents > 0 ? round(($presentCount / ($presentCount + $absentCount + $lateCount + $excusedCount)) * 100, 2) : 0,
                'present' => $presentCount,
                'absent' => $absentCount,
                'late' => $lateCount,
                'excused' => $excusedCount,
            ],
            'by_student' => $attendanceByStudent,
            'by_date' => $attendances->groupBy(function ($attendance) {
                return $attendance->session->date;
            })->map(function ($dateAttendances) {
                $total = $dateAttendances->count();
                $present = $dateAttendances->where('status', 'Present')->count();
                
                return [
                    'date' => $dateAttendances->first()->session->date,
                    'total' => $total,
                    'present' => $present,
                    'rate' => $total > 0 ? round(($present / $total) * 100, 2) : 0,
                ];
            })->sortBy('date'),
        ];
    }

    public function exportToExcel()
    {
        $data = $this->getReportData();
        
        // Implementation for Excel export
        // This would use Laravel Excel package
        return response()->json($data);
    }

    public function exportToPdf()
    {
        $data = $this->getReportData();
        
        // Implementation for PDF export
        // This would use DomPDF or similar
        return response()->json($data);
    }
}

