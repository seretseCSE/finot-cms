<?php

namespace App\Filament\Widgets\Stats;

use App\Enums\MemberType;
use App\Filament\Resources\MemberResource;
use App\Models\Member;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class YouthMembersWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $count = Cache::remember('dashboard_youth_members', 300, fn () => Member::where('member_type', MemberType::YOUTH)->count());

        return [
            Stat::make('Youth Members', $count)
                ->icon('heroicon-o-user')
                ->color('info')
                ->url(MemberResource::getUrl()),
        ];
    }
}
