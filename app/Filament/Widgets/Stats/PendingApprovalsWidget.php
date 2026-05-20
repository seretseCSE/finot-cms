<?php

namespace App\Filament\Widgets\Stats;

use App\Models\FinancialTransaction;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class PendingApprovalsWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $count = Cache::remember('dashboard_pending_approvals', 60, fn () => FinancialTransaction::pending()->count());

        return [
            Stat::make('Pending Approvals', $count)
                ->icon('heroicon-o-clock')
                ->color($count > 0 ? 'warning' : 'success'),
        ];
    }
}
