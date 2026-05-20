<?php

namespace App\Filament\Widgets\Stats;

use App\Enums\MemberType;
use App\Models\Member;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class DeptYouthMembersWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $userId = filament()->auth()->id();
        $user = User::find($userId);
        $deptId = $user?->department_id;

        $cacheKey = "dashboard_dept_youth_{$deptId}";

        $count = Cache::remember($cacheKey, 300, fn () => Member::where('member_type', MemberType::YOUTH)
            ->when($deptId, fn ($q) => $q->where('department_id', $deptId))
            ->count());

        return [
            Stat::make('Youth Members', $count)
                ->icon('heroicon-o-user')
                ->color('info'),
        ];
    }
}
