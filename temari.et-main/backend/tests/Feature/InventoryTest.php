<?php

use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Notification;
use App\Models\PurchaseOrderItem;
use App\Models\RequisitionItem;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Services\Inventory\StockLedger;
use Database\Seeders\InventoryCategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(InventoryCategorySeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function invItem(Branch $branch, array $overrides = []): InventoryItem
{
    return InventoryItem::create(array_merge([
        'school_id' => $branch->school_id,
        'inventory_category_id' => InventoryCategory::whereNull('school_id')->value('id'),
        'name' => 'Chalk (box of 100) '.uniqid(),
        'unit' => 'box',
    ], $overrides));
}

function invReceive(Branch $branch, InventoryItem $item, float $qty, int $userId): void
{
    app(StockLedger::class)->post($branch->school_id, $branch->id, $item, StockMovementType::Receive, $qty, [], $userId);
}

// ── The ledger ──────────────────────────────────────────────────────────

it('keeps the cached quantity equal to the sum of movements — the bin card never lies', function () {
    $branch = makeBranch();
    $keeper = memberOf($branch, Role::Storekeeper);
    $item = invItem($branch);
    Sanctum::actingAs($keeper);

    $ctx = branchContext($branch);
    $this->withHeaders($ctx)->postJson('/api/v1/inventory/movements/receive', [
        'inventory_item_id' => $item->id, 'quantity' => 50, 'unit_cost' => 180, 'supplier_name' => 'Mega PLC',
    ])->assertCreated();
    $this->withHeaders($ctx)->postJson('/api/v1/inventory/movements/receive', [
        'inventory_item_id' => $item->id, 'quantity' => 20,
    ])->assertCreated();
    $this->withHeaders($ctx)->postJson('/api/v1/inventory/movements/adjust', [
        'inventory_item_id' => $item->id, 'quantity' => -5, 'note' => 'Damp boxes found during cleaning',
    ])->assertCreated();
    $this->withHeaders($ctx)->postJson('/api/v1/inventory/movements/write-off', [
        'inventory_item_id' => $item->id, 'quantity' => 3, 'note' => 'Water damage',
    ])->assertCreated();

    $level = StockLevel::where('branch_id', $branch->id)->where('inventory_item_id', $item->id)->first();
    $sum = (float) StockMovement::where('inventory_item_id', $item->id)->sum('quantity_change');

    expect((float) $level->quantity_on_hand)->toBe(62.0)
        ->and($sum)->toBe(62.0);

    // Running balances stamp the bin card in order.
    expect(StockMovement::where('inventory_item_id', $item->id)->orderBy('id')->pluck('quantity_after')->map(fn ($q) => (float) $q)->all())
        ->toBe([50.0, 70.0, 65.0, 62.0]);
});

it('issues directly without a requisition, but never to nobody', function () {
    $branch = makeBranch();
    $keeper = memberOf($branch, Role::Storekeeper);
    $item = invItem($branch);
    invReceive($branch, $item, 10, $keeper->id);
    Sanctum::actingAs($keeper);

    $ctx = branchContext($branch);
    $this->withHeaders($ctx)->postJson('/api/v1/inventory/movements/issue', [
        'inventory_item_id' => $item->id, 'quantity' => 4,
    ])->assertUnprocessable(); // recipient required

    $this->withHeaders($ctx)->postJson('/api/v1/inventory/movements/issue', [
        'inventory_item_id' => $item->id, 'quantity' => 4, 'recipient' => 'Abebe Kebede (Registrar)',
    ])->assertCreated();

    expect((float) StockLevel::where('inventory_item_id', $item->id)->value('quantity_on_hand'))->toBe(6.0);
});

it('refuses to overdraw stock', function () {
    $branch = makeBranch();
    $keeper = memberOf($branch, Role::Storekeeper);
    $item = invItem($branch);
    invReceive($branch, $item, 5, $keeper->id);
    Sanctum::actingAs($keeper);

    $this->withHeaders(branchContext($branch))->postJson('/api/v1/inventory/movements/adjust', [
        'inventory_item_id' => $item->id, 'quantity' => -8, 'note' => 'Impossible correction',
    ])->assertUnprocessable();

    expect((float) StockLevel::where('inventory_item_id', $item->id)->value('quantity_on_hand'))->toBe(5.0);
});

it('alerts store staff once when stock crosses the reorder level', function () {
    $branch = makeBranch();
    $keeper = memberOf($branch, Role::Storekeeper);
    $item = invItem($branch, ['reorder_level' => 10]);
    invReceive($branch, $item, 20, $keeper->id);
    Sanctum::actingAs($keeper);

    $issue = fn (float $qty) => $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/inventory/movements/write-off', [
            'inventory_item_id' => $item->id, 'quantity' => $qty, 'note' => 'Broken',
        ])->assertCreated();

    $issue(12); // 20 → 8: crosses the threshold
    $issue(2);  // already low: stays quiet

    expect(Notification::where('event', 'inventory.low_stock')->count())->toBe(1);
});

