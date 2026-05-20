<?php

namespace App\Filament\Widgets\Stats;

use App\Enums\MemberType;
use App\Models\Member;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class DeptTotalMembersWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $userId = filament()->auth()->id();
        $user = User::find($userId);
        $deptId = $user?->department_id;
        $deptName = $user?->department?->name_en ?? 'All';

        $cacheKey = "dashboard_dept_total_{$deptId}";

        $count = Cache::remember($cacheKey, 300, fn () => $deptId ? Member::where('department_id', $deptId)->count() : Member::count());

        return [
            Stat::make("{$deptName} Members", $count)
                ->icon('heroicon-o-users')
                ->color('primary'),
        ];
    }
}
