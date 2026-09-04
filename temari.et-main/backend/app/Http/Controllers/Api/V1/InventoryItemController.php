<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\StockMovementType;
use App\Http\Controllers\Concerns\HandlesListQueries;
use App\Http\Controllers\Concerns\ResolvesInventoryScope;
use App\Http\Controllers\Controller;
use App\Http\Resources\InventoryItemResource;
use App\Models\AssetUnit;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\Requisition;
use App\Models\School;
use App\Models\StockMovement;
use App\Services\Inventory\StockLedger;
use App\Support\ActivityLogger;
use App\Support\InventoryUnits;
use App\Support\PublicId;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The item master (school-owned catalog) with the active scope's stock
 * aggregated onto each row. Requesters browse it to build a requisition;
 * only inventory.manage edits it.
 */
class InventoryItemController extends Controller
{
    use HandlesListQueries;
    use ResolvesInventoryScope;

    public function index(Request $request): AnonymousResourceCollection
    {
        $school = $this->inventorySchool($request, ['inventory.view', 'inventory.manage', 'inventory.request']);
        $branch = $this->activeBranchOrNull($request);
        $levelBranchId = $branch?->id ?? $this->branchFilterId($request, $branch);

        $query = $this->baseQuery($request, $school->id)
            ->with('category:id,name,icon')
            ->withSum([
                'stockLevels as quantity_on_hand' => fn ($q) => $q
                    ->when($levelBranchId, fn ($qq, int $id) => $qq->where('branch_id', $id)),
            ], 'quantity_on_hand');

        $this->applySort($query, $request, ['name', 'unit', 'quantity_on_hand', 'reorder_level', 'created_at'], 'name', 'asc');

        return InventoryItemResource::collection($query->paginate($this->perPage($request)));
    }

