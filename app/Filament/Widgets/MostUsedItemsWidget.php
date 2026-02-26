<?php

namespace App\Filament\Widgets;

use App\Models\InventoryMovement;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class MostUsedItemsWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        // Only show to inventory staff, nibret_hisab_head, admin, superadmin
        if (!Auth::user()?->hasRole(['inventory_staff', 'nibret_hisab_head', 'admin', 'superadmin'])) {
            return $table->query(InventoryItem::whereRaw('1 = 0'));
        }

        return $table
            ->query(
                InventoryMovement::select('item_id', \DB::raw('COUNT(*) as usage_count'))
                    ->where('movement_type', 'Stock Out')
                    ->groupBy('item_id')
                    ->orderBy('usage_count', 'desc')
                    ->limit(10)
                    ->with('item')
            )
            ->columns([
                Tables\Columns\TextColumn::make('item.name')
                    ->label('Item Name')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('item.category')
                    ->label('Category')
                    ->badge()
                    ->color(fn ($record) => match($record->item->category) {
                        'Electronics' => 'blue',
                        'Furniture' => 'brown',
                        'Books' => 'green',
                        'Supplies' => 'yellow',
                        'Equipment' => 'purple',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('usage_count')
                    ->label('Usage Count')
                    ->alignCenter()
                    ->sortable(),
            ])
            ->paginated(false)
            ->heading('Most Used Items')
            ->description('Top 10 items by Stock Out frequency');
    }
}

