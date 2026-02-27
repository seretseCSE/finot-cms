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
public ?array $reportData = null;
public bool $isLoading = false;

    public function mount(): void
    {
        $this->form->fill([
            'academic_year_id' => AcademicYear::where('status', 'Active')->first()?->id,
            'class_id' => null,
            'start_date' => now()->subMonth()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ]);
        
        // Try to load initial data
        $this->updateReportData();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('academic_year_id')
                    ->label('Academic Year')
                    ->options(AcademicYear::pluck('name', 'id'))
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        $set('class_id', null);
                        $this->updateReportData();
                    }),

                Forms\Components\Select::make('class_id')
                    ->label('Class')
                    ->options(function (callable $get) {
                        $yearId = $get('academic_year_id');
                        
                        // Get all classes, but prioritize those with sessions in the selected year
                        $query = ClassModel::query();
                        
                        if ($yearId) {
                            // Get classes that have sessions in this year
                            $classesWithSessions = ClassModel::whereHas('attendanceSessions', function ($query) use ($yearId) {
                                $query->where('academic_year_id', $yearId);
                            })->pluck('id')->toArray();
                            
                            // Get all classes and mark those with sessions
                            $allClasses = ClassModel::orderBy('name')->get();
                            
                            return $allClasses->mapWithKeys(function ($class) use ($classesWithSessions, $yearId) {
                                $hasSessions = in_array($class->id, $classesWithSessions);
                                $label = $class->name . ($hasSessions ? ' ✓' : ' (No sessions)');
                                return [$class->id => $label];
                            });
                        } else {
                            return ClassModel::orderBy('name')
                                ->get()
                                ->mapWithKeys(fn ($class) => [$class->id => $class->name]);
                        }
                    })
                    ->reactive()
                    ->afterStateUpdated(function () {
                        $this->updateReportData();
                    }),

                Forms\Components\DatePicker::make('start_date')
                    ->label('Start Date')
                    ->reactive()
                    ->afterStateUpdated(function () {
                        $this->updateReportData();
                    }),

                Forms\Components\DatePicker::make('end_date')
                    ->label('End Date')
                    ->reactive()
                    ->afterStateUpdated(function () {
                        $this->updateReportData();
                    }),
            ])
            ->columns(4);
    }

    public function updateReportData(): void
    {
        $formData = $this->form->getState();
        
        // Only load data if we have the basic required fields
        if ($formData['academic_year_id'] && $formData['start_date'] && $formData['end_date']) {
            $this->isLoading = true;
            
            // Load the report data
            $this->reportData = $this->getReportData();
            
            $this->isLoading = false;
        } else {
            $this->reportData = null;
            $this->isLoading = false;
        }
    }

    public function generateReport(): void
    {
        $this->reportData = $this->getReportData();
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
        $query->whereHas('session', function (Builder $query) use ($filters) {
            $query->whereBetween('date', [$filters['start_date'], $filters['end_date']]);
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
        if (!$this->reportData) {
            $this->reportData = $this->getReportData();
        }
        
        $data = $this->reportData;
        
        // Implementation for Excel export
        // This would use Laravel Excel package
        return response()->json($data);
    }

    public function exportToPdf()
    {
        if (!$this->reportData) {
            $this->reportData = $this->getReportData();
        }
        
        $data = $this->reportData;
        
        // Implementation for PDF export
        // This would use DomPDF or similar
        return response()->json($data);
    }
}

