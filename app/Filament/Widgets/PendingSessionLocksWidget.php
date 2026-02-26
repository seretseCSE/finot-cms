<?php

namespace App\Filament\Widgets;

use App\Models\AttendanceSession;
use App\Models\AcademicYear;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class PendingSessionLocksWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected function getStats(): array
    {
        $activeYear = AcademicYear::query()->where('is_active', true)->first();

        if (! $activeYear) {
            return [
                Stat::make('Pending Locks', 0)
                    ->description('No active academic year')
                    ->descriptionIcon('heroicon-m-exclamation-triangle')
                    ->color('danger'),
            ];
        }

        $thresholdDate = now()->subDays(27);
        $pending = AttendanceSession::query()
            ->where('academic_year_id', $activeYear->id)
            ->whereIn('status', ['Open', 'Completed'])
            ->where('session_date', '<=', $thresholdDate)
            ->count();

        $color = $pending > 0 ? 'warning' : 'success';

        return [
            Stat::make('Pending Locks', $pending)
                ->description('Sessions approaching 30-day auto-lock')
                ->descriptionIcon('heroicon-m-clock')
                ->color($color)
                ->url(route('filament.admin.resources.attendance-sessions.index')),
        ];
    }

    protected function getColumns(): int
    {
        return 1;
    }
}

