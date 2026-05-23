<?php

namespace App\Filament\Widgets\Stats;

use App\Models\StudentAttendance;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class AttendanceRateWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $rate = Cache::remember('dashboard_attendance_rate', 300, function () {
            $total = StudentAttendance::whereHas('session', fn ($q) => $q->whereBetween('session_date', [now()->startOfWeek(), now()->endOfWeek()]))
                ->count();

            $present = StudentAttendance::whereHas('session', fn ($q) => $q->whereBetween('session_date', [now()->startOfWeek(), now()->endOfWeek()]))
                ->where('status', 'Present')
                ->count();

            return $total > 0 ? round(($present / $total) * 100, 1) : 0;
        });

        return [
            Stat::make("This Week's Attendance", "{$rate}%")
                ->icon('heroicon-o-check-circle')
                ->color($rate >= 80 ? 'success' : ($rate >= 50 ? 'warning' : 'danger')),
        ];
    }
}