// ── Permission gates ────────────────────────────────────────────────────

it('lets requesters browse the catalog but never touch the ledger or the item master', function () {
    $branch = makeBranch();
    $teacher = memberOf($branch, Role::Teacher);
    $item = invItem($branch);
    Sanctum::actingAs($teacher);

    $ctx = branchContext($branch);
    $this->withHeaders($ctx)->getJson('/api/v1/inventory/items')->assertOk();
    $this->withHeaders($ctx)->getJson('/api/v1/inventory/movements')->assertForbidden();
    $this->withHeaders($ctx)->postJson('/api/v1/inventory/movements/receive', [
        'inventory_item_id' => $item->id, 'quantity' => 5,
    ])->assertForbidden();
    $this->withHeaders($ctx)->postJson('/api/v1/inventory/items', [
        'inventory_category_id' => $item->inventory_category_id, 'name' => 'Rogue item', 'unit' => 'piece',
    ])->assertForbidden();
});

it('never leaks another school\'s inventory — the branch is the tenant boundary', function () {
    $branchA = makeBranch();
    $branchB = makeBranch('AA-0002');
    $keeperA = memberOf($branchA, Role::Storekeeper);
    $itemB = invItem($branchB, ['name' => 'School B microscope']);
    Sanctum::actingAs($keeperA);

    $list = $this->withHeaders(branchContext($branchA))->getJson('/api/v1/inventory/items')->assertOk();
    expect(collect($list->json('data'))->pluck('name'))->not->toContain('School B microscope');

    // A forged receive against school B's item is rejected at validation.
    $this->withHeaders(branchContext($branchA))->postJson('/api/v1/inventory/movements/receive', [
        'inventory_item_id' => $itemB->id, 'quantity' => 5,
    ])->assertUnprocessable();

    // And school B's requisitions are unreachable rows.
    $teacherB = memberOf($branchB, Role::Teacher);
    Sanctum::actingAs($teacherB);
    $reqB = $this->withHeaders(branchContext($branchB))->postJson('/api/v1/inventory/requisitions', [
        'items' => [['inventory_item_id' => $itemB->id, 'quantity' => 2]],
    ])->assertCreated()->json('data.id');

    Sanctum::actingAs($keeperA);
    $this->withHeaders(branchContext($branchA))
        ->postJson("/api/v1/inventory/requisitions/{$reqB}/approve")->assertForbidden();
});

// ── The requisition workflow ────────────────────────────────────────────

