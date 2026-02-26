<?php

namespace App\Filament\Widgets;

use App\Models\Rehearsal;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class UpcomingRehearsalsWidget extends BaseWidget
{
    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        // Only show to Mezmur Head, Worship Monitor, Admin, Superadmin
        if (!Auth::user()?->hasRole(['mezmur_head', 'worship_monitor', 'admin', 'superadmin'])) {
            return $table->query(Rehearsal::whereRaw('1 = 0'));
        }

        return $table
            ->query(
                Rehearsal::where('status', 'Scheduled')
                    ->where('date_time', '>', now())
                    ->orderBy('date_time', 'asc')
                    ->limit(3)
            )
            ->columns([
                Tables\Columns\TextColumn::make('location')
                    ->label('Location')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('ethiopian_date')
                    ->label('Date')
                    ->sortable(),

                Tables\Columns\TextColumn::make('formatted_time')
                    ->label('Time')
                    ->sortable(),
            ])
            ->paginated(false)
            ->heading('Upcoming Rehearsals')
            ->description('Next 3 scheduled rehearsals');
    }
}