    /** Tiles above the register: catalog size, low stock, open paperwork. */
    public function stats(Request $request): JsonResponse
    {
        $school = $this->inventorySchool($request, ['inventory.view', 'inventory.manage']);
        $branch = $this->activeBranchOrNull($request);
        $levelBranchId = $branch?->id ?? $this->branchFilterId($request, $branch);

        $itemCount = InventoryItem::query()
            ->where('school_id', $school->id)
            ->where('is_active', true)
            ->count();

        $lowStock = $this->lowStockCount($school->id, $levelBranchId);

        $docScope = fn (Builder $q) => $q
            ->where('school_id', $school->id)
            ->when($levelBranchId, fn ($qq, int $id) => $qq->where('branch_id', $id));

        return response()->json([
            'data' => [
                'item_count' => $itemCount,
                'low_stock_count' => $lowStock,
                'pending_requisitions' => Requisition::query()->tap($docScope)->where('status', 'pending')->count(),
                'open_purchase_orders' => PurchaseOrder::query()->tap($docScope)->whereIn('status', ['pending', 'approved'])->count(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $school = $this->inventorySchool($request, ['inventory.manage']);

        $data = $this->validatePayload($request, $school->id);

        $item = InventoryItem::create([...$data, 'school_id' => $school->id]);

        return (new InventoryItemResource($item->load('category:id,name,icon')))
            ->additional(['message' => 'Item created.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * The guided "Add to store" flow: create the item AND put it on the
     * shelf in one transaction. Consumables land with their opening stock;
     * asset items register their tagged units and the matching ledger
     * receive together — so the two books can never disagree at birth.
     */
    public function quickAdd(Request $request): JsonResponse
    {
        $school = $this->inventorySchool($request, ['inventory.manage']);

        $data = $this->validatePayload($request, $school->id);

        $extra = $request->validate([
            'opening_quantity' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'supplier_name' => ['nullable', 'string', 'max:160'],
            'units' => ['nullable', 'integer', 'min:0', 'max:100'],
            'serial_numbers' => ['nullable', 'array', 'max:100'],
            'serial_numbers.*' => ['nullable', 'string', 'max:120'],
            'condition' => ['sometimes', Rule::enum(AssetCondition::class)],
        ]);

        $isAsset = (bool) ($data['is_asset'] ?? false);
        $unitCount = $isAsset ? (int) ($extra['units'] ?? 0) : 0;
        // For assets the units ARE the opening stock; consumables use the qty.
        $opening = $isAsset ? $unitCount : (float) ($extra['opening_quantity'] ?? 0);

        // Stock needs a branch; a bare catalog row does not.
        $branch = $opening > 0 ? $this->targetBranch($request) : null;

        if ($branch !== null) {
            abort_unless(
                $request->user()->hasPermissionForScope('inventory.manage', $branch->school_id, $branch->id),
                403,
            );
            abort_unless($branch->school_id === $school->id, 422, 'Pick a branch of this school.');
        }

        $item = DB::transaction(function () use ($data, $extra, $school, $branch, $opening, $unitCount, $request): InventoryItem {
            $item = InventoryItem::create([...$data, 'school_id' => $school->id]);

            if ($branch !== null && $opening > 0) {
                app(StockLedger::class)->post(
                    $school->id,
                    $branch->id,
                    $item,
                    StockMovementType::Receive,
                    $opening,
                    [
                        'unit_cost' => $extra['unit_cost'] ?? null,
                        'supplier_name' => $extra['supplier_name'] ?? null,
                        'note' => 'Opening stock',
                    ],
                    $request->user()->id,
                );
            }

            if ($branch !== null && $unitCount > 0) {
                for ($i = 0; $i < $unitCount; $i++) {
                    AssetUnit::create([
                        'school_id' => $school->id,
                        'branch_id' => $branch->id,
                        'inventory_item_id' => $item->id,
                        'tag' => PublicId::generate('asset_units', 'tag'),
                        'serial_number' => $extra['serial_numbers'][$i] ?? null,
                        'condition' => $extra['condition'] ?? AssetCondition::Good->value,
                        'status' => AssetStatus::InStore,
                        'unit_cost' => $extra['unit_cost'] ?? null,
                    ]);
                }
            }

            return $item;
        });

        ActivityLogger::log($request->user(), 'inventory.item_quick_added', $item, [
            'opening' => $opening,
            'units' => $unitCount,
        ], $school->id, $branch?->id);

        return (new InventoryItemResource($item->load('category:id,name,icon')))
            ->additional(['message' => 'Item added to the store.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Suggest the next item code: a prefix read off the category's name
     * (Latin letters only — custom Amharic categories fall back to ITM) plus
     * the school's next free number for that prefix. A suggestion, never a
     * reservation — the storekeeper may overwrite it.
     */
    public function nextCode(Request $request): JsonResponse
    {
        $school = $this->inventorySchool($request, ['inventory.manage']);

        $data = $request->validate([
            'inventory_category_id' => [
                'required',
                Rule::exists('inventory_categories', 'id')
                    ->where(fn ($q) => $q->where(fn ($qq) => $qq->whereNull('school_id')->orWhere('school_id', $school->id)))
                    ->whereNull('deleted_at'),
            ],
        ]);

        $name = (string) InventoryCategory::query()->whereKey($data['inventory_category_id'])->value('name');
        $letters = preg_replace('/[^A-Za-z]/', '', $name) ?: 'ITM';
        $prefix = strtoupper(substr($letters, 0, 3));

        $max = InventoryItem::query()
            ->withTrashed()
            ->where('school_id', $school->id)
            ->where('code', 'like', "{$prefix}-%")
            ->pluck('code')
            ->map(fn (?string $code): int => (int) substr((string) $code, strlen($prefix) + 1))
            ->max() ?? 0;

        return response()->json([
            'data' => ['code' => sprintf('%s-%04d', $prefix, $max + 1)],
        ]);
    }

    public function update(Request $request, InventoryItem $inventoryItem): InventoryItemResource
    {
        $this->authorizeRow($request, $inventoryItem);

        $inventoryItem->update($this->validatePayload($request, $inventoryItem->school_id, $inventoryItem->id));

        return (new InventoryItemResource($inventoryItem->load('category:id,name,icon')))
            ->additional(['message' => 'Item saved.']);
    }

    public function destroy(Request $request, InventoryItem $inventoryItem): JsonResponse
    {
        $this->authorizeRow($request, $inventoryItem);

        // An item with ledger history holds the branch's audit trail —
        // deactivate it so the bin card survives.
        if ($inventoryItem->stockLevels()->where('quantity_on_hand', '>', 0)->exists()) {
            throw ValidationException::withMessages([
                'item' => ['This item still has stock on hand — issue or write it off first.'],
            ]);
        }

        if (StockMovement::query()->where('inventory_item_id', $inventoryItem->id)->exists()) {
            throw ValidationException::withMessages([
                'item' => ['This item has ledger history — deactivate it instead of deleting.'],
            ]);
        }

        $inventoryItem->delete();

        return response()->json(['message' => 'Item deleted.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, int $schoolId, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'inventory_category_id' => [
                'required',
                Rule::exists('inventory_categories', 'id')
                    ->where(fn ($q) => $q->where(fn ($qq) => $qq->whereNull('school_id')->orWhere('school_id', $schoolId)))
                    ->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:160'],
            'code' => ['nullable', 'string', 'max:60'],
            'unit' => ['required', Rule::in(InventoryUnits::ALL)],
            'is_asset' => ['sometimes', 'boolean'],
            'reorder_level' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        foreach (['name' => 'An item with this name already exists.', 'code' => 'An item with this code already exists.'] as $field => $message) {
            if (($data[$field] ?? null) === null) {
                continue;
            }

            $exists = InventoryItem::query()
                ->where('school_id', $schoolId)
                ->where($field, 'ilike', $data[$field])
                ->when($ignoreId, fn ($q, int $id) => $q->whereKeyNot($id))
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([$field => [$message]]);
            }
        }

        return $data;
    }

    private function authorizeRow(Request $request, InventoryItem $item): void
    {
        abort_unless(
            $request->user()->hasPermissionForScope('inventory.manage', $item->school_id, $this->activeBranchOrNull($request)?->id),
            403,
        );
    }

    private function lowStockCount(int $schoolId, ?int $branchId): int
    {
        return InventoryItem::query()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->whereNotNull('reorder_level')
            ->whereRaw(
                '(SELECT COALESCE(SUM(sl.quantity_on_hand), 0) FROM stock_levels sl WHERE sl.inventory_item_id = inventory_items.id'
                .($branchId !== null ? ' AND sl.branch_id = ?' : '')
                .') <= reorder_level',
                $branchId !== null ? [$branchId] : [],
            )
            ->count();
    }

    /**
     * @return Builder<InventoryItem>
     */
    private function baseQuery(Request $request, int $schoolId): Builder
    {
        $branch = $this->activeBranchOrNull($request);
        $levelBranchId = $branch?->id ?? $this->branchFilterId($request, $branch);

        return InventoryItem::query()
            ->where('school_id', $schoolId)
            ->when($this->csvIds($request, 'inventory_category_id'), fn ($q, array $ids) => $q->whereIn('inventory_category_id', $ids))
            ->tap(fn ($q) => $this->applyBooleanFilter($q, $request, 'is_asset', 'is_asset'))
            ->when(! $request->boolean('include_inactive'), fn ($q) => $q->where('is_active', true))
            ->when($request->boolean('low_stock'), fn ($q) => $q
                ->whereNotNull('reorder_level')
                ->whereRaw(
                    '(SELECT COALESCE(SUM(sl.quantity_on_hand), 0) FROM stock_levels sl WHERE sl.inventory_item_id = inventory_items.id'
                    .($levelBranchId !== null ? ' AND sl.branch_id = ?' : '')
                    .') <= reorder_level',
                    $levelBranchId !== null ? [$levelBranchId] : [],
                ))
            ->tap(fn ($q) => $this->applySearch($q, $request, fn ($w, string $n) => $w
                ->where('name', 'ilike', $this->needle($n))
                ->orWhere('code', 'ilike', $this->needle($n))));
    }
}
