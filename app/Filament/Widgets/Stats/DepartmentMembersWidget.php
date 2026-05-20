<?php

namespace App\Filament\Widgets\Stats;

use App\Models\Member;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class DepartmentMembersWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $userId = filament()->auth()->id();
        $user = User::find($userId);

        $deptId = $user?->department_id;
        $deptName = $user?->department?->name_en ?? 'All';

        $cacheKey = "dashboard_dept_members_{$deptId}";

        $count = Cache::remember($cacheKey, 300, function () use ($deptId) {
            if ($deptId) {
                return Member::where('department_id', $deptId)->count();
            }
            return Member::count();
        });

        return [
            Stat::make("{$deptName} Members", $count)
                ->icon('heroicon-o-users')
                ->color('primary'),
        ];
    }
}
