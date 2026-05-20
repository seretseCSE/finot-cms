<?php

namespace App\Filament\Widgets\Stats;

use App\Models\StudentAttendance;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AttendanceRateWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $total = StudentAttendance::whereHas('session', fn ($q) => $q->whereDate('session_date', today()))
            ->count();

        $present = StudentAttendance::whereHas('session', fn ($q) => $q->whereDate('session_date', today()))
            ->where('status', 'Present')
            ->count();

        $rate = $total > 0 ? round(($present / $total) * 100, 1) : 0;

        return [
            Stat::make("Today's Attendance", "{$rate}%")
                ->icon('heroicon-o-check-circle')
                ->color($rate >= 80 ? 'success' : ($rate >= 50 ? 'warning' : 'danger')),
        ];
    }
}
