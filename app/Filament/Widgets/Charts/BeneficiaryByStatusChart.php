<?php

namespace App\Filament\Widgets\Charts;

use App\Models\Beneficiary;
use Filament\Widgets\DoughnutChartWidget;
use Illuminate\Support\Facades\Cache;

class BeneficiaryByStatusChart extends DoughnutChartWidget
{
    protected int | string | array $columnSpan = 1;

    protected ?string $heading = 'Beneficiaries by Status';

    protected function getData(): array
    {
        $data = Cache::remember('dashboard_beneficiary_status_chart', 300, fn () =>
            Beneficiary::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray()
        );

        return [
            'datasets' => [
                [
                    'data' => array_values($data),
                    'backgroundColor' => ['#22c55e', '#f97316', '#6b7280', '#3b82f6'],
                ],
            ],
            'labels' => array_keys($data),
        ];
    }
}
