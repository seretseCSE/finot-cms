<?php

namespace App\Filament\Widgets;

use App\Models\ClassModel;
use App\Models\StudentEnrollment;
use App\Models\AcademicYear;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class EnrollmentStatsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $activeYear = AcademicYear::query()->where('is_active', true)->first();

        if (! $activeYear) {
            return [
                Stat::make('Total Enrolled', 0)
                    ->description('No active academic year')
                    ->descriptionIcon('heroicon-m-exclamation-triangle')
                    ->color('danger'),
            ];
        }

        $total = StudentEnrollment::query()
            ->where('academic_year_id', $activeYear->id)
            ->where('status', 'Enrolled')
            ->count();

        $byClass = StudentEnrollment::query()
            ->where('academic_year_id', $activeYear->id)
            ->where('status', 'Enrolled')
            ->join('classes', 'student_enrollments.class_id', '=', 'classes.id')
            ->selectRaw('classes.name as class_name, COUNT(*) as count')
            ->groupBy('classes.name')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->mapWithKeys(fn ($row) => [$row->class_name => $row->count])
            ->all();

        $stats = [
            Stat::make('Total Enrolled', $total)
                ->description('Active students this year')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),
        ];

        foreach ($byClass as $className => $count) {
            $stats[] = Stat::make($className, $count)
                ->description('Students in this class')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('gray');
        }

        return $stats;
    }

    protected function getColumns(): int
    {
        return 2;
    }
}

