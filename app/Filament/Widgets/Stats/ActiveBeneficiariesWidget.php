<?php

namespace App\Filament\Widgets\Stats;

use App\Models\Beneficiary;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class ActiveBeneficiariesWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $count = Cache::remember('dashboard_active_beneficiaries', 300, fn () =>
            Beneficiary::where('status', 'Active')->count()
        );

        return [
            Stat::make('Active Beneficiaries', $count)
                ->icon('heroicon-o-heart')
                ->color('success'),
        ];
    }
}
