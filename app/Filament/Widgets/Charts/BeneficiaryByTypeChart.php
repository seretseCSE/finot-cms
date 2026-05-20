<?php

namespace App\Filament\Widgets\Charts;

use App\Models\Beneficiary;
use Filament\Widgets\DoughnutChartWidget;

class BeneficiaryByTypeChart extends DoughnutChartWidget
{
    protected int | string | array $columnSpan = 1;

    protected ?string $heading = 'Beneficiaries by Type';

    protected function getData(): array
    {
        $data = Beneficiary::selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        return [
            'datasets' => [
                [
                    'data' => array_values($data),
                    'backgroundColor' => ['#3b82f6', '#22c55e', '#f97316'],
                ],
            ],
            'labels' => array_keys($data),
        ];
    }
}
