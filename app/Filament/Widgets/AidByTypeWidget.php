<?php

namespace App\Filament\Widgets;

use App\Models\AidDistribution;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class AidByTypeWidget extends ChartWidget
{
    protected ?string $heading = 'Aid Distribution by Type';

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $data = AidDistribution::select('aid_type', DB::raw('sum(amount) as total'))
            ->groupBy('aid_type')
            ->orderBy('total', 'desc')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Total Amount (ETB)',
                    'data' => $data->pluck('total')->toArray(),
                    'backgroundColor' => [
                        '#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899'
                    ],
                ],
            ],
            'labels' => $data->pluck('aid_type')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    public static function canView(): bool
    {
        return in_array(auth()->user()->role, ['charity_head', 'internal_relations_head', 'admin', 'superadmin']);
    }
}