it('runs the request → countersign → issue workflow with the four-eyes rule', function () {
    $branch = makeBranch();
    $keeper = memberOf($branch, Role::Storekeeper);
    $director = directorOf($branch);
    $teacher = memberOf($branch, Role::Teacher);
    $item = invItem($branch);
    invReceive($branch, $item, 30, $keeper->id);

    $ctx = branchContext($branch);

    // The teacher asks for 10.
    Sanctum::actingAs($teacher);
    $id = $this->withHeaders($ctx)->postJson('/api/v1/inventory/requisitions', [
        'purpose' => 'Grade 9 classrooms',
        'items' => [['inventory_item_id' => $item->id, 'quantity' => 10]],
    ])->assertCreated()->json('data.id');

    // Requesters see their own rows; another teacher sees nothing.
    expect($this->withHeaders($ctx)->getJson('/api/v1/inventory/requisitions')->json('data'))->toHaveCount(1);
    Sanctum::actingAs(memberOf($branch, Role::Teacher));
    expect($this->withHeaders($ctx)->getJson('/api/v1/inventory/requisitions')->json('data'))->toHaveCount(0);

    // The requester can never countersign their own request…
    Sanctum::actingAs($teacher);
    $this->withHeaders($ctx)->postJson("/api/v1/inventory/requisitions/{$id}/approve")->assertForbidden();

    // …and the storekeeper (no approve permission) cannot either.
    Sanctum::actingAs($keeper);
    $this->withHeaders($ctx)->postJson("/api/v1/inventory/requisitions/{$id}/approve")->assertForbidden();

    // The director approves, trimming the line to 8.
    Sanctum::actingAs($director);
    $line = RequisitionItem::where('requisition_id', $id)->first();
    $this->withHeaders($ctx)->postJson("/api/v1/inventory/requisitions/{$id}/approve", [
        'lines' => [['requisition_item_id' => $line->id, 'quantity_approved' => 8]],
    ])->assertOk()->assertJsonPath('data.status', 'approved');

    // The storekeeper issues 5 (partial), then the remaining 3.
    Sanctum::actingAs($keeper);
    $this->withHeaders($ctx)->postJson("/api/v1/inventory/requisitions/{$id}/issue", [
        'lines' => [['requisition_item_id' => $line->id, 'quantity' => 5]],
    ])->assertOk()->assertJsonPath('data.status', 'approved');

    // Over-issuing beyond the approved remainder is refused.
    $this->withHeaders($ctx)->postJson("/api/v1/inventory/requisitions/{$id}/issue", [
        'lines' => [['requisition_item_id' => $line->id, 'quantity' => 4]],
    ])->assertUnprocessable();

    $this->withHeaders($ctx)->postJson("/api/v1/inventory/requisitions/{$id}/issue", [
        'lines' => [['requisition_item_id' => $line->id, 'quantity' => 3]],
    ])->assertOk()->assertJsonPath('data.status', 'issued');

    // 30 received − 8 issued = 22 on hand; the ledger carries both issues.
    expect((float) StockLevel::where('inventory_item_id', $item->id)->value('quantity_on_hand'))->toBe(22.0)
        ->and(StockMovement::where('requisition_id', $id)->count())->toBe(2);
});

it('requires a reason to decline and lets the requester edit only pending rows', function () {
    $branch = makeBranch();
    $director = directorOf($branch);
    $teacher = memberOf($branch, Role::Teacher);
    $item = invItem($branch);

    $ctx = branchContext($branch);

    Sanctum::actingAs($teacher);
    $id = $this->withHeaders($ctx)->postJson('/api/v1/inventory/requisitions', [
        'items' => [['inventory_item_id' => $item->id, 'quantity' => 2]],
    ])->assertCreated()->json('data.id');

    Sanctum::actingAs($director);
    $this->withHeaders($ctx)->postJson("/api/v1/inventory/requisitions/{$id}/decline")->assertUnprocessable();
    $this->withHeaders($ctx)->postJson("/api/v1/inventory/requisitions/{$id}/decline", [
        'decline_reason' => 'Reserved for exam week',
    ])->assertOk()->assertJsonPath('data.status', 'declined');

    // Decided rows are frozen for the requester.
    Sanctum::actingAs($teacher);
    $this->withHeaders($ctx)->putJson("/api/v1/inventory/requisitions/{$id}", [
        'items' => [['inventory_item_id' => $item->id, 'quantity' => 9]],
    ])->assertUnprocessable();
});

