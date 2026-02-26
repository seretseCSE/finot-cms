<?php

namespace App\Filament\Widgets;

use App\Helpers\EthiopianDateHelper;
use App\Models\AttendanceSession;
use App\Models\StudentAttendance;
use App\Models\AcademicYear;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class RecentAttendanceWidget extends BaseWidget
{
    protected static ?int $sort = 5;

    public function getTableRecords(): \Illuminate\Support\Collection|\Illuminate\Contracts\Pagination\Paginator|\Illuminate\Contracts\Pagination\CursorPaginator
    {
        $activeYear = AcademicYear::query()->where('is_active', true)->first();

        if (! $activeYear) {
            return AttendanceSession::query()->whereRaw('1 = 0');
        }

        return AttendanceSession::query()
            ->with(['class'])
            ->where('academic_year_id', $activeYear->id)
            ->where('session_date', '>=', now()->subDays(7))
            ->latest('session_date')
            ->limit(10);
    }

    protected function getTableColumns(): array
    {
        return [
            \Filament\Tables\Columns\TextColumn::make('class.name')
                ->label('Class')
                ->sortable(),

            \Filament\Tables\Columns\TextColumn::make('session_date')
                ->label('Date')
                ->formatStateUsing(fn ($state) => $state ? app(EthiopianDateHelper::class)->toString($state) : '')
                ->sortable(),

            \Filament\Tables\Columns\TextColumn::make('attendance_summary')
                ->label('Attendance')
                ->state(function (AttendanceSession $record): string {
                    $present = $record->studentAttendance()->where('status', 'Present')->count();
                    $total = $record->studentAttendance()->count();
                    $rate = $total > 0 ? round(($present / $total) * 100, 1) : 0;
                    return "{$present}/{$total} ({$rate}%)";
                }),

            \Filament\Tables\Columns\BadgeColumn::make('status')
                ->colors([
                    'success' => 'Open',
                    'warning' => 'Completed',
                    'danger' => 'Locked',
                ]),
        ];
    }

    protected function getDefaultTableRecordsPerPage(): int
    {
        return 10;
    }
}

