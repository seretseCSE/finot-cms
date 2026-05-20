<?php

namespace App\Filament\Widgets\Charts;

use App\Models\Rehearsal;
use Filament\Widgets\LineChartWidget;

class RehearsalAttendanceChart extends LineChartWidget
{
    protected int | string | array $columnSpan = 2;

    protected ?string $heading = 'Rehearsal Attendance Trend';

    protected function getData(): array
    {
        $labels = [];
        $data = [];

        $rehearsals = Rehearsal::where('date', '>=', now()->subDays(30))
            ->orderBy('date')
            ->get();

        foreach ($rehearsals as $rehearsal) {
            $labels[] = $rehearsal->date?->format('M j') ?? 'N/A';
            $total = $rehearsal->attendances()->count();
            $present = $rehearsal->attendances()->where('status', 'Present')->count();
            $data[] = $total > 0 ? round(($present / $total) * 100, 1) : 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Attendance %',
                    'data' => $data,
                    'borderColor' => '#8b5cf6',
                    'backgroundColor' => '#8b5cf6',
                ],
            ],
            'labels' => $labels,
        ];
    }
}