// ── The optional purchase-order lane ────────────────────────────────────

it('receives goods directly without any purchase order — POs are never mandatory', function () {
    $branch = makeBranch();
    $keeper = memberOf($branch, Role::Storekeeper);
    $item = invItem($branch);
    Sanctum::actingAs($keeper);

    $this->withHeaders(branchContext($branch))->postJson('/api/v1/inventory/movements/receive', [
        'inventory_item_id' => $item->id, 'quantity' => 40, 'supplier_name' => 'Walk-in supplier',
    ])->assertCreated();

    expect((float) StockLevel::where('inventory_item_id', $item->id)->value('quantity_on_hand'))->toBe(40.0);
});

it('runs the PO lane: countersigned, received in parts, auto-completed', function () {
    $branch = makeBranch();
    $keeper = memberOf($branch, Role::Storekeeper);
    $director = directorOf($branch);
    $item = invItem($branch);

    $ctx = branchContext($branch);

    Sanctum::actingAs($keeper);
    $po = $this->withHeaders($ctx)->postJson('/api/v1/inventory/purchase-orders', [
        'supplier_name' => 'Mega Stationery PLC',
        'items' => [['inventory_item_id' => $item->id, 'quantity' => 10, 'unit_cost' => 450]],
    ])->assertCreated();
    $poId = $po->json('data.id');
    expect($po->json('data.total_cost'))->toBe('4500.00');

    // Receiving before approval is refused; the raiser cannot self-approve.
    $lineId = PurchaseOrderItem::where('purchase_order_id', $poId)->value('id');
    $this->withHeaders($ctx)->postJson("/api/v1/inventory/purchase-orders/{$poId}/receive", [
        'lines' => [['purchase_order_item_id' => $lineId, 'quantity' => 4]],
    ])->assertUnprocessable();
    Sanctum::actingAs($director);
    // (director raised nothing — but verify the rule with the keeper first)
    Sanctum::actingAs($keeper);
    $this->withHeaders($ctx)->postJson("/api/v1/inventory/purchase-orders/{$poId}/approve")->assertForbidden();

    Sanctum::actingAs($director);
    $this->withHeaders($ctx)->postJson("/api/v1/inventory/purchase-orders/{$poId}/approve")
        ->assertOk()->assertJsonPath('data.status', 'approved');

    // Goods land in two deliveries; the PO completes itself.
    Sanctum::actingAs($keeper);
    $this->withHeaders($ctx)->postJson("/api/v1/inventory/purchase-orders/{$poId}/receive", [
        'reference' => 'DN-101',
        'lines' => [['purchase_order_item_id' => $lineId, 'quantity' => 4]],
    ])->assertOk()->assertJsonPath('data.status', 'approved');

    $this->withHeaders($ctx)->postJson("/api/v1/inventory/purchase-orders/{$poId}/receive", [
        'lines' => [['purchase_order_item_id' => $lineId, 'quantity' => 6]],
    ])->assertOk()->assertJsonPath('data.status', 'received');

    // Over-receiving is refused; stock and ledger agree.
    $this->withHeaders($ctx)->postJson("/api/v1/inventory/purchase-orders/{$poId}/receive", [
        'lines' => [['purchase_order_item_id' => $lineId, 'quantity' => 1]],
    ])->assertUnprocessable();

    expect((float) StockLevel::where('inventory_item_id', $item->id)->value('quantity_on_hand'))->toBe(10.0)
        ->and(StockMovement::where('purchase_order_id', $poId)->count())->toBe(2);
});

it('blocks a raiser from countersigning their own purchase order', function () {
    $branch = makeBranch();
    $director = directorOf($branch);
    $item = invItem($branch);
    Sanctum::actingAs($director);

    $ctx = branchContext($branch);
    $poId = $this->withHeaders($ctx)->postJson('/api/v1/inventory/purchase-orders', [
        'supplier_name' => 'Self Deal PLC',
        'items' => [['inventory_item_id' => $item->id, 'quantity' => 1]],
    ])->assertCreated()->json('data.id');

    $this->withHeaders($ctx)->postJson("/api/v1/inventory/purchase-orders/{$poId}/approve")
        ->assertUnprocessable();
});

