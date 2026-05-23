<?php

namespace App\Filament\Widgets\Stats;

use App\Models\InventoryItem;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class TotalInventoryItemsWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('Inventory Items', Cache::remember('dashboard_total_inventory', 300, fn () => InventoryItem::count()))
                ->icon('heroicon-o-cube')
                ->color('primary'),
        ];
    }
}
