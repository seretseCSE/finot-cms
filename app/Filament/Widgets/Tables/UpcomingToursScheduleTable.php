<?php

namespace App\Filament\Widgets\Tables;

use App\Models\Tour;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class UpcomingToursScheduleTable extends TableWidget
{
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Tour::where('tour_date', '>=', today())
                    ->orWhere('status', 'active')
                    ->orderBy('tour_date')
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('place')
                    ->label('Destination'),
                Tables\Columns\TextColumn::make('tour_date')
                    ->date(),
                Tables\Columns\TextColumn::make('cost_per_person')
                    ->money('ETB'),
                Tables\Columns\TextColumn::make('max_capacity')
                    ->label('Capacity'),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
            ])
            ->paginated(false);
    }
}
