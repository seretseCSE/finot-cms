<?php

namespace App\Filament\Widgets;

use App\Models\MediaItem;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class MediaByVisibilityWidget extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Media by Visibility';

    protected ?int $chartHeight = 300;

    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        // Only show to AV Head, Admin, Superadmin
        if (!Auth::user()?->hasRole(['av_head', 'admin', 'superadmin'])) {
            return [];
        }

        $data = MediaItem::whereNull('deleted_at')
            ->selectRaw('visibility, COUNT(*) as count')
            ->groupBy('visibility')
            ->pluck('count', 'visibility')
            ->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Media Items',
                    'data' => [
                        $data['Public'] ?? 0,
                        $data['Members Only'] ?? 0,
                        $data['Department Only'] ?? 0,
                    ],
                    'backgroundColor' => [
                        '#10b981', // green
                        '#3b82f6', // blue
                        '#8b5cf6', // purple
                    ],
                ],
            ],
            'labels' => ['Public', 'Members Only', 'Department Only'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}

