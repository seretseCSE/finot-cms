<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StockMovementType;
use App\Enums\StockTakeStatus;
use App\Http\Controllers\Concerns\HandlesListQueries;
use App\Http\Controllers\Concerns\ResolvesInventoryScope;
use App\Http\Controllers\Controller;
use App\Http\Resources\StockTakeResource;
use App\Models\InventoryItem;
use App\Models\StockLevel;
use App\Models\StockTake;
use App\Models\StockTakeLine;
use App\Services\Inventory\StockLedger;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Physical counting sessions. Starting one snapshots every active item's
 * expected quantity (optionally one category); counting saves tallies;
 * POSTING reconciles — each difference lands in the ledger as an adjustment
 * computed against the LIVE balance, so stock moved mid-count never
 * double-corrects. Counting itself never touches stock.
 */
class StockTakeController extends Controller
{
    use HandlesListQueries;
    use ResolvesInventoryScope;

    private const LIST_WITH = ['starter:id,name', 'category:id,name'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $school = $this->inventorySchool($request, ['inventory.view', 'inventory.manage']);
        $branch = $this->activeBranchOrNull($request);

        $query = StockTake::query()
            ->where('school_id', $school->id)
            ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
            ->when($this->branchFilterId($request, $branch), fn ($q, int $id) => $q->where('branch_id', $id))
            ->when($this->csvValues($request, 'status'), fn ($q, array $statuses) => $q->whereIn('status', $statuses))
            ->withCount('lines')
            ->withCount(['lines as counted_count' => fn ($q) => $q->whereNotNull('counted_quantity')])
            ->with(self::LIST_WITH);

        if ($branch === null) {
            $query->with('branch:id,name');
        }

        $this->applySort($query, $request, ['created_at', 'status', 'posted_at'], 'created_at');

        return StockTakeResource::collection($query->paginate($this->perPage($request)));
    }

