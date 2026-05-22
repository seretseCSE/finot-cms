<?php

namespace App\Filament\Widgets\Charts;

use App\Models\StudentAttendance;
use Filament\Widgets\LineChartWidget;

class AttendanceTrendChart extends LineChartWidget
{
    protected int | string | array $columnSpan = 2;

    protected ?string $heading = 'Student Attendance Rate (12 Weeks)';

    protected function getData(): array
    {
        $labels = [];
        $data = [];

        for ($i = 11; $i >= 0; $i--) {
            $startOfWeek = now()->subWeeks($i)->startOfWeek();
            $endOfWeek = now()->subWeeks($i)->endOfWeek();
            $labels[] = $startOfWeek->format('M j');

            $total = StudentAttendance::whereHas('session', fn ($q) => $q->whereBetween('session_date', [$startOfWeek, $endOfWeek]))
                ->count();

            $present = StudentAttendance::whereHas('session', fn ($q) => $q->whereBetween('session_date', [$startOfWeek, $endOfWeek]))
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
