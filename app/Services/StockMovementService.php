<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use Illuminate\Validation\ValidationException;

class StockMovementService
{
    /**
     * Validate stock movement data before save.
     *
     * @param array $data The form data
     * @param InventoryMovement $original The original movement record
     * @return array The validated data
     * @throws ValidationException
     */
    public function validateStockMovement(array $data, InventoryMovement $original): array
    {
        $itemId = $data['item_id'] ?? null;

        if (!$itemId) {
            return $data;
        }

        $item = InventoryItem::find($itemId);
        if (!$item) {
            return $data;
        }

        if (($data['movement_type'] ?? null) === 'Stock Out') {
            $this->checkStockAvailability($item, $data['quantity'], $original);
        }

        if (($data['movement_type'] ?? null) === 'Stock In' && ($data['sub_type'] ?? null) === 'Return') {
            $this->checkReturnLimit($item, $data['quantity'], $original);
        }

        return $data;
    }

    /**
     * Check if stock is available for the requested quantity.
     *
     * @param InventoryItem $item The inventory item
     * @param float $quantity The requested quantity
     * @param InventoryMovement $original The original movement record
     * @return void
     * @throws ValidationException
     */
    public function checkStockAvailability(InventoryItem $item, float $quantity, InventoryMovement $original): void
    {
        $available = $item->current_stock;

        if ($original->movement_type === 'Stock Out' && $original->item_id == $item->id) {
            $available += (float) $original->quantity;
        }

        if ($quantity > $available) {
            throw ValidationException::withMessages([
                'data.quantity' => "The quantity exceeds the available stock of {$available}.",
            ]);
        }
    }

    /**
     * Check if return quantity is within allowed limits.
     *
     * @param InventoryItem $item The inventory item
     * @param float $quantity The return quantity
     * @param InventoryMovement $original The original movement record
     * @return void
     * @throws ValidationException
     */
    public function checkReturnLimit(InventoryItem $item, float $quantity, InventoryMovement $original): void
    {
        $totalOut = (float) InventoryMovement::where('item_id', $item->id)
            ->where('movement_type', 'Stock Out')
            ->sum('quantity');

        $totalReturns = (float) InventoryMovement::where('item_id', $item->id)
            ->where('movement_type', 'Stock In')
            ->where('sub_type', 'Return')
            ->sum('quantity');

        $allowed = $totalOut - $totalReturns;

        if ($original->movement_type === 'Stock In' && $original->sub_type === 'Return' && $original->item_id == $item->id) {
            $allowed += (float) $original->quantity;
        }

        if ($quantity > $allowed) {
            throw ValidationException::withMessages([
                'data.quantity' => "The return quantity cannot exceed the total stock out amount of {$allowed}.",
            ]);
        }
    }
}
