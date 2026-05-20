<?php

namespace App\Filament\Widgets\Charts;

use App\Models\User;
use Filament\Widgets\LineChartWidget;

class UserRegistrationChart extends LineChartWidget
{
    protected int | string | array $columnSpan = 2;

    protected ?string $heading = 'User Registrations (30 Days)';

    protected function getData(): array
    {
        $data = User::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'New Users',
                    'data' => $data->pluck('count')->toArray(),
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => '#3b82f6',
                    'fill' => true,
                ],
            ],
            'labels' => $data->pluck('date')->map(fn ($d) => \Carbon\Carbon::parse($d)->format('M j'))->toArray(),
        ];
    }
}
