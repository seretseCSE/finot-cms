<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StockMovementType;
use App\Http\Controllers\Concerns\HandlesListQueries;
use App\Http\Controllers\Concerns\ResolvesInventoryScope;
use App\Http\Controllers\Controller;
use App\Http\Resources\StockMovementResource;
use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Services\Inventory\StockLedger;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * The ledger surface: reading the bin card, plus the storekeeper's direct
 * movements — receive (no PO needed), return to store, adjustment and
 * write-off. Requisition issues and PO receipts post through their own
 * controllers; everything lands in the same append-only ledger.
 */
class StockMovementController extends Controller
{
    use HandlesListQueries;
    use ResolvesInventoryScope;

    private const LIST_WITH = ['item:id,name,unit', 'creator:id,name'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $school = $this->inventorySchool($request, ['inventory.view', 'inventory.manage']);
        $branch = $this->activeBranchOrNull($request);

        $query = StockMovement::query()
            ->where('school_id', $school->id)
            ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
            ->when($this->branchFilterId($request, $branch), fn ($q, int $id) => $q->where('branch_id', $id))
            ->when($request->integer('inventory_item_id'), fn ($q, int $id) => $q->where('inventory_item_id', $id))
            ->when($this->csvValues($request, 'type'), fn ($q, array $types) => $q->whereIn('type', $types))
            ->tap(fn ($q) => $this->applyDateRange($q, $request, 'created_at', 'from', 'to'))
            ->with(self::LIST_WITH);

        if ($branch === null) {
            $query->with('branch:id,name');
        }

        $this->applySort($query, $request, ['created_at', 'quantity_change', 'quantity_after', 'type'], 'created_at');

        return StockMovementResource::collection($query->paginate($this->perPage($request)));
    }

    /** Goods arriving without a PO — the everyday Ethiopian store reality. */
    public function receive(Request $request): JsonResponse
    {
        return $this->post($request, StockMovementType::Receive, [
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'supplier_name' => ['nullable', 'string', 'max:160'],
            'reference' => ['nullable', 'string', 'max:120'],
            'expense_id' => ['nullable', 'integer'],
        ], 'Stock received.');
    }

    /**
     * Direct issue without a requisition — the storekeeper hands something
     * over on the spot. Who received it is required: an issue with no name
     * is stock that simply vanished.
     */
    public function issue(Request $request): JsonResponse
    {
        return $this->post($request, StockMovementType::Issue, [
            'recipient' => ['required', 'string', 'max:160'],
        ], 'Items issued.');
    }

    /** Items coming back from a recipient (unused chalk boxes, a returned kit). */
    public function returnStock(Request $request): JsonResponse
    {
        return $this->post($request, StockMovementType::Return, [
            'recipient' => ['nullable', 'string', 'max:160'],
        ], 'Return recorded.');
    }

    /** Damaged / expired / lost stock leaving the books, reason required. */
    public function writeOff(Request $request): JsonResponse
    {
        return $this->post($request, StockMovementType::WriteOff, [
            'note' => ['required', 'string', 'max:255'],
        ], 'Write-off recorded.');
    }

    /** Signed correction (found surplus / uncounted shrinkage), reason required. */
    public function adjust(Request $request): JsonResponse
    {
        return $this->post($request, StockMovementType::Adjustment, [
            'note' => ['required', 'string', 'max:255'],
        ], 'Adjustment posted.', signed: true);
    }

    /**
     * @param  array<string, mixed>  $extraRules
     */
    private function post(Request $request, StockMovementType $type, array $extraRules, string $message, bool $signed = false): JsonResponse
    {
        $branch = $this->targetBranch($request);

        abort_unless(
            $request->user()->hasPermissionForScope('inventory.manage', $branch->school_id, $branch->id),
            403,
        );

        $data = $request->validate([
            'inventory_item_id' => [
                'required',
                Rule::exists('inventory_items', 'id')
                    ->where('school_id', $branch->school_id)
                    ->whereNull('deleted_at'),
            ],
            'quantity' => $signed
                ? ['required', 'numeric', 'not_in:0', 'min:-9999999999', 'max:9999999999']
                : ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'note' => ['nullable', 'string', 'max:255'],
            ...$extraRules,
        ]);

        // An expense link ties the receipt to the cashbook row that paid for
        // it — it must be the same school's expense.
        if (($data['expense_id'] ?? null) !== null) {
            $ok = Expense::query()
                ->whereKey($data['expense_id'])
                ->where('school_id', $branch->school_id)
                ->exists();

            abort_unless($ok, 422, 'Pick an expense from this school.');
        }

        $item = InventoryItem::query()->findOrFail($data['inventory_item_id']);

        $movement = app(StockLedger::class)->post(
            $branch->school_id,
            $branch->id,
            $item,
            $type,
            (float) $data['quantity'],
            $data,
            $request->user()->id,
        );

        ActivityLogger::log(
            $request->user(),
            'inventory.stock_'.$type->value,
            $movement,
            ['item_id' => $item->id, 'quantity' => (float) $data['quantity']],
            $branch->school_id,
            $branch->id,
        );

        return (new StockMovementResource($movement->load(self::LIST_WITH)))
            ->additional(['message' => $message])
            ->response()
            ->setStatusCode(201);
    }
}
