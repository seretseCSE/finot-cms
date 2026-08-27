<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementType;
use App\Http\Controllers\Concerns\HandlesListQueries;
use App\Http\Controllers\Concerns\ResolvesInventoryScope;
use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Services\Inventory\StockLedger;
use App\Services\Notify\Notifier;
use App\Support\ActivityLogger;
use App\Support\Ethiopia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The OPTIONAL procurement lane — direct receiving (StockMovementController)
 * never requires a PO. When a school does raise one: pending →
 * approved/declined (countersigned, never one's own) → goods land against
 * the approved lines and the PO auto-completes.
 */
class PurchaseOrderController extends Controller
{
    use HandlesListQueries;
    use ResolvesInventoryScope;

    private const LIST_WITH = ['orderer:id,name', 'decider:id,name', 'items.item:id,name,unit'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $school = $this->inventorySchool($request, ['inventory.view', 'inventory.manage', 'inventory.approve']);
        $branch = $this->activeBranchOrNull($request);

        $query = PurchaseOrder::query()
            ->where('school_id', $school->id)
            ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
            ->when($this->branchFilterId($request, $branch), fn ($q, int $id) => $q->where('branch_id', $id))
            ->when($this->csvValues($request, 'status'), fn ($q, array $statuses) => $q->whereIn('status', $statuses))
            ->tap(fn ($q) => $this->applySearch($q, $request, fn ($w, string $n) => $w
                ->where('supplier_name', 'ilike', $this->needle($n))
                ->orWhere('note', 'ilike', $this->needle($n))))
            ->withCount('items')
            ->with(self::LIST_WITH);

        if ($branch === null) {
            $query->with('branch:id,name');
        }

        $this->applySort($query, $request, ['created_at', 'status', 'supplier_name', 'total_cost', 'expected_on'], 'created_at');

        return PurchaseOrderResource::collection($query->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $branch = $this->targetBranch($request);
        $user = $request->user();

        abort_unless(
            $user->hasPermissionForScope('inventory.manage', $branch->school_id, $branch->id),
            403,
        );

        $data = $this->validatePayload($request, $branch->school_id);

        $po = DB::transaction(function () use ($data, $branch, $user): PurchaseOrder {
            $po = PurchaseOrder::create([
                'school_id' => $branch->school_id,
                'branch_id' => $branch->id,
                'supplier_name' => $data['supplier_name'],
                'supplier_phone' => $data['supplier_phone'] ?? null,
                'status' => PurchaseOrderStatus::Pending,
                'expected_on' => $data['expected_on'] ?? null,
                'note' => $data['note'] ?? null,
                'ordered_by' => $user->id,
            ]);

            $this->writeLines($po, $data['items']);

            return $po;
        });

        app(Notifier::class)->toStaff($branch->school_id, $branch->id, 'inventory.approve', 'inventory.po_submitted', [
            'supplier' => $po->supplier_name,
        ], [
            'link' => '/inventory?tab=purchase-orders',
            'exceptUserId' => $user->id,
        ]);

        return (new PurchaseOrderResource($po->load(self::LIST_WITH)->loadCount('items')))
            ->additional(['message' => 'Purchase order submitted — pending approval.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $this->authorizeManage($request, $purchaseOrder);
        $this->assertStatus($purchaseOrder, [PurchaseOrderStatus::Pending], 'Only pending purchase orders can be edited.');

        $data = $this->validatePayload($request, $purchaseOrder->school_id);

        DB::transaction(function () use ($purchaseOrder, $data): void {
            $purchaseOrder->update([
                'supplier_name' => $data['supplier_name'],
                'supplier_phone' => $data['supplier_phone'] ?? null,
                'expected_on' => $data['expected_on'] ?? null,
                'note' => $data['note'] ?? null,
            ]);

            $purchaseOrder->items()->delete();
            $this->writeLines($purchaseOrder, $data['items']);
        });

        return (new PurchaseOrderResource($purchaseOrder->load(self::LIST_WITH)->loadCount('items')))
            ->additional(['message' => 'Purchase order saved.']);
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $this->authorizeManage($request, $purchaseOrder);

        // A pending PO cancels freely; an approved one only while nothing has
        // landed — received goods are ledger history and cannot be unwound.
        $received = $purchaseOrder->items()->where('received_quantity', '>', 0)->exists();

        if (! in_array($purchaseOrder->status, [PurchaseOrderStatus::Pending, PurchaseOrderStatus::Approved], true) || $received) {
            throw ValidationException::withMessages([
                'purchase_order' => ['This purchase order can no longer be cancelled.'],
            ]);
        }

        $purchaseOrder->update(['status' => PurchaseOrderStatus::Cancelled]);

        return (new PurchaseOrderResource($purchaseOrder->load(self::LIST_WITH)->loadCount('items')))
            ->additional(['message' => 'Purchase order cancelled.']);
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return $this->decide($request, $purchaseOrder, approved: true);
    }

    public function decline(Request $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return $this->decide($request, $purchaseOrder, approved: false);
    }

    /** Goods landing against approved lines — each line a ledger receive. */
    public function receive(Request $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $user = $request->user();

        abort_unless(
            $user->hasPermissionForScope('inventory.manage', $purchaseOrder->school_id, $purchaseOrder->branch_id),
            403,
        );

        $this->assertStatus($purchaseOrder, [PurchaseOrderStatus::Approved], 'Only approved purchase orders can receive goods.');

        $data = $request->validate([
            'reference' => ['nullable', 'string', 'max:120'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_item_id' => ['required', 'integer', 'distinct'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        ]);

        $purchaseOrder->load('items.item');
        $ledger = app(StockLedger::class);

        DB::transaction(function () use ($data, $purchaseOrder, $ledger, $user): void {
            foreach ($data['lines'] as $input) {
                /** @var PurchaseOrderItem|null $line */
                $line = $purchaseOrder->items->firstWhere('id', (int) $input['purchase_order_item_id']);

                if ($line === null) {
                    throw ValidationException::withMessages([
                        'lines' => ['One of the lines does not belong to this purchase order.'],
                    ]);
                }

                $remaining = round((float) $line->quantity - (float) $line->received_quantity, 2);
                $qty = (float) $input['quantity'];

                if ($qty > $remaining) {
                    throw ValidationException::withMessages([
                        'lines' => ["{$line->item->name}: only {$remaining} {$line->item->unit} remain to receive."],
                    ]);
                }

                $ledger->post(
                    $purchaseOrder->school_id,
                    $purchaseOrder->branch_id,
                    $line->item,
                    StockMovementType::Receive,
                    $qty,
                    [
                        'purchase_order_id' => $purchaseOrder->id,
                        'unit_cost' => $input['unit_cost'] ?? $line->unit_cost,
                        'supplier_name' => $purchaseOrder->supplier_name,
                        'reference' => $data['reference'] ?? null,
                    ],
                    $user->id,
                );

                $line->update(['received_quantity' => round((float) $line->received_quantity + $qty, 2)]);
            }

            $complete = $purchaseOrder->items()
                ->whereRaw('received_quantity < quantity')
                ->doesntExist();

            if ($complete) {
                $purchaseOrder->update(['status' => PurchaseOrderStatus::Received]);
            }
        });

        ActivityLogger::log($user, 'inventory.po_received', $purchaseOrder, [
            'lines' => count($data['lines']),
        ], $purchaseOrder->school_id, $purchaseOrder->branch_id);

        return (new PurchaseOrderResource($purchaseOrder->fresh()->load(self::LIST_WITH)->loadCount('items')))
            ->additional(['message' => 'Goods received.']);
    }

    private function decide(Request $request, PurchaseOrder $purchaseOrder, bool $approved): PurchaseOrderResource
    {
        $user = $request->user();

        abort_unless(
            $user->hasPermissionForScope('inventory.approve', $purchaseOrder->school_id, $purchaseOrder->branch_id),
            403,
        );

        $this->assertStatus($purchaseOrder, [PurchaseOrderStatus::Pending], 'Only pending purchase orders can be decided.');

        // Four-eyes: the one who raised it never countersigns it.
        if ($purchaseOrder->ordered_by === $user->id) {
            throw ValidationException::withMessages([
                'purchase_order' => ['You raised this purchase order — a different approver must decide it.'],
            ]);
        }

        $data = $request->validate([
            'decline_reason' => [$approved ? 'nullable' : 'required', 'string', 'max:255'],
        ]);

        $purchaseOrder->update([
            'status' => $approved ? PurchaseOrderStatus::Approved : PurchaseOrderStatus::Declined,
            'decided_by' => $user->id,
            'decided_at' => now(),
            'decline_reason' => $data['decline_reason'] ?? null,
        ]);

        ActivityLogger::log($user, 'inventory.po_decided', $purchaseOrder, [
            'approved' => $approved,
        ], $purchaseOrder->school_id, $purchaseOrder->branch_id);

        app(Notifier::class)->toUser($purchaseOrder->orderer, 'inventory.po_decided', [
            'supplier' => $purchaseOrder->supplier_name,
            'status' => $approved ? 'approved' : 'declined',
        ], [
            'link' => '/inventory?tab=purchase-orders',
            'schoolId' => $purchaseOrder->school_id,
            'branchId' => $purchaseOrder->branch_id,
            'exceptUserId' => $user->id,
        ]);

        return (new PurchaseOrderResource($purchaseOrder->load(self::LIST_WITH)->loadCount('items')))
            ->additional(['message' => $approved ? 'Purchase order approved.' : 'Purchase order declined.']);
    }

    /**
     * @param  list<array{inventory_item_id: int, quantity: float|string, unit_cost?: float|string|null}>  $lines
     */
    private function writeLines(PurchaseOrder $po, array $lines): void
    {
        foreach ($lines as $line) {
            PurchaseOrderItem::create([
                'purchase_order_id' => $po->id,
                'inventory_item_id' => $line['inventory_item_id'],
                'quantity' => $line['quantity'],
                'unit_cost' => $line['unit_cost'] ?? null,
            ]);
        }

        $po->refreshTotalCost();
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, int $schoolId): array
    {
        return $request->validate([
            'supplier_name' => ['required', 'string', 'max:160'],
            'supplier_phone' => ['nullable', 'string', 'max:20'],
            // Orders are for goods still to come — never a past delivery
            // (record those as a direct receive instead).
            'expected_on' => ['nullable', 'date', 'after_or_equal:'.Ethiopia::today()],
            'note' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.inventory_item_id' => [
                'required',
                'distinct',
                Rule::exists('inventory_items', 'id')
                    ->where('school_id', $schoolId)
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        ]);
    }

    private function authorizeManage(Request $request, PurchaseOrder $po): void
    {
        abort_unless(
            $request->user()->hasPermissionForScope('inventory.manage', $po->school_id, $po->branch_id),
            403,
        );
    }

    /**
     * @param  list<PurchaseOrderStatus>  $allowed
     */
    private function assertStatus(PurchaseOrder $po, array $allowed, string $message): void
    {
        if (! in_array($po->status, $allowed, true)) {
            throw ValidationException::withMessages(['purchase_order' => [$message]]);
        }
    }
}