// ── Stock takes ─────────────────────────────────────────────────────────

it('posts a stock take as ledger adjustments and skips uncounted lines', function () {
    $branch = makeBranch();
    $keeper = memberOf($branch, Role::Storekeeper);
    $counted = invItem($branch, ['name' => 'Football']);
    $skipped = invItem($branch, ['name' => 'Liquid soap']);
    invReceive($branch, $counted, 8, $keeper->id);
    invReceive($branch, $skipped, 30, $keeper->id);
    Sanctum::actingAs($keeper);

    $ctx = branchContext($branch);
    $take = $this->withHeaders($ctx)->postJson('/api/v1/inventory/stock-takes', [])->assertCreated();
    $takeId = $take->json('data.id');

    // Only one open count per branch.
    $this->withHeaders($ctx)->postJson('/api/v1/inventory/stock-takes', [])->assertUnprocessable();

    $lines = collect($take->json('data.lines'));
    $countedLine = $lines->firstWhere('item_name', 'Football');

    $this->withHeaders($ctx)->putJson("/api/v1/inventory/stock-takes/{$takeId}/counts", [
        'lines' => [['stock_take_line_id' => $countedLine['id'], 'counted_quantity' => 6]],
    ])->assertOk();

    $this->withHeaders($ctx)->postJson("/api/v1/inventory/stock-takes/{$takeId}/post")
        ->assertOk()->assertJsonPath('data.status', 'posted');

    expect((float) StockLevel::where('inventory_item_id', $counted->id)->value('quantity_on_hand'))->toBe(6.0)
        ->and((float) StockLevel::where('inventory_item_id', $skipped->id)->value('quantity_on_hand'))->toBe(30.0)
        ->and(StockMovement::where('stock_take_id', $takeId)->count())->toBe(1);

    // A posted take is closed for further tallies.
    $this->withHeaders($ctx)->putJson("/api/v1/inventory/stock-takes/{$takeId}/counts", [
        'lines' => [['stock_take_line_id' => $countedLine['id'], 'counted_quantity' => 4]],
    ])->assertUnprocessable();
});

// ── The item master ─────────────────────────────────────────────────────

it('protects ledger history: items with stock or movements deactivate, never delete', function () {
    $branch = makeBranch();
    $keeper = memberOf($branch, Role::Storekeeper);
    $item = invItem($branch);
    invReceive($branch, $item, 10, $keeper->id);
    Sanctum::actingAs($keeper);

    $ctx = branchContext($branch);
    $this->withHeaders($ctx)->deleteJson("/api/v1/inventory/items/{$item->id}")->assertUnprocessable();

    // Fresh, never-moved items delete cleanly.
    $fresh = invItem($branch, ['name' => 'Never used ruler']);
    $this->withHeaders($ctx)->deleteJson("/api/v1/inventory/items/{$fresh->id}")->assertOk();
});

it('flags low stock in the items register', function () {
    $branch = makeBranch();
    $keeper = memberOf($branch, Role::Storekeeper);
    $low = invItem($branch, ['name' => 'Chalk low', 'reorder_level' => 10]);
    $fine = invItem($branch, ['name' => 'Paper fine', 'reorder_level' => 10]);
    invReceive($branch, $low, 8, $keeper->id);
    invReceive($branch, $fine, 50, $keeper->id);
    Sanctum::actingAs($keeper);

    $names = collect($this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/inventory/items?low_stock=1')->assertOk()->json('data'))->pluck('name');

    expect($names)->toContain('Chalk low')->not->toContain('Paper fine');

    $stats = $this->withHeaders(branchContext($branch))->getJson('/api/v1/inventory/items/stats')->assertOk();
    expect($stats->json('data.low_stock_count'))->toBe(1);
});
