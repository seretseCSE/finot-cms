<?php

namespace App\Filament\Widgets\Stats;

use App\Enums\MemberType;
use App\Filament\Resources\MemberResource;
use App\Filament\Support\ClickableStat;
use App\Models\Member;
use Filament\Widgets\StatsOverviewWidget;
use Illuminate\Support\Facades\Cache;

class TotalMembersWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $count = Cache::remember('dashboard_total_members', 300, fn () => Member::count());

        return [
            ClickableStat::make('Total Members', $count, ClickableStat::resourceUrl(MemberResource::class))
                ->icon('heroicon-o-users')
                ->color('primary'),
        ];
    }
}
