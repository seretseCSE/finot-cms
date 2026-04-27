<?php

namespace App\Filament\Resources\StockMovementResource\Pages;

use App\Filament\Resources\StockMovementResource;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditStockMovement extends EditRecord
{
    protected static string $resource = StockMovementResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $original = $this->record;
        $itemId = $data['item_id'] ?? null;

        if (! $itemId) {
            return $data;
        }

        $item = InventoryItem::find($itemId);
        if (! $item) {
            return $data;
        }

        if (($data['movement_type'] ?? null) === 'Stock Out') {
            $available = $item->current_stock;

            if ($original->movement_type === 'Stock Out' && $original->item_id == $itemId) {
                $available += (float) $original->quantity;
            }

            if ($data['quantity'] > $available) {
                throw ValidationException::withMessages([
                    'data.quantity' => "The quantity exceeds the available stock of {$available}.",
                ]);
            }
        }

        if (($data['movement_type'] ?? null) === 'Stock In' && ($data['sub_type'] ?? null) === 'Return') {
            $totalOut = (float) InventoryMovement::where('item_id', $itemId)->where('movement_type', 'Stock Out')->sum('quantity');
            $totalReturns = (float) InventoryMovement::where('item_id', $itemId)->where('movement_type', 'Stock In')->where('sub_type', 'Return')->sum('quantity');

            $allowed = $totalOut - $totalReturns;
            if ($original->movement_type === 'Stock In' && $original->sub_type === 'Return' && $original->item_id == $itemId) {
                $allowed += (float) $original->quantity;
            }

            if ($data['quantity'] > $allowed) {
                throw ValidationException::withMessages([
                    'data.quantity' => "The return quantity cannot exceed the total stock out amount of {$allowed}.",
                ]);
            }
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
