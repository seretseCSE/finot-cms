<?php

namespace App\Filament\Widgets\Stats;

use App\Filament\Resources\MemberResource;
use App\Models\Member;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class ActiveMembersWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $data = Cache::remember('dashboard_active_members', 300, function () {
            $count = Member::where('status', 'Active')->count();
            $total = Member::count();
            return ['count' => $count, 'rate' => $total > 0 ? round(($count / $total) * 100, 1) : 0];
        });

        return [
            Stat::make('Active Members', $data['count'])
                ->description("{$data['rate']}% of total")
                ->icon('heroicon-o-user-group')
                ->color('success')
                ->url(MemberResource::getUrl()),
        ];
    }
}
