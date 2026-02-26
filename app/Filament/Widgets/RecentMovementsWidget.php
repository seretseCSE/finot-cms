<?php

namespace App\Filament\Widgets;

use App\Models\InventoryMovement;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class RecentMovementsWidget extends BaseWidget
{
    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        // Only show to inventory staff, nibret_hisab_head, admin, superadmin
        if (!Auth::user()?->hasRole(['inventory_staff', 'nibret_hisab_head', 'admin', 'superadmin'])) {
            return $table->query(InventoryMovement::whereRaw('1 = 0'));
        }

        return $table
            ->query(
                InventoryMovement::with(['item', 'recordedBy'])
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('item.name')
                    ->label('Item Name')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('movement_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn ($record) => $record->movement_type_color),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Quantity')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('ethiopian_movement_date')
                    ->label('Date')
                    ->sortable(),

                Tables\Columns\TextColumn::make('recordedBy.name')
                    ->label('Recorded By')
                    ->sortable(),
            ])
            ->paginated(false)
            ->heading('Recent Movements')
            ->description('Last 10 inventory movements');
    }
}

