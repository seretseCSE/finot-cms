<?php

namespace App\Services\Inventory;

use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Services\Notify\Notifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The ONLY writer of stock. post() row-locks the branch × item stock_level,
 * appends the movement with its running balance (the bin-card line) and
 * mirrors quantity_on_hand — so two issues can never race each other below
 * zero. Issues and write-offs refuse to overdraw; falling to/under the
 * item's reorder level notifies the store staff exactly once per breach.
 */
class StockLedger
{
    /**
     * @param  array{unit_cost?: float|string|null, requisition_id?: int|null, purchase_order_id?: int|null, stock_take_id?: int|null, expense_id?: int|null, supplier_name?: string|null, recipient?: string|null, reference?: string|null, note?: string|null}  $attrs
     */
    public function post(
        int $schoolId,
        int $branchId,
        InventoryItem $item,
        StockMovementType $type,
        float $quantity,
        array $attrs = [],
        ?int $createdBy = null,
    ): StockMovement {
        if ($type === StockMovementType::Adjustment) {
            if ($quantity == 0.0) {
                throw ValidationException::withMessages([
                    'quantity' => ['An adjustment must change the quantity.'],
                ]);
            }
        } elseif ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => ['Quantity must be greater than zero.'],
            ]);
        }

        // Adjustments carry their own sign; every other type derives it.
        $change = match ($type) {
            StockMovementType::Receive, StockMovementType::Return => $quantity,
            StockMovementType::Issue, StockMovementType::WriteOff => -$quantity,
            StockMovementType::Adjustment => $quantity,
        };

        return DB::transaction(function () use ($schoolId, $branchId, $item, $type, $change, $attrs, $createdBy): StockMovement {
            StockLevel::query()->firstOrCreate([
                'branch_id' => $branchId,
                'inventory_item_id' => $item->id,
            ], ['school_id' => $schoolId]);

            $level = StockLevel::query()
                ->where('branch_id', $branchId)
                ->where('inventory_item_id', $item->id)
                ->lockForUpdate()
                ->firstOrFail();

            $before = (float) $level->quantity_on_hand;
            $after = round($before + $change, 2);

            if ($after < 0) {
                throw ValidationException::withMessages([
                    'quantity' => ["Not enough stock: only {$before} {$item->unit} of {$item->name} on hand."],
                ]);
            }

            $movement = StockMovement::create([
                'school_id' => $schoolId,
                'branch_id' => $branchId,
                'inventory_item_id' => $item->id,
                'type' => $type,
                'quantity' => abs($change),
                'quantity_change' => $change,
                'quantity_after' => $after,
                'unit_cost' => $attrs['unit_cost'] ?? null,
                'requisition_id' => $attrs['requisition_id'] ?? null,
                'purchase_order_id' => $attrs['purchase_order_id'] ?? null,
                'stock_take_id' => $attrs['stock_take_id'] ?? null,
                'expense_id' => $attrs['expense_id'] ?? null,
                'supplier_name' => $attrs['supplier_name'] ?? null,
                'recipient' => $attrs['recipient'] ?? null,
                'reference' => $attrs['reference'] ?? null,
                'note' => $attrs['note'] ?? null,
                'created_by' => $createdBy,
            ]);

            // The cached quantity lives outside fillable on purpose — no
            // mass-assignment path may move stock; the single writer forceFills.
            $level->forceFill(['quantity_on_hand' => $after])->save();

            $this->alertIfLow($item, $schoolId, $branchId, $before, $after);

            return $movement;
        });
    }

    /**
     * Notify store staff once per breach: only when the balance CROSSES down
     * to/under the reorder level (a second issue while already low stays
     * quiet; the dedupeKey folds any near-simultaneous repeats).
     */
    private function alertIfLow(InventoryItem $item, int $schoolId, int $branchId, float $before, float $after): void
    {
        $reorder = $item->reorder_level === null ? null : (float) $item->reorder_level;

        if ($reorder === null || $after > $reorder || $before <= $reorder) {
            return;
        }

        app(Notifier::class)->toStaff($schoolId, $branchId, 'inventory.manage', 'inventory.low_stock', [
            'item' => $item->name,
            'quantity' => number_format($after, 2),
            'unit' => $item->unit,
        ], [
            'link' => '/inventory?tab=items',
            'dedupeKey' => "inventory.low_stock:{$branchId}:{$item->id}",
        ]);
    }
}
