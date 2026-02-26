<?php

namespace App\Filament\Widgets;

use App\Helpers\EthiopianDateHelper;
use App\Models\AcademicYear;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ActiveAcademicYearWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $active = AcademicYear::query()->where('is_active', true)->first();

        if (! $active) {
            return [
                Stat::make('Academic Year', 'No Active Year')
                    ->description('Set an active year to continue')
                    ->descriptionIcon('heroicon-m-exclamation-triangle')
                    ->color('danger'),
            ];
        }

        $helper = app(EthiopianDateHelper::class);
        $today = now();
        $daysRemaining = $today->diffInDays($active->end_date);

        return [
            Stat::make('Academic Year', $active->name)
                ->description("{$helper->toString($active->start_date)} – {$helper->toString($active->end_date)}")
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('success'),

            Stat::make('Days Remaining', $daysRemaining)
                ->description($daysRemaining > 0 ? 'Until year ends' : 'Year ended')
                ->descriptionIcon('heroicon-m-clock')
                ->color($daysRemaining > 30 ? 'success' : ($daysRemaining > 0 ? 'warning' : 'danger')),
        ];
    }

    protected function getColumns(): int
    {
        return 2;
    }
}

