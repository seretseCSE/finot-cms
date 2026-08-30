<?php

namespace App\Filament\Widgets\Stats;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AttendanceRateWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $rate = Cache::remember('dashboard_attendance_rate', 300, function () {
            $row = DB::table('student_attendances')
                ->join('attendance_sessions', 'student_attendances.session_id', '=', 'attendance_sessions.id')
                ->whereBetween('attendance_sessions.session_date', [now()->startOfWeek(), now()->endOfWeek()])
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN student_attendances.status = 'Present' THEN 1 ELSE 0 END) as present
                ")
                ->first();

            return $row && $row->total > 0 ? round(($row->present / $row->total) * 100, 1) : 0;
        });

        return [
            Stat::make("This Week's Attendance", "{$rate}%")
                ->icon('heroicon-o-check-circle')
                ->color($rate >= 80 ? 'success' : ($rate >= 50 ? 'warning' : 'danger')),
        ];
    }
}
