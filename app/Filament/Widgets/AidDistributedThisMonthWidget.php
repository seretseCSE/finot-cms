<?php

namespace App\Filament\Widgets;

use App\Models\AidDistribution;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AidDistributedThisMonthWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $thisMonth = now()->startOfMonth();
        $distributions = AidDistribution::where('distribution_date', '>=', $thisMonth);
        
        $totalAmount = $distributions->sum('amount');
        $totalCount = $distributions->count();
        
        return [
            Stat::make('Aid Distributed This Month', 'ETB ' . number_format($totalAmount, 2))
                ->description($totalCount . ' distributions')
                ->icon('heroicono-currency-dollar')
                ->color('primary'),
        ];
    }

    public static function canView(): bool
    {
        return in_array(auth()->user()->role, ['charity_head', 'internal_relations_head', 'admin', 'superadmin']);
    }
}

