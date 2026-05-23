<?php

namespace App\Filament\Widgets\Charts;

use App\Models\MediaItem;
use Filament\Widgets\DoughnutChartWidget;
use Illuminate\Support\Facades\Cache;

class MediaByCategoryChart extends DoughnutChartWidget
{
    protected int | string | array $columnSpan = 1;

    protected ?string $heading = 'Media by Category';

    protected function getData(): array
    {
        $data = Cache::remember('dashboard_media_by_category_chart', 300, fn () =>
            MediaItem::selectRaw('category_id, COUNT(*) as count')
                ->groupBy('category_id')
                ->with('category')
                ->get()
                ->mapWithKeys(fn ($item) => [$item->category?->name ?? 'Uncategorized' => $item->count])
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
