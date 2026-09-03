<?php

namespace App\Filament\Widgets\Stats;

use App\Filament\Pages\Student\MyAttendance;
use App\Filament\Pages\Student\MyResults;
use App\Models\MarklistItem;
use App\Models\StudentAttendance;
use App\Models\StudentEnrollment;
use App\Support\RoleGate;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StudentOverviewWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return RoleGate::is('student');
    }

    protected function getStats(): array
    {
        $memberId = RoleGate::user()?->member_id;

        $enrollment = $memberId
            ? StudentEnrollment::query()->active()->where('member_id', $memberId)->with('class')->latest()->first()
            : null;

        $resultsCount = $memberId
            ? MarklistItem::query()
                ->where('member_id', $memberId)
                ->whereHas('marklist', fn ($query) => $query->where('status', 'approved'))
                ->count()
            : 0;

        $attendanceBase = $memberId
            ? StudentAttendance::query()
                ->where('student_id', $memberId)
                ->whereHas('session', fn ($query) => $query->whereBetween('session_date', [now()->startOfWeek(), now()->endOfWeek()]))
            : null;

        $total = $attendanceBase?->count() ?? 0;
        $present = $memberId
            ? StudentAttendance::query()
                ->where('student_id', $memberId)
                ->where('status', 'Present')
                ->whereHas('session', fn ($query) => $query->whereBetween('session_date', [now()->startOfWeek(), now()->endOfWeek()]))
                ->count()
            : 0;
        $rate = $total > 0 ? round(($present / $total) * 100, 1) : 0;

        return [
            Stat::make('Class', $enrollment?->class?->name ?? 'Not enrolled')
                ->icon('heroicon-o-building-library')
                ->color('info')
                ->url(MyResults::getUrl()),
            Stat::make('Approved results', (string) $resultsCount)
                ->icon('heroicon-o-academic-cap')
                ->color('success')
                ->url(MyResults::getUrl()),
            Stat::make("This week's attendance", "{$rate}%")
                ->icon('heroicon-o-check-circle')
                ->color($rate >= 80 ? 'success' : ($rate >= 50 ? 'warning' : 'danger'))
                ->url(MyAttendance::getUrl()),
        ];
    }
}
