<?php

namespace App\Filament\Widgets\Stats;

use App\Enums\MemberType;
use App\Filament\Resources\MemberResource;
use App\Models\Member;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class AdultMembersWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $count = Cache::remember('dashboard_adult_members', 300, fn () => Member::where('member_type', MemberType::ADULT)->count());

        return [
            Stat::make('Adult Members', $count)
                ->icon('heroicon-o-user-group')
                ->color('primary')
                ->url(MemberResource::getUrl()),
        ];
    }
}
