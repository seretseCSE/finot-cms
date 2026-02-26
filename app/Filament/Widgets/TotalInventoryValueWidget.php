<?php

namespace App\Filament\Widgets;

use App\Models\InventoryItem;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class TotalInventoryValueWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        // Only show to inventory staff, nibret_hisab_head, admin, superadmin
        if (!Auth::user()?->hasRole(['inventory_staff', 'nibret_hisab_head', 'admin', 'superadmin'])) {
            return [];
        }

        $totalValue = InventoryItem::whereNull('deleted_at')
            ->whereNotNull('purchase_price')
            ->get()
            ->sum(function ($item) {
                return $item->purchase_price * $item->current_stock;
            });

        return [
            Stat::make('Total Inventory Value', number_format($totalValue, 2))
                ->description('Sum of purchase price × current quantity')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),
        ];
    }

    protected function getColumns(): int
    {
        return 1;
    }
}

