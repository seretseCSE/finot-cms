<?php

namespace App\Filament\Resources\LossRecordResource\Pages;

use App\Filament\Resources\LossRecordResource;
use App\Models\InventoryItem;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateLossRecord extends CreateRecord
{
    protected static string $resource = LossRecordResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['recorded_by'] = Auth::id();

        $itemId = $data['item_id'] ?? null;
        if ($itemId) {
            $item = InventoryItem::find($itemId);
            if ($item && $data['quantity'] > $item->current_stock) {
                throw ValidationException::withMessages([
                    'data.quantity' => "The quantity exceeds the available stock of {$item->current_stock}.",
                ]);
            }
        }

        return $data;
    }
}
