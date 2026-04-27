<?php

namespace App\Filament\Resources\StockMovementResource\Pages;

use App\Filament\Resources\StockMovementResource;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateStockMovement extends CreateRecord
{
    protected static string $resource = StockMovementResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['recorded_by'] = Auth::id();

        $itemId = $data['item_id'] ?? null;
        if (! $itemId) {
            return $data;
        }

        $item = InventoryItem::find($itemId);
        if (! $item) {
            return $data;
        }

        if (($data['movement_type'] ?? null) === 'Stock Out') {
            if ($data['quantity'] > $item->current_stock) {
                throw ValidationException::withMessages([
                    'data.quantity' => "The quantity exceeds the available stock of {$item->current_stock}.",
                ]);
            }
        }

        if (($data['movement_type'] ?? null) === 'Stock In' && ($data['sub_type'] ?? null) === 'Return') {
            $totalOut = (float) InventoryMovement::where('item_id', $itemId)->where('movement_type', 'Stock Out')->sum('quantity');
            $totalReturns = (float) InventoryMovement::where('item_id', $itemId)->where('movement_type', 'Stock In')->where('sub_type', 'Return')->sum('quantity');
            $allowed = $totalOut - $totalReturns;

            if ($data['quantity'] > $allowed) {
                throw ValidationException::withMessages([
                    'data.quantity' => "The return quantity cannot exceed the total stock out amount of {$allowed}.",
                ]);
            }
        }

        return $data;
    }
}
