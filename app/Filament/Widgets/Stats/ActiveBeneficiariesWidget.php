<?php

namespace App\Filament\Widgets\Stats;

use App\Models\Beneficiary;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ActiveBeneficiariesWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $count = Beneficiary::where('status', 'Active')->count();

        return [
            Stat::make('Active Beneficiaries', $count)
                ->icon('heroicon-o-heart')
                ->color('success'),
        ];
    }
}
