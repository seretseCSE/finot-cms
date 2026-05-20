<?php

namespace App\Filament\Widgets\Tables;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentContentTable extends TableWidget
{
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                \App\Models\MediaItem::latest()->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Title'),
                Tables\Columns\TextColumn::make('type')
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')
                    ->date()
                    ->label('Published'),
            ])
            ->paginated(false);
    }
}
