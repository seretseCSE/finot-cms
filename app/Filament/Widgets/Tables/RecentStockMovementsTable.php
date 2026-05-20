<?php

namespace App\Filament\Widgets\Tables;

use App\Models\InventoryMovement;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentStockMovementsTable extends TableWidget
{
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                InventoryMovement::with(['item', 'user'])
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('item.name')
                    ->label('Item'),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state) => $state === 'in' ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('quantity'),
                Tables\Columns\TextColumn::make('recordedBy.name')
                    ->label('By'),
                Tables\Columns\TextColumn::make('created_at')
                    ->since(),
            ])
            ->paginated(false);
    }
}
