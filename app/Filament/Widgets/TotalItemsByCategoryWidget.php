<?php

namespace App\Filament\Widgets;

use App\Models\InventoryItem;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class TotalItemsByCategoryWidget extends ChartWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Total Items by Category';

    protected ?int $chartHeight = 300;

    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        // Only show to inventory staff, nibret_hisab_head, admin, superadmin
        if (!Auth::user()?->hasRole(['inventory_staff', 'nibret_hisab_head', 'admin', 'superadmin'])) {
            return [];
        }

        $data = InventoryItem::whereNull('deleted_at')
            ->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category')
            ->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Items',
                    'data' => [
                        $data['Electronics'] ?? 0,
                        $data['Furniture'] ?? 0,
                        $data['Books'] ?? 0,
                        $data['Supplies'] ?? 0,
                        $data['Equipment'] ?? 0,
                        $data['Other'] ?? 0,
                    ],
                    'backgroundColor' => [
                        '#3b82f6', // blue
                        '#8b5cf6', // purple
                        '#10b981', // green
                        '#f59e0b', // yellow
                        '#ef4444', // red
                        '#6b7280', // gray
                    ],
                ],
            ],
            'labels' => ['Electronics', 'Furniture', 'Books', 'Supplies', 'Equipment', 'Other'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}

