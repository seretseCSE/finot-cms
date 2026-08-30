<?php

namespace App\Filament\Widgets\Charts;

use Filament\Widgets\LineChartWidget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AttendanceTrendChart extends LineChartWidget
{
    protected int | string | array $columnSpan = 2;

    protected ?string $heading = 'Student Attendance Rate (12 Weeks)';

    protected function getData(): array
    {
        return Cache::remember('dashboard_attendance_trend', 300, function () {
            $startDate = now()->subWeeks(11)->startOfWeek();

            $rows = DB::table('student_attendances')
                ->join('attendance_sessions', 'student_attendances.session_id', '=', 'attendance_sessions.id')
                ->where('attendance_sessions.session_date', '>=', $startDate)
                ->selectRaw("
                    YEARWEEK(attendance_sessions.session_date, 1) as yw,
                    MIN(attendance_sessions.session_date) as week_start,
                    COUNT(*) as total,
                    SUM(CASE WHEN student_attendances.status = 'Present' THEN 1 ELSE 0 END) as present
                ")
                ->groupBy('yw')
                ->orderBy('yw')
                ->get();

            $labels = [];
            $data = [];
            foreach ($rows as $row) {
                $labels[] = \Carbon\Carbon::parse($row->week_start)->format('M j');
                $data[] = $row->total > 0 ? round(($row->present / $row->total) * 100, 1) : 0;
            }

            return [
                'datasets' => [
                    [
                        'label' => 'Attendance %',
                        'data' => $data,
                        'borderColor' => '#22c55e',
                        'backgroundColor' => '#22c55e',
                    ],
                ],
                'labels' => $labels,
            ];
        });
    }
}
