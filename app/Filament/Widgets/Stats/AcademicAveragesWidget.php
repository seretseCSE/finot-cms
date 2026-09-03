<?php

namespace App\Filament\Widgets\Stats;

use App\Filament\Pages\Education\AcademicResultsReport;
use App\Filament\Support\ClickableStat;
use App\Services\Academics\RankingService;
use Filament\Widgets\StatsOverviewWidget;

class AcademicAveragesWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $report = app(RankingService::class)->institutionReport();
        $url = ClickableStat::pageUrl(AcademicResultsReport::class);

        return [
            ClickableStat::make(
                'Institution average',
                $report['average'] !== null ? number_format($report['average'], 1).'%' : '—',
                $url
            )
                ->icon('heroicon-o-chart-bar')
                ->color('primary')
                ->description($report['students'].' students scored'),
        ];
    }
}
