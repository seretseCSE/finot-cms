<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Models\Member;
use App\Models\Contribution;
use App\Models\Tour;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Parent as ParentModel;
use App\Models\Department;
use App\Models\AcademicYear;
use App\Models\StudentEnrollment;
use App\Models\AttendanceSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Filament\Widgets\ChartWidget;

class GlobalOversightChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    public function getHeading(): string
    {
        return 'System Activity Overview';
    }

    protected function getData(): array
    {
        if (!Auth::user()->hasRole('superadmin')) {
            return [];
        }

        // User registrations over last 30 days
        $userRegs = User::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Contributions over last 30 days
        $contributions = Contribution::selectRaw('DATE(created_at) as date, SUM(amount) as total')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'labels' => $userRegs->pluck('date')->map(fn($date) => \Carbon\Carbon::parse($date)->format('M j')),
            'datasets' => [
                [
                    'label' => 'New Users',
                    'data' => $userRegs->pluck('count'),
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'tension' => 0.1,
                ],
                [
                    'label' => 'Contributions (ETB)',
                    'data' => $contributions->pluck('total'),
                    'borderColor' => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'tension' => 0.1,
                    'yAxisID' => 'y1',
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
            ],
            'scales' => [
                'y' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'left',
                    'title' => [
                        'display' => true,
                        'text' => 'Users',
                    ],
                ],
                'y1' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'right',
                    'title' => [
                        'display' => true,
                        'text' => 'Contributions (ETB)',
                    ],
                    'grid' => [
                        'drawOnChartArea' => false,
                    ],
                ],
            ],
        ];
    }
}
