<?php

namespace App\Filament\Widgets\Stats;

use App\Models\InventoryItem;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TotalInventoryItemsWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('Inventory Items', InventoryItem::count())
                ->icon('heroicon-o-cube')
                ->color('primary'),
        ];
    }
}
