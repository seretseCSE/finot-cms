<?php

namespace App\Filament\Widgets\Stats;

use App\Filament\Resources\StudentEnrollmentResource;
use App\Filament\Support\ClickableStat;
use App\Models\StudentEnrollment;
use Filament\Widgets\StatsOverviewWidget;
use Illuminate\Support\Facades\Cache;

class ActiveEnrollmentsWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $count = Cache::remember('dashboard_active_enrollments', 300, fn () =>
            StudentEnrollment::whereHas('academicYear', fn ($q) => $q->where('status', 'Active'))->count()
        );

        return [
            ClickableStat::make('Active Enrollments', $count, ClickableStat::resourceUrl(StudentEnrollmentResource::class))
                ->icon('heroicon-o-academic-cap')
                ->color('primary'),
        ];
    }
}
