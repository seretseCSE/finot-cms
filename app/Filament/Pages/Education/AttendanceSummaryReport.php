<?php

namespace App\Filament\Pages\Education;

use App\Models\StudentAttendance;
use App\Models\TeacherAttendance;
use Filament\Schemas\Schema;
use App\Models\ClassModel;
use App\Models\AcademicYear;
use Filament\Pages\Page;
use Filament\Forms;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AttendanceSummaryReport extends Page
{
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-chart-bar';
    }

    protected string $view = 'filament.pages.education.attendance-summary-report';

    public static function getNavigationGroup(): ?string
    {
        return 'Reports';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public ?array $filters = [];
    public ?array $reportData = null;
    public bool $isLoading = false;

    public static function canAccess(array $parameters = []): bool
    {
        return Auth::user()?->can('page.report.attendance-summary');
    }

    public function mount(): void
    {
        $this->form->fill([
            'academic_year_id' => AcademicYear::where('status', 'Active')->first()?->id,
            'class_id' => null,
            'start_date' => now()->subMonth()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ]);

        $this->updateReportData();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('filters')->components([
                Forms\Components\Select::make('academic_year_id')
                    ->label('Academic Year')
                    ->options(AcademicYear::pluck('name', 'id'))
                    ->live()
                    ->afterStateUpdated(function ($state, $set) {
                        $set('class_id', null);
                        $this->updateReportData();
                    }),

                Forms\Components\Select::make('class_id')
                    ->label('Class')
                    ->options(function ($get) {
                        $yearId = $get('academic_year_id');

                        $query = ClassModel::orderBy('name');

                        if ($yearId) {
                            $query->whereHas('attendanceSessions', fn ($q) => $q->where('academic_year_id', $yearId));
                        }

                        return $query->get()->mapWithKeys(function ($class) use ($yearId) {
                            $hasSessions = $yearId
                                ? $class->attendanceSessions()->where('academic_year_id', $yearId)->exists()
                                : true;

                            $label = $class->name . ($hasSessions ? ' ✓' : ' (No sessions)');
                            return [$class->id => $label];
                        });
                    })
                    ->searchable()
                    ->preload()
                    ->placeholder('All Classes')
                    ->live()
                    ->afterStateUpdated(fn () => $this->updateReportData()),

                Forms\Components\DatePicker::make('start_date')
                    ->label('Start Date')
                    ->live()
                    ->afterStateUpdated(function () {
                        $this->updateReportData();
                    }),

                Forms\Components\DatePicker::make('end_date')
                    ->label('End Date')
                    ->live()
                    ->afterOrEqual('start_date')
                    ->afterStateUpdated(function () {
                        $this->updateReportData();
                    }),
            ])
            ->columns(4);
    }

    public function updateReportData(): void
    {
        $formData = $this->filters ?? [];

        $academicYearId = $formData['academic_year_id'] ?? null;
        $startDate = $formData['start_date'] ?? null;
        $endDate = $formData['end_date'] ?? null;

        if ($academicYearId && $startDate && $endDate) {
            $this->isLoading = true;
            $this->reportData = $this->getReportData();
            $this->isLoading = false;
        } else {
            $this->reportData = null;
            $this->isLoading = false;
        }
    }

    public function resetFilters(): void
    {
        $this->form->fill([
            'academic_year_id' => AcademicYear::where('status', 'Active')->first()?->id,
            'class_id' => null,
            'start_date' => now()->subMonth()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ]);

        $this->updateReportData();
    }

    public function getReportData(): array
    {
        $filters = $this->filters ?? [];

        $classId = $filters['class_id'] ?? null;
        $academicYearId = $filters['academic_year_id'] ?? null;
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;

        Log::debug('AttendanceSummaryReport::getReportData', [
            'class_id' => $classId,
            'academic_year_id' => $academicYearId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $studentQuery = StudentAttendance::with(['student', 'session.classes', 'session.academicYear'])
            ->whereHas('session', function (Builder $query) use ($academicYearId, $classId, $startDate, $endDate) {
                if ($academicYearId) {
                    $query->where('academic_year_id', $academicYearId);
                }
                if ($classId) {
                    $query->where(function (Builder $q) use ($classId) {
                        $q->where('class_id', $classId)
                          ->orWhereHas('classes', fn ($q2) => $q2->where('classes.id', $classId));
                    });
                }
                if ($startDate && $endDate) {
                    $query->whereBetween('session_date', [$startDate, $endDate]);
                }
            });

        $attendances = $studentQuery->get();

        $totalSessions = $attendances->pluck('session_id')->unique()->count();
        $totalStudents = $attendances->pluck('student_id')->unique()->count();
        $presentCount = $attendances->where('status', 'Present')->count();
        $absentCount = $attendances->where('status', 'Absent')->count();
        $lateCount = $attendances->where('status', 'Late')->count();
        $excusedCount = $attendances->where('status', 'Excused')->count();

        $attendanceByStudent = $attendances->groupBy('student_id')->map(function ($studentAttendances) {
            $total = $studentAttendances->count();
            $present = $studentAttendances->where('status', 'Present')->count();
            $rate = $total > 0 ? ($present / $total) * 100 : 0;

            return [
                'student' => $studentAttendances->first()->student,
                'total_sessions' => $total,
                'present' => $present,
                'rate' => round($rate, 2),
            ];
        })->sortByDesc('rate');

        $teacherQuery = TeacherAttendance::with(['teacherAssignment.teacher', 'teacherAssignment.subject', 'session'])
            ->whereHas('session', function (Builder $query) use ($academicYearId, $classId, $startDate, $endDate) {
                if ($academicYearId) {
                    $query->where('academic_year_id', $academicYearId);
                }
                if ($classId) {
                    $query->where(function (Builder $q) use ($classId) {
                        $q->where('class_id', $classId)
                          ->orWhereHas('classes', fn ($q2) => $q2->where('classes.id', $classId));
                    });
                }
                if ($startDate && $endDate) {
                    $query->whereBetween('session_date', [$startDate, $endDate]);
                }
            });

        $teacherAttendances = $teacherQuery->get();

        $byTeacherSubject = $teacherAttendances
            ->groupBy(fn ($ta) => $ta->teacherAssignment?->subject?->name ?? 'Unknown')
            ->map(function ($subjectAttendances) {
                return $subjectAttendances
                    ->groupBy(fn ($ta) => $ta->teacherAssignment?->teacher?->id ?? 0)
                    ->map(function ($teacherAttendances) {
                        $total = $teacherAttendances->count();
                        $present = $teacherAttendances->where('attendance_status', 'Present')->count();
                        $rate = $total > 0 ? ($present / $total) * 100 : 0;

                        return [
                            'teacher_name' => $teacherAttendances->first()->teacherAssignment?->teacher?->full_name ?? 'N/A',
                            'total_sessions' => $total,
                            'present' => $present,
                            'rate' => round($rate, 2),
                        ];
                    })
                    ->sortByDesc('rate')
                    ->values()
                    ->toArray();
            })
            ->toArray();

        $totalEntries = $presentCount + $absentCount + $lateCount + $excusedCount;

        return [
            'summary' => [
                'total_sessions' => $totalSessions,
                'total_students' => $totalStudents,
                'present_rate' => $totalEntries > 0 ? round(($presentCount / $totalEntries) * 100, 2) : 0,
                'present' => $presentCount,
                'absent' => $absentCount,
                'late' => $lateCount,
                'excused' => $excusedCount,
            ],
            'by_student' => $attendanceByStudent,
            'by_date' => $attendances->groupBy(fn ($a) => $a->session->session_date)
                ->map(function ($dateAttendances) {
                    $total = $dateAttendances->count();
                    $present = $dateAttendances->where('status', 'Present')->count();

                    return [
                        'date' => $dateAttendances->first()->session->session_date,
                        'total' => $total,
                        'present' => $present,
                        'rate' => $total > 0 ? round(($present / $total) * 100, 2) : 0,
                    ];
                })->sortBy('date'),
            'by_teacher_subject' => $byTeacherSubject,
        ];
    }

    public function exportToExcel()
    {
        if (!$this->reportData) {
            $this->reportData = $this->getReportData();
        }

        $data = $this->reportData;

        return response()->json($data);
    }

    public function exportToPdf()
    {
        if (!$this->reportData) {
            $this->reportData = $this->getReportData();
        }

        $data = $this->reportData;

        return response()->json($data);
    }
}
