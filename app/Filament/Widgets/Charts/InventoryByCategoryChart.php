<?php

namespace App\Filament\Widgets\Charts;

use App\Models\InventoryItem;
use Filament\Widgets\DoughnutChartWidget;
use Illuminate\Support\Facades\Cache;

class InventoryByCategoryChart extends DoughnutChartWidget
{
    protected int | string | array $columnSpan = 1;

    protected ?string $heading = 'Inventory by Category';

    protected function getData(): array
    {
        $data = Cache::remember('dashboard_inventory_by_category_chart', 300, fn () =>
            InventoryItem::selectRaw('category, COUNT(*) as count')
                ->groupBy('category')
                ->pluck('count', 'category')
                ->toArray()
        );

        return [
            'datasets' => [
                [
                    'data' => array_values($data),
                    'backgroundColor' => ['#3b82f6', '#22c55e', '#f97316', '#8b5cf6', '#ec4899'],
                ],
            ],
            'labels' => array_keys($data),
        ];
    }
}
