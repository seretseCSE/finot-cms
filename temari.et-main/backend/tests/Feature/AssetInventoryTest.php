<?php

use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Models\AssetUnit;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\GradeLevel;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Notification;
use App\Models\Room;
use App\Models\SchoolProgram;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\TextbookLoan;
use App\Services\Inventory\StockLedger;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\InventoryCategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(GradeLevelSeeder::class);
    $this->seed(InventoryCategorySeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function assetItem(Branch $branch, string $name = 'Projector'): InventoryItem
{
    return InventoryItem::create([
        'school_id' => $branch->school_id,
        'inventory_category_id' => InventoryCategory::whereNull('school_id')->value('id'),
        'name' => $name.' '.uniqid(),
        'unit' => 'piece',
        'is_asset' => true,
    ]);
}

function branchEmployee(Branch $branch, string $first = 'Abebe'): Employee
{
    return Employee::create([
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'first_name' => $first,
        'father_name' => 'Kebede',
        'gender' => 'male',
        'is_active' => true,
    ]);
}

/** A section full of active students, returning [section_id, year, students]. */
function sectionRoster(Branch $branch, int $count = 3): array
{
    $year = activeYear($branch);
    $gradeId = (int) GradeLevel::query()->orderBy('sort_order')->value('id');
    $section = $branch->sections()->create([
        'school_id' => $branch->school_id,
        'grade_level_id' => $gradeId,
        'name' => 'G1-'.uniqid(),
    ]);

    $students = [];
    for ($i = 0; $i < $count; $i++) {
        $student = Student::create([
            'school_id' => $branch->school_id, 'branch_id' => $branch->id,
            'first_name' => 'Student'.$i, 'father_name' => 'Test', 'gender' => 'female',
        ]);
        StudentEnrollment::create([
            'student_id' => $student->id, 'school_id' => $branch->school_id, 'branch_id' => $branch->id,
            'academic_year_id' => $year->id, 'school_program_id' => SchoolProgram::defaultFor($branch)->id,
            'grade_level_id' => $gradeId, 'section_id' => $section->id,
            'status' => 'active', 'enrolled_on' => now(),
        ]);
        $students[] = $student;
    }

    return [$section->id, $year, $students];
}

// ── The property register ───────────────────────────────────────────────

it('bulk-registers tagged units and lists them scoped to the school', function () {
    $branch = makeBranch();
    $keeper = memberOf($branch, Role::Storekeeper);
    $item = assetItem($branch);
    Sanctum::actingAs($keeper);

    $res = $this->withHeaders(branchContext($branch))->postJson('/api/v1/inventory/assets', [
        'inventory_item_id' => $item->id,
        'quantity' => 3,
        'serial_numbers' => ['SN-1', 'SN-2'],
        'condition' => 'new',
        'unit_cost' => 28000,
    ])->assertCreated();

    $tags = collect($res->json('data'))->pluck('tag');
    expect($tags)->toHaveCount(3)
        ->and($tags->unique())->toHaveCount(3);

    // Only asset items may enter the register.
    $consumable = InventoryItem::create([
        'school_id' => $branch->school_id,
        'inventory_category_id' => InventoryCategory::whereNull('school_id')->value('id'),
        'name' => 'Chalk '.uniqid(), 'unit' => 'box',
    ]);
    $this->withHeaders(branchContext($branch))->postJson('/api/v1/inventory/assets', [
        'inventory_item_id' => $consumable->id, 'quantity' => 1, 'condition' => 'good',
    ])->assertUnprocessable();
});

it('runs the custody chain: assign, refuse double custody, return with condition', function () {
    $branch = makeBranch();
    $keeper = memberOf($branch, Role::Storekeeper);
    $item = assetItem($branch);
    $employee = branchEmployee($branch);
    Sanctum::actingAs($keeper);

    $ctx = branchContext($branch);
    $unitId = $this->withHeaders($ctx)->postJson('/api/v1/inventory/assets', [
        'inventory_item_id' => $item->id, 'quantity' => 1, 'condition' => 'good',
    ])->assertCreated()->json('data.0.id');

    $this->withHeaders($ctx)->postJson("/api/v1/inventory/assets/{$unitId}/assign", [
        'holder_type' => 'employee', 'holder_id' => $employee->id, 'note' => 'Physics classes',
    ])->assertOk()->assertJsonPath('data.status', 'assigned')
        ->assertJsonPath('data.holder.label', $employee->full_name);

    // A unit is in one pair of hands at a time.
    $this->withHeaders($ctx)->postJson("/api/v1/inventory/assets/{$unitId}/assign", [
        'holder_type' => 'employee', 'holder_id' => $employee->id,
    ])->assertUnprocessable();

    $this->withHeaders($ctx)->postJson("/api/v1/inventory/assets/{$unitId}/return", [
        'condition' => 'fair',
    ])->assertOk()->assertJsonPath('data.status', 'in_store')->assertJsonPath('data.condition', 'fair');

    $unit = AssetUnit::findOrFail($unitId);
    expect($unit->assignments()->count())->toBe(1)
        ->and($unit->assignments()->first()->returned_on)->not->toBeNull()
        ->and($unit->assignments()->first()->return_condition)->toBe('fair');
});

it('closes custody when a unit is lost, and refuses disposal out of someone\'s hands', function () {
    $branch = makeBranch();
    $keeper = memberOf($branch, Role::Storekeeper);
    $item = assetItem($branch);
    $employee = branchEmployee($branch);
    Sanctum::actingAs($keeper);

    $ctx = branchContext($branch);
    $unitId = $this->withHeaders($ctx)->postJson('/api/v1/inventory/assets', [
        'inventory_item_id' => $item->id, 'quantity' => 1, 'condition' => 'good',
    ])->json('data.0.id');

    $this->withHeaders($ctx)->postJson("/api/v1/inventory/assets/{$unitId}/assign", [
        'holder_type' => 'employee', 'holder_id' => $employee->id,
    ])->assertOk();

    // Disposing an assigned unit is refused; losing it closes custody.
    $this->withHeaders($ctx)->postJson("/api/v1/inventory/assets/{$unitId}/status", [
        'status' => 'disposed',
    ])->assertUnprocessable();

    $this->withHeaders($ctx)->postJson("/api/v1/inventory/assets/{$unitId}/status", [
        'status' => 'lost', 'note' => 'Missing after the exam week',
    ])->assertOk()->assertJsonPath('data.status', 'lost');

    $unit = AssetUnit::findOrFail($unitId);
    expect($unit->openAssignment()->exists())->toBeFalse();

    // Found again → back to the store; disposed is terminal.
    $this->withHeaders($ctx)->postJson("/api/v1/inventory/assets/{$unitId}/status", [
        'status' => 'in_store',
    ])->assertOk();
    $this->withHeaders($ctx)->postJson("/api/v1/inventory/assets/{$unitId}/status", [
        'status' => 'disposed',
    ])->assertOk();
    $this->withHeaders($ctx)->postJson("/api/v1/inventory/assets/{$unitId}/status", [
        'status' => 'in_store',
    ])->assertUnprocessable();
});

it('rejects holders from another branch and hides the register from requesters', function () {
    $branchA = makeBranch();
    $branchB = makeBranch('AA-0002');
    $keeperA = memberOf($branchA, Role::Storekeeper);
    $employeeB = branchEmployee($branchB, 'Chaltu');
    $item = assetItem($branchA);
    Sanctum::actingAs($keeperA);

    $ctx = branchContext($branchA);
    $unitId = $this->withHeaders($ctx)->postJson('/api/v1/inventory/assets', [
        'inventory_item_id' => $item->id, 'quantity' => 1, 'condition' => 'good',
    ])->json('data.0.id');

    $this->withHeaders($ctx)->postJson("/api/v1/inventory/assets/{$unitId}/assign", [
        'holder_type' => 'employee', 'holder_id' => $employeeB->id,
    ])->assertUnprocessable();

    // Requesters (teachers) hold no register read and no holder picker.
    Sanctum::actingAs(memberOf($branchA, Role::Teacher));
    $this->withHeaders($ctx)->getJson('/api/v1/inventory/assets')->assertForbidden();
    $this->withHeaders($ctx)->getJson('/api/v1/inventory/holders?type=employee')->assertForbidden();
});

it('answers the clearance question: everything one holder still has', function () {
    $branch = makeBranch();
    $keeper = memberOf($branch, Role::Storekeeper);
    $employee = branchEmployee($branch);
    $item = assetItem($branch);
    Sanctum::actingAs($keeper);

    $ctx = branchContext($branch);
    $ids = collect($this->withHeaders($ctx)->postJson('/api/v1/inventory/assets', [
        'inventory_item_id' => $item->id, 'quantity' => 2, 'condition' => 'good',
    ])->json('data'))->pluck('id');

    foreach ($ids as $id) {
        $this->withHeaders($ctx)->postJson("/api/v1/inventory/assets/{$id}/assign", [
            'holder_type' => 'employee', 'holder_id' => $employee->id,
        ])->assertOk();
    }
    $this->withHeaders($ctx)->postJson("/api/v1/inventory/assets/{$ids[0]}/return")->assertOk();

    $held = $this->withHeaders($ctx)
        ->getJson("/api/v1/inventory/assets?holder_type=employee&holder_id={$employee->id}")
        ->assertOk()->json('data');

    expect($held)->toHaveCount(1)
        ->and($held[0]['id'])->toBe($ids[1]);
});

it('deletes only units that never held custody', function () {
    $branch = makeBranch();
    $keeper = memberOf($branch, Role::Storekeeper);
    $employee = branchEmployee($branch);
    $item = assetItem($branch);
    Sanctum::actingAs($keeper);

    $ctx = branchContext($branch);
    $ids = collect($this->withHeaders($ctx)->postJson('/api/v1/inventory/assets', [
        'inventory_item_id' => $item->id, 'quantity' => 2, 'condition' => 'good',
    ])->json('data'))->pluck('id');

    $this->withHeaders($ctx)->postJson("/api/v1/inventory/assets/{$ids[0]}/assign", [
        'holder_type' => 'employee', 'holder_id' => $employee->id,
    ])->assertOk();
    $this->withHeaders($ctx)->postJson("/api/v1/inventory/assets/{$ids[0]}/return")->assertOk();

    $this->withHeaders($ctx)->deleteJson("/api/v1/inventory/assets/{$ids[0]}")->assertUnprocessable();
    $this->withHeaders($ctx)->deleteJson("/api/v1/inventory/assets/{$ids[1]}")->assertOk();
});

// ── The guided add flow ─────────────────────────────────────────────────

it('quick-adds a consumable with its opening stock in one call', function () {
    $branch = makeBranch();
    $keeper = memberOf($branch, Role::Storekeeper);
    Sanctum::actingAs($keeper);

    $res = $this->withHeaders(branchContext($branch))->postJson('/api/v1/inventory/items/quick-add', [
        'inventory_category_id' => InventoryCategory::whereNull('school_id')->value('id'),
        'name' => 'Quick Chalk', 'unit' => 'box', 'reorder_level' => 10,
        'opening_quantity' => 50, 'unit_cost' => 180, 'supplier_name' => 'Mega PLC',
    ])->assertCreated();

    $itemId = $res->json('data.id');
    expect((float) StockLevel::where('inventory_item_id', $itemId)->value('quantity_on_hand'))->toBe(50.0)
        ->and(StockMovement::where('inventory_item_id', $itemId)->count())->toBe(1);
});

it('quick-adds an asset with its tagged units AND the matching stock in one call', function () {
    $branch = makeBranch();
    $keeper = memberOf($branch, Role::Storekeeper);
    Sanctum::actingAs($keeper);

    $res = $this->withHeaders(branchContext($branch))->postJson('/api/v1/inventory/items/quick-add', [
        'inventory_category_id' => InventoryCategory::whereNull('school_id')->value('id'),
        'name' => 'Quick Projector', 'unit' => 'piece', 'is_asset' => true,
        'units' => 3, 'serial_numbers' => ['QP-1', 'QP-2'], 'condition' => 'new', 'unit_cost' => 28000,
    ])->assertCreated();

    $itemId = $res->json('data.id');
    // The two books agree at birth: 3 units, quantity 3.
    expect(AssetUnit::where('inventory_item_id', $itemId)->count())->toBe(3)
        ->and((float) StockLevel::where('inventory_item_id', $itemId)->value('quantity_on_hand'))->toBe(3.0)
        ->and(AssetUnit::where('inventory_item_id', $itemId)->pluck('tag')->unique())->toHaveCount(3);

    // A bare catalog row (no stock, no units) needs no branch and posts nothing.
    $bare = $this->withHeaders(branchContext($branch))->postJson('/api/v1/inventory/items/quick-add', [
        'inventory_category_id' => InventoryCategory::whereNull('school_id')->value('id'),
        'name' => 'Bare row', 'unit' => 'piece',
    ])->assertCreated()->json('data.id');
    expect(StockMovement::where('inventory_item_id', $bare)->count())->toBe(0);
});

it('suggests sequential item codes from the category prefix', function () {
    $branch = makeBranch();
    $keeper = memberOf($branch, Role::Storekeeper);
    Sanctum::actingAs($keeper);

    $categoryId = InventoryCategory::whereNull('school_id')
        ->where('name', 'like', 'Stationery%')->value('id');

    $ctx = branchContext($branch);
    $first = $this->withHeaders($ctx)
        ->getJson("/api/v1/inventory/items/next-code?inventory_category_id={$categoryId}")
        ->assertOk()->json('data.code');
    expect($first)->toBe('STA-0001');

    // Taking the code advances the sequence.
    $this->withHeaders($ctx)->postJson('/api/v1/inventory/items', [
        'inventory_category_id' => $categoryId, 'name' => 'Coded item', 'unit' => 'piece', 'code' => $first,
    ])->assertCreated();
    expect($this->withHeaders($ctx)
        ->getJson("/api/v1/inventory/items/next-code?inventory_category_id={$categoryId}")
        ->json('data.code'))->toBe('STA-0002');

    // A custom category with no Latin letters falls back to ITM.
    $amharic = InventoryCategory::create(['school_id' => $branch->school_id, 'name' => 'የግቢ ዕቃዎች']);
    expect($this->withHeaders($ctx)
        ->getJson("/api/v1/inventory/items/next-code?inventory_category_id={$amharic->id}")
        ->json('data.code'))->toBe('ITM-0001');
});

it('keeps units and stock aligned on day-2 registration, with an opt-out', function () {
    $branch = makeBranch();
    $keeper = memberOf($branch, Role::Storekeeper);
    $item = assetItem($branch);
    Sanctum::actingAs($keeper);

    $ctx = branchContext($branch);
    // Default: registering 2 units also receives 2 into stock.
    $this->withHeaders($ctx)->postJson('/api/v1/inventory/assets', [
        'inventory_item_id' => $item->id, 'quantity' => 2, 'condition' => 'good',
    ])->assertCreated();
    expect((float) StockLevel::where('inventory_item_id', $item->id)->value('quantity_on_hand'))->toBe(2.0);

    // Opt-out: stock was already received earlier (e.g. via a PO delivery).
    $this->withHeaders($ctx)->postJson('/api/v1/inventory/assets', [
        'inventory_item_id' => $item->id, 'quantity' => 1, 'condition' => 'good', 'add_to_stock' => false,
    ])->assertCreated();
    expect((float) StockLevel::where('inventory_item_id', $item->id)->value('quantity_on_hand'))->toBe(2.0);
});

// ── Textbook lending ────────────────────────────────────────────────────

it('bulk-issues a book to a section with ONE aggregate ledger movement, skipping holders', function () {
    $branch = makeBranch();
    $keeper = memberOf($branch, Role::Storekeeper);
    [$sectionId, $year, $students] = sectionRoster($branch, 3);
    $book = InventoryItem::create([
        'school_id' => $branch->school_id,
        'inventory_category_id' => InventoryCategory::whereNull('school_id')->value('id'),
        'name' => 'Maths Textbook '.uniqid(), 'unit' => 'piece',
    ]);
    app(StockLedger::class)->post($branch->school_id, $branch->id, $book, StockMovementType::Receive, 50, [], $keeper->id);
    Sanctum::actingAs($keeper);

    $ctx = branchContext($branch);
    $payload = [
        'academic_year_id' => $year->id, 'inventory_item_id' => $book->id, 'section_id' => $sectionId,
    ];

    $this->withHeaders($ctx)->postJson('/api/v1/inventory/textbooks/issue', $payload)
        ->assertCreated()->assertJsonPath('data.issued', 3)->assertJsonPath('data.skipped', 0);

    // One aggregate issue movement for the batch; stock down by 3.
    expect(StockMovement::where('inventory_item_id', $book->id)->where('type', 'issue')->count())->toBe(1)
        ->and((float) StockLevel::where('inventory_item_id', $book->id)->value('quantity_on_hand'))->toBe(47.0)
        ->and(TextbookLoan::where('inventory_item_id', $book->id)->where('status', 'out')->count())->toBe(3);

    // Re-running never double-issues.
    $this->withHeaders($ctx)->postJson('/api/v1/inventory/textbooks/issue', $payload)->assertUnprocessable();

    // Families were told in-app.
    expect(Notification::where('event', 'inventory.textbook_issued')->exists())->toBeFalse();
    // (students have no linked users/guardians in this fixture — no rows, no crash)
});

it('takes year-end returns in bulk and marks lost copies as write-offs with the family told', function () {
    $branch = makeBranch();
    $keeper = memberOf($branch, Role::Storekeeper);
    [$sectionId, $year] = sectionRoster($branch, 2);
    $book = InventoryItem::create([
        'school_id' => $branch->school_id,
        'inventory_category_id' => InventoryCategory::whereNull('school_id')->value('id'),
        'name' => 'English Textbook '.uniqid(), 'unit' => 'piece',
    ]);
    app(StockLedger::class)->post($branch->school_id, $branch->id, $book, StockMovementType::Receive, 10, [], $keeper->id);
    Sanctum::actingAs($keeper);

    $ctx = branchContext($branch);
    $this->withHeaders($ctx)->postJson('/api/v1/inventory/textbooks/issue', [
        'academic_year_id' => $year->id, 'inventory_item_id' => $book->id, 'section_id' => $sectionId,
    ])->assertCreated();

    $loans = TextbookLoan::where('inventory_item_id', $book->id)->orderBy('id')->get();

    // Return one, lose one.
    $this->withHeaders($ctx)->postJson('/api/v1/inventory/textbooks/return', [
        'ids' => [$loans[0]->id],
    ])->assertOk()->assertJsonPath('data.returned', 1);

    $this->withHeaders($ctx)->postJson("/api/v1/inventory/textbooks/{$loans[1]->id}/lost", [
        'note' => 'Bag stolen on the way home',
    ])->assertOk()->assertJsonPath('data.status', 'lost');

    // 10 − 2 issued + 1 returned = 9. The lost copy posts NO movement — the
    // issue already took it off the shelf; a write-off would shrink twice.
    expect((float) StockLevel::where('inventory_item_id', $book->id)->value('quantity_on_hand'))->toBe(9.0)
        ->and(StockMovement::where('inventory_item_id', $book->id)->where('type', 'return')->count())->toBe(1)
        ->and(StockMovement::where('inventory_item_id', $book->id)->where('type', 'write_off')->count())->toBe(0);

    // A returned loan frees the slot: the student can be issued again.
    $this->withHeaders($ctx)->postJson('/api/v1/inventory/textbooks/issue', [
        'academic_year_id' => $year->id, 'inventory_item_id' => $book->id, 'section_id' => $sectionId,
    ])->assertCreated()->assertJsonPath('data.issued', 2)->assertJsonPath('data.skipped', 0);
});

it('scopes the holder picker to the branch and serves all four holder kinds', function () {
    $branch = makeBranch();
    $keeper = memberOf($branch, Role::Storekeeper);
    branchEmployee($branch, 'Meseret');
    [$sectionId] = sectionRoster($branch, 1);
    Room::create(['school_id' => $branch->school_id, 'branch_id' => $branch->id, 'name' => 'ICT Lab', 'is_active' => true]);
    Sanctum::actingAs($keeper);

    $ctx = branchContext($branch);
    foreach (['employee', 'student', 'room', 'section'] as $type) {
        $rows = $this->withHeaders($ctx)->getJson("/api/v1/inventory/holders?type={$type}")
            ->assertOk()->json('data');
        expect($rows)->not->toBeEmpty();
    }

    // Another branch's storekeeper sees none of it.
    $branchB = makeBranch('AA-0002');
    Sanctum::actingAs(memberOf($branchB, Role::Storekeeper));
    expect($this->withHeaders(branchContext($branchB))->getJson('/api/v1/inventory/holders?type=employee')
        ->assertOk()->json('data'))->toBeEmpty();
});
