<?php

namespace App\Filament\Widgets\Stats;

use App\Enums\MemberType;
use App\Filament\Resources\MemberResource;
use App\Models\Member;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class KidsMembersWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $count = Cache::remember('dashboard_kids_members', 300, fn () => Member::where('member_type', MemberType::KIDS)->count());

        return [
            Stat::make('Kids Members', $count)
                ->icon('heroicon-o-academic-cap')
                ->color('success')
                ->url(MemberResource::getUrl()),
        ];
    }
}
