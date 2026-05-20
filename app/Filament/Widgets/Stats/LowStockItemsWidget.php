<?php

namespace App\Filament\Widgets\Stats;

use App\Models\InventoryItem;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LowStockItemsWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $count = InventoryItem::where('quantity', '<=', 5)
            ->count();

        return [
            Stat::make('Low Stock Items', $count)
                ->icon('heroicon-o-exclamation-triangle')
                ->color($count > 0 ? 'warning' : 'success'),
        ];
    }
}
