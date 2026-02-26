<?php

namespace App\Filament\Widgets;

use App\Models\AttendanceSession;
use App\Models\StudentAttendance;
use App\Models\AcademicYear;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class AttendanceRateWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        $activeYear = AcademicYear::query()->where('is_active', true)->first();

        if (! $activeYear) {
            return [
                Stat::make('Attendance Rate', 'N/A')
                    ->description('No active academic year')
                    ->descriptionIcon('heroicon-m-exclamation-triangle')
                    ->color('danger'),
            ];
        }

        $sevenDaysAgo = now()->subDays(7);
        $totalSessions = AttendanceSession::query()
            ->where('academic_year_id', $activeYear->id)
            ->where('session_date', '>=', $sevenDaysAgo)
            ->count();

        $presentSessions = StudentAttendance::query()
            ->join('attendance_sessions', 'student_attendance.session_id', '=', 'attendance_sessions.id')
            ->where('attendance_sessions.academic_year_id', $activeYear->id)
            ->where('attendance_sessions.session_date', '>=', $sevenDaysAgo)
            ->where('student_attendance.status', 'Present')
            ->count();

        $rate = $totalSessions > 0 ? round(($presentSessions / $totalSessions) * 100, 1) : 0;
        $color = $rate >= 90 ? 'success' : ($rate >= 70 ? 'warning' : 'danger');

        return [
            Stat::make('This Week Rate', "{$rate}%")
                ->description('Present / total sessions')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($color),

            Stat::make('Total Sessions', $totalSessions)
                ->description('Last 7 days')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('primary'),
        ];
    }

    protected function getColumns(): int
    {
        return 2;
    }
}

