<?php

namespace App\Filament\Widgets\Charts;

use App\Models\StudentAttendance;
use Filament\Widgets\LineChartWidget;

class AttendanceTrendChart extends LineChartWidget
{
    protected int | string | array $columnSpan = 2;

    protected ?string $heading = 'Student Attendance Rate (30 Days)';

    protected function getData(): array
    {
        $labels = [];
        $data = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->format('M j');

            $total = StudentAttendance::whereHas('session', fn ($q) => $q->whereDate('session_date', $date))
                ->count();

            $present = StudentAttendance::whereHas('session', fn ($q) => $q->whereDate('session_date', $date))
                ->where('status', 'Present')
                ->count();

            $rate = $total > 0 ? round(($present / $total) * 100, 1) : 0;
            $data[] = $rate;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Attendance %',
                    'data' => $data,
                    'borderColor' => '#22c55e',
                    'backgroundColor' => '#22c55e',
                ],
            ],
            'labels' => $labels,
        ];
    }
}