    public function show(Request $request, StockTake $stockTake): StockTakeResource
    {
        $this->authorizeRow($request, $stockTake, ['inventory.view', 'inventory.manage']);

        return new StockTakeResource(
            $stockTake->load([...self::LIST_WITH, 'lines.item:id,name,unit,code'])
                ->loadCount('lines'),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $branch = $this->targetBranch($request);
        $user = $request->user();

        abort_unless(
            $user->hasPermissionForScope('inventory.manage', $branch->school_id, $branch->id),
            403,
        );

        $data = $request->validate([
            'inventory_category_id' => [
                'nullable',
                Rule::exists('inventory_categories', 'id')
                    ->where(fn ($q) => $q->where(fn ($qq) => $qq->whereNull('school_id')->orWhere('school_id', $branch->school_id)))
                    ->whereNull('deleted_at'),
            ],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        // One open count at a time per branch keeps tallies unambiguous.
        $open = StockTake::query()
            ->where('branch_id', $branch->id)
            ->where('status', StockTakeStatus::InProgress)
            ->exists();

        if ($open) {
            throw ValidationException::withMessages([
                'stock_take' => ['A stock take is already in progress for this branch — post or cancel it first.'],
            ]);
        }

        $stockTake = DB::transaction(function () use ($data, $branch, $user): StockTake {
            $stockTake = StockTake::create([
                'school_id' => $branch->school_id,
                'branch_id' => $branch->id,
                'inventory_category_id' => $data['inventory_category_id'] ?? null,
                'status' => StockTakeStatus::InProgress,
                'note' => $data['note'] ?? null,
                'started_by' => $user->id,
            ]);

            $levels = StockLevel::query()
                ->where('branch_id', $branch->id)
                ->pluck('quantity_on_hand', 'inventory_item_id');

            InventoryItem::query()
                ->where('school_id', $branch->school_id)
                ->where('is_active', true)
                ->when($data['inventory_category_id'] ?? null, fn ($q, int $id) => $q->where('inventory_category_id', $id))
                ->orderBy('name')
                ->pluck('id')
                ->each(fn (int $itemId) => StockTakeLine::create([
                    'stock_take_id' => $stockTake->id,
                    'inventory_item_id' => $itemId,
                    'expected_quantity' => (float) ($levels[$itemId] ?? 0),
                ]));

            return $stockTake;
        });

        return (new StockTakeResource($stockTake->load([...self::LIST_WITH, 'lines.item:id,name,unit,code'])->loadCount('lines')))
            ->additional(['message' => 'Stock take started.'])
            ->response()
            ->setStatusCode(201);
    }

    /** Save tallies (bulk, called as the counter works through the store). */
    public function saveCounts(Request $request, StockTake $stockTake): StockTakeResource
    {
        $this->authorizeRow($request, $stockTake, ['inventory.manage']);
        $this->assertInProgress($stockTake);

        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.stock_take_line_id' => ['required', 'integer', 'distinct'],
            'lines.*.counted_quantity' => ['present', 'nullable', 'numeric', 'min:0', 'max:9999999999'],
        ]);

        $lines = $stockTake->lines()->pluck('id')->flip();

        DB::transaction(function () use ($data, $lines): void {
            foreach ($data['lines'] as $input) {
                if (! $lines->has((int) $input['stock_take_line_id'])) {
                    throw ValidationException::withMessages([
                        'lines' => ['One of the lines does not belong to this stock take.'],
                    ]);
                }

                StockTakeLine::query()
                    ->whereKey((int) $input['stock_take_line_id'])
                    ->update(['counted_quantity' => $input['counted_quantity']]);
            }
        });

        return new StockTakeResource(
            $stockTake->load([...self::LIST_WITH, 'lines.item:id,name,unit,code'])->loadCount('lines'),
        );
    }

    /** Reconcile: post each counted difference as a ledger adjustment. */
    public function post(Request $request, StockTake $stockTake): StockTakeResource
    {
        $user = $request->user();

        $this->authorizeRow($request, $stockTake, ['inventory.manage']);
        $this->assertInProgress($stockTake);

        $stockTake->load('lines.item');
        $ledger = app(StockLedger::class);
        $adjusted = 0;

        DB::transaction(function () use ($stockTake, $ledger, $user, &$adjusted): void {
            foreach ($stockTake->lines as $line) {
                if ($line->counted_quantity === null) {
                    continue; // uncounted lines are skipped, never zeroed
                }

                // Reconcile against the LIVE balance — stock may have moved
                // legitimately since the expected snapshot.
                $live = (float) (StockLevel::query()
                    ->where('branch_id', $stockTake->branch_id)
                    ->where('inventory_item_id', $line->inventory_item_id)
                    ->value('quantity_on_hand') ?? 0);

                $change = round((float) $line->counted_quantity - $live, 2);

                if ($change == 0.0) {
                    continue;
                }

                $ledger->post(
                    $stockTake->school_id,
                    $stockTake->branch_id,
                    $line->item,
                    StockMovementType::Adjustment,
                    $change,
                    [
                        'stock_take_id' => $stockTake->id,
                        'note' => 'Stock take reconciliation',
                    ],
                    $user->id,
                );

                $adjusted++;
            }

            $stockTake->update([
                'status' => StockTakeStatus::Posted,
                'posted_by' => $user->id,
                'posted_at' => now(),
            ]);
        });

        ActivityLogger::log($user, 'inventory.stock_take_posted', $stockTake, [
            'adjustments' => $adjusted,
        ], $stockTake->school_id, $stockTake->branch_id);

        return (new StockTakeResource($stockTake->load([...self::LIST_WITH, 'lines.item:id,name,unit,code'])->loadCount('lines')))
            ->additional(['message' => $adjusted > 0 ? "Stock take posted — {$adjusted} adjustment(s) written." : 'Stock take posted — everything matched.']);
    }

    public function cancel(Request $request, StockTake $stockTake): StockTakeResource
    {
        $this->authorizeRow($request, $stockTake, ['inventory.manage']);
        $this->assertInProgress($stockTake);

        $stockTake->update(['status' => StockTakeStatus::Cancelled]);

        return (new StockTakeResource($stockTake->load(self::LIST_WITH)->loadCount('lines')))
            ->additional(['message' => 'Stock take cancelled.']);
    }

    /**
     * @param  list<string>  $anyOf
     */
    private function authorizeRow(Request $request, StockTake $stockTake, array $anyOf): void
    {
        $user = $request->user();

        abort_unless(
            collect($anyOf)->contains(fn (string $p) => $user->hasPermissionForScope($p, $stockTake->school_id, $stockTake->branch_id)),
            403,
        );
    }

    private function assertInProgress(StockTake $stockTake): void
    {
        if ($stockTake->status !== StockTakeStatus::InProgress) {
            throw ValidationException::withMessages([
                'stock_take' => ['This stock take is already closed.'],
            ]);
        }
    }
}
