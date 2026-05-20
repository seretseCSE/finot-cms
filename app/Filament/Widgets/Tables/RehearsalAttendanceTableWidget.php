<?php

namespace App\Filament\Widgets\Tables;

use App\Models\RehearsalAttendance;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RehearsalAttendanceTableWidget extends TableWidget
{
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                RehearsalAttendance::with(['rehearsal', 'member'])
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('rehearsal.date')
                    ->label('Date')
                    ->date(),
                Tables\Columns\TextColumn::make('member.full_name')
                    ->label('Member'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'Present' => 'success',
                        'Absent' => 'danger',
                        'Late' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->paginated(false);
    }
}
