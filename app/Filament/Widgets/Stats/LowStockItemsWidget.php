<?php

namespace App\Filament\Widgets\Stats;

use App\Filament\Resources\InventoryResource;
use App\Models\InventoryItem;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class LowStockItemsWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $count = Cache::remember('dashboard_low_stock_items', 300, fn () =>
            InventoryItem::where('quantity', '<=', 5)->count()
        );

        return [
            Stat::make('Low Stock Items', $count)
                ->icon('heroicon-o-exclamation-triangle')
                ->color($count > 0 ? 'warning' : 'success')
                ->url(InventoryResource::getUrl()),
        ];
    }
}
