<?php

namespace App\Filament\Widgets;

use App\Models\InventoryItem;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class LowStockItemsWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        // Only show to inventory staff, nibret_hisab_head, admin, superadmin
        if (!Auth::user()?->hasRole(['inventory_staff', 'nibret_hisab_head', 'admin', 'superadmin'])) {
            return $table->query(InventoryItem::whereRaw('1 = 0'));
        }

        return $table
            ->query(
                InventoryItem::whereNull('deleted_at')
                    ->where('status', 'Active')
                    ->get()
                    ->filter(fn ($item) => $item->current_stock < 5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('item_code')
                    ->label('Item Code')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Item Name')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('current_stock')
                    ->label('Current Stock')
                    ->getStateUsing(fn ($record) => $record->current_stock)
                    ->alignCenter()
                    ->color('danger')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('unit')
                    ->label('Unit')
                    ->sortable(),

                Tables\Columns\TextColumn::make('location')
                    ->label('Location')
                    ->searchable()
                    ->sortable(),
            ])
            ->paginated(false)
            ->heading('Low Stock Items')
            ->description('Items with current stock less than 5 units');
    }
}

