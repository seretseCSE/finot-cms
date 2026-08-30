<?php

namespace App\Filament\Pages\Education;

use App\Models\TeacherAttendance;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Schemas\Schema;
use App\Models\ClassModel;
use App\Models\AcademicYear;
use App\Models\Teacher;
use Filament\Pages\Page;
use Filament\Forms;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class TeacherAttendanceReport extends Page
{
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-academic-cap';
    }

    protected string $view = 'filament.pages.education.teacher-attendance-report';

    public static function getNavigationGroup(): ?string
    {
        return 'Attendance & Results';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public ?array $filters = [];
    public ?array $reportData = null;
    public bool $isLoading = false;

    public static function canAccess(array $parameters = []): bool
    {
        return \App\Support\RoleGate::can('page.report.teacher-attendance');
    }

    public function mount(): void
    {
        $this->form->fill([
            'academic_year_id' => null,
            'class_id' => null,
            'teacher_id' => null,
            'start_date' => null,
            'end_date' => null,
        ]);
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
                        $set('teacher_id', null);
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

                Forms\Components\Select::make('teacher_id')
                    ->label('Teacher')
                    ->options(function ($get) {
                        $yearId = $get('academic_year_id');

                        if (!$yearId) {
                            return [];
                        }

                        return Teacher::where('status', 'Active')
                            ->whereHas('assignments', fn ($q) => $q->where('academic_year_id', $yearId))
                            ->get()
                            ->mapWithKeys(fn ($t) => [$t->id => $t->full_name]);
                    })
                    ->searchable()
                    ->preload()
                    ->placeholder('All Teachers')
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
            ->columns(5);
    }

    public function updateReportData(): void
    {
        $formData = $this->filters ?? [];

        $academicYearId = $formData['academic_year_id'] ?? null;

        if ($academicYearId) {
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
            'academic_year_id' => null,
            'class_id' => null,
            'teacher_id' => null,
            'start_date' => null,
            'end_date' => null,
        ]);

        $this->reportData = null;
    }

    public function getReportData(): array
    {
        $filters = $this->filters ?? [];

        $classId = $filters['class_id'] ?? null;
        $teacherId = $filters['teacher_id'] ?? null;
        $academicYearId = $filters['academic_year_id'] ?? null;
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;

        $teacherAttendances = TeacherAttendance::with(['teacherAssignment.teacher', 'teacherAssignment.subject', 'session'])
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

        if ($teacherId) {
            $teacherAttendances->whereHas('teacherAssignment', fn ($q) => $q->where('teacher_id', $teacherId));
        }

        $teacherAttendances = $teacherAttendances->get();

        $rows = $teacherAttendances
            ->groupBy(fn ($ta) => $ta->teacherAssignment?->subject?->name ?? 'Unknown')
            ->flatMap(function ($subjectAttendances, $subjectName) {
                return $subjectAttendances
                    ->groupBy(fn ($ta) => $ta->teacherAssignment?->teacher?->id ?? 0)
                    ->map(function ($teacherAttendances) use ($subjectName) {
                        $total = $teacherAttendances->count();
                        $present = $teacherAttendances->where('attendance_status', 'Present')->count();
                        $rate = $total > 0 ? ($present / $total) * 100 : 0;

                        return [
                            'teacher_name' => $this->deepSanitizeString($teacherAttendances->first()->teacherAssignment?->teacher?->full_name ?? 'N/A'),
                            'subject' => $this->deepSanitizeString($subjectName),
                            'total_sessions' => $total,
                            'present' => $present,
                            'absent' => $total - $present,
                            'rate' => round($rate, 2),
                        ];
                    })
                    ->sortByDesc('rate')
                    ->values();
            })
            ->toArray();

        $totalSessions = $teacherAttendances->pluck('session_id')->unique()->count();
        $presentCount = $teacherAttendances->where('attendance_status', 'Present')->count();
        $totalEntries = $teacherAttendances->count();

        return [
            'total_sessions' => $totalSessions,
            'total_entries' => $totalEntries,
            'present_rate' => $totalEntries > 0 ? round(($presentCount / $totalEntries) * 100, 2) : 0,
            'present' => $presentCount,
            'absent' => $totalEntries - $presentCount,
            'rows' => $rows,
        ];
    }

    public function exportToExcel()
    {
        $reportData = $this->reportData ?? $this->getReportData();

        $rows = [];
        $rows[] = ['Teacher', 'Subject', 'Sessions', 'Present', 'Absent', 'Rate'];
        $rows[] = [
            'TOTAL',
            '',
            $reportData['total_sessions'],
            $reportData['present'],
            $reportData['absent'],
            $reportData['present_rate'] . '%',
        ];
        $rows[] = [];

        foreach ($reportData['rows'] as $entry) {
            $rows[] = [
                $entry['teacher_name'],
                $entry['subject'],
                $entry['total_sessions'],
                $entry['present'],
                $entry['absent'],
                $entry['rate'] . '%',
            ];
        }

        $callback = function () use ($rows) {
            $file = fopen('php://output', 'w');
            fprintf($file, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->streamDownload($callback, 'teacher-attendance.csv');
    }

    public function exportToPdf()
    {
        $reportData = $this->reportData ?? $this->getReportData();
        $reportData = $this->deepSanitizeUtf8($reportData);

        $html = '<h2>Teacher Attendance Report</h2>';
        $html .= '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse:collapse;width:100%;">';
        $html .= '<tr><th>Metric</th><th>Value</th></tr>';
        $html .= '<tr><td>Total Sessions</td><td>' . ($reportData['total_sessions'] ?? 0) . '</td></tr>';
        $html .= '<tr><td>Total Records</td><td>' . ($reportData['total_entries'] ?? 0) . '</td></tr>';
        $html .= '<tr><td>Present Rate</td><td>' . ($reportData['present_rate'] ?? 0) . '%</td></tr>';
        $html .= '<tr><td>Present</td><td>' . ($reportData['present'] ?? 0) . '</td></tr>';
        $html .= '<tr><td>Absent</td><td>' . ($reportData['absent'] ?? 0) . '</td></tr>';
        $html .= '</table>';

        $html .= '<h3>Attendance by Teacher</h3>';
        $html .= '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse:collapse;width:100%;">';
        $html .= '<tr><th>Teacher</th><th>Subject</th><th>Sessions</th><th>Present</th><th>Absent</th><th>Rate</th></tr>';
        foreach (($reportData['rows'] ?? []) as $entry) {
            $html .= '<tr>';
            $html .= '<td>' . e($this->deepSanitizeString($entry['teacher_name'] ?? '')) . '</td>';
            $html .= '<td>' . e($this->deepSanitizeString($entry['subject'] ?? '')) . '</td>';
            $html .= '<td>' . ($entry['total_sessions'] ?? 0) . '</td>';
            $html .= '<td>' . ($entry['present'] ?? 0) . '</td>';
            $html .= '<td>' . ($entry['absent'] ?? 0) . '</td>';
            $html .= '<td>' . ($entry['rate'] ?? 0) . '%</td>';
            $html .= '</tr>';
        }
        $html .= '</table>';

        return response()->streamDownload(
            fn () => print(Pdf::loadHTML($html)->output()),
            'teacher-attendance.pdf'
        );
    }

    private function deepSanitizeString(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $cleaned = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
        if ($cleaned !== false && $cleaned !== '') {
            return $cleaned;
        }

        $detected = @mb_detect_encoding($value, mb_detect_order(), true);
        if ($detected && $detected !== 'UTF-8') {
            $converted = @mb_convert_encoding($value, 'UTF-8', $detected);
            if ($converted !== false && $converted !== '') {
                return $converted;
            }
        }

        $stripped = @preg_replace('/[^\x09\x0A\x0D\x20-\x7F]/', '', $value);
        if ($stripped !== null && $stripped !== '') {
            return $stripped;
        }

        return '';
    }

    private function deepSanitizeUtf8(?array $data): array
    {
        if ($data === null) {
            return [];
        }

        $encoded = @json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE);
        if ($encoded !== false) {
            return json_decode($encoded, true) ?? [];
        }

        $sanitized = $data;
        array_walk_recursive($sanitized, function (&$item) {
            if (is_string($item)) {
                $item = $this->deepSanitizeString($item);
            }
        });

        return $sanitized;
    }
}
