<?php

namespace App\Filament\Pages\Education;

use App\Models\StudentAttendance;
use App\Models\StudentEnrollment;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Schemas\Schema;
use App\Models\ClassModel;
use App\Models\AcademicYear;
use Filament\Pages\Page;
use Filament\Forms;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class AttendanceSummaryReport extends Page
{
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-chart-bar';
    }

    protected string $view = 'filament.pages.education.attendance-summary-report';

    public static function getNavigationGroup(): ?string
    {
        return 'Attendance & Results';
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
        return \App\Support\RoleGate::can('page.report.attendance-summary');
    }

    public function mount(): void
    {
        $this->reportData = null;
        $this->form->fill([
            'academic_year_id' => null,
            'class_id' => null,
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
            'start_date' => null,
            'end_date' => null,
        ]);

        $this->reportData = null;
    }

    public function getReportData(): array
    {
        $filters = $this->filters ?? [];

        $classId = $filters['class_id'] ?? null;
        $academicYearId = $filters['academic_year_id'] ?? null;
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;

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

        if ($classId && $academicYearId) {
            $enrolledStudentIds = StudentEnrollment::where('class_id', $classId)
                ->where('academic_year_id', $academicYearId)
                ->pluck('member_id');

            $studentQuery->whereIn('student_id', $enrolledStudentIds);
        }

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
            $student = $studentAttendances->first()->student;

            return [
                'student_name' => $this->deepSanitizeString($student?->full_name ?? $student?->member_code ?? 'N/A'),
                'total_sessions' => $total,
                'present' => $present,
                'rate' => round($rate, 2),
            ];
        })->sortByDesc('rate')->values();

        $totalEntries = $presentCount + $absentCount + $lateCount + $excusedCount;

        // Build by_date with properly formatted strings to avoid JSON serialization issues
        $byDateArray = [];
        foreach ($attendances->groupBy(fn ($a) => $a->session->session_date) as $dateKey => $dateAttendances) {
            $total = $dateAttendances->count();
            $present = $dateAttendances->where('status', 'Present')->count();
            $dateString = $dateKey instanceof \Carbon\Carbon
                ? $dateKey->format('Y-m-d')
                : (is_string($dateKey) ? explode(' ', $dateKey)[0] : $dateKey);

            $byDateArray[] = [
                'date' => $dateString,
                'total' => $total,
                'present' => $present,
                'rate' => $total > 0 ? round(($present / $total) * 100, 2) : 0,
            ];
        }

        // Sort by date
        usort($byDateArray, function ($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        $data = [
            'summary' => [
                'total_sessions' => $totalSessions,
                'total_students' => $totalStudents,
                'present_rate' => $totalEntries > 0 ? round(($presentCount / $totalEntries) * 100, 2) : 0,
                'present' => $presentCount,
                'absent' => $absentCount,
                'late' => $lateCount,
                'excused' => $excusedCount,
            ],
            'by_student' => $attendanceByStudent->toArray(),
            'by_date' => $byDateArray,
        ];

        $encoded = @json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE);
        if ($encoded !== false) {
            $data = json_decode($encoded, true);
        }

        return $data;
    }

    public function exportToExcel()
    {
        if (!$this->reportData) {
            $this->reportData = $this->getReportData();
        }

        $reportData = $this->deepSanitizeUtf8($this->reportData);

        $rows = [];
        $rows[] = ['Student', 'Sessions', 'Present', 'Absent', 'Late', 'Excused', 'Rate'];
        $rows[] = [
            'TOTAL',
            $reportData['summary']['total_sessions'],
            $reportData['summary']['present'],
            $reportData['summary']['absent'],
            $reportData['summary']['late'],
            $reportData['summary']['excused'],
            $reportData['summary']['present_rate'] . '%',
        ];
        $rows[] = [];

        foreach ($reportData['by_student'] as $student) {
            $name = $student['student_name'] ?? 'N/A';
            $rows[] = [
                $name,
                $student['total_sessions'],
                $student['present'],
                $student['total_sessions'] - $student['present'],
                '',
                '',
                $student['rate'] . '%',
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

        return response()->streamDownload($callback, 'attendance-summary.csv');
    }

    public function exportToPdf()
    {
        if (!$this->reportData) {
            $this->reportData = $this->getReportData();
        }

        // Work on a local copy — don't mutate the Livewire property
        $reportData = $this->deepSanitizeUtf8($this->reportData);

        $html = '<h2>Attendance Summary Report</h2>';
        $html .= '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse:collapse;width:100%;">';
        $html .= '<tr><th>Metric</th><th>Value</th></tr>';
        $html .= '<tr><td>Total Sessions</td><td>' . (int) $reportData['summary']['total_sessions'] . '</td></tr>';
        $html .= '<tr><td>Total Students</td><td>'  . (int) $reportData['summary']['total_students']  . '</td></tr>';
        $html .= '<tr><td>Present Rate</td><td>'    . (float) $reportData['summary']['present_rate']   . '%</td></tr>';
        $html .= '<tr><td>Present</td><td>'         . (int) $reportData['summary']['present']          . '</td></tr>';
        $html .= '<tr><td>Absent</td><td>'          . (int) $reportData['summary']['absent']           . '</td></tr>';
        $html .= '</table>';

        $html .= '<h3>Attendance by Student</h3>';
        $html .= '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse:collapse;width:100%;">';
        $html .= '<tr><th>Student</th><th>Sessions</th><th>Present</th><th>Rate</th></tr>';

        foreach ($reportData['by_student'] as $student) {
            $name = $this->deepSanitizeString($student['student_name'] ?? 'N/A');
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '<td>' . (int) $student['total_sessions'] . '</td>';
            $html .= '<td>' . (int) $student['present'] . '</td>';
            $html .= '<td>' . (float) $student['rate'] . '%</td>';
            $html .= '</tr>';
        }
        $html .= '</table>';

        // Capture PDF output as a string — never call ->download() inside Livewire
        $pdfContent = Pdf::loadHTML($html)->output();

        return response()->streamDownload(
            function () use ($pdfContent) { echo $pdfContent; },
            'attendance-summary.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    // ── Replaces the old ensureValidUtf8 + sanitizeUtf8 pair ──────────────────

    private function deepSanitizeUtf8(?array $data): array
    {
        if ($data === null) {
            return [];
        }

        array_walk_recursive($data, function (&$value) {
            if (is_string($value)) {
                $value = $this->deepSanitizeString($value);
            }
        });

        return $data;
    }

    private function deepSanitizeString(string $value): string
    {
        // Already valid — fast path
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        // Try iconv strip
        $cleaned = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
        if ($cleaned !== false && $cleaned !== '') {
            return $cleaned;
        }

        // Try detecting source encoding
        $detected = mb_detect_encoding($value, mb_detect_order(), true);
        if ($detected && $detected !== 'UTF-8') {
            $cleaned = @mb_convert_encoding($value, 'UTF-8', $detected);
            if ($cleaned !== false && $cleaned !== '') {
                return $cleaned;
            }
        }

        // Nuclear option: strip every non-UTF-8 byte
        return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }
}
