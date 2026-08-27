<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RequisitionStatus;
use App\Enums\StockMovementType;
use App\Http\Controllers\Concerns\HandlesListQueries;
use App\Http\Controllers\Concerns\ResolvesInventoryScope;
use App\Http\Controllers\Controller;
use App\Http\Resources\RequisitionResource;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Services\Inventory\StockLedger;
use App\Services\Notify\Notifier;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The Model-22 workflow. Any staff member with inventory.request files one;
 * inventory.approve countersigns (never their own — the finance four-eyes
 * rule); inventory.manage issues against the approved lines, each issue a
 * ledger movement. Partial issues allowed; the row flips to `issued` when
 * every approved line is fulfilled.
 */
class RequisitionController extends Controller
{
    use HandlesListQueries;
    use ResolvesInventoryScope;

    private const LIST_WITH = ['requester:id,name', 'decider:id,name', 'items.item:id,name,unit'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $school = $this->inventorySchool($request, ['inventory.view', 'inventory.manage', 'inventory.approve', 'inventory.request']);
        $branch = $this->activeBranchOrNull($request);

        $supervisory = $user->hasContextPermission('inventory.view')
            || $user->hasContextPermission('inventory.manage')
            || $user->hasContextPermission('inventory.approve');

        $query = Requisition::query()
            ->where('school_id', $school->id)
            ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
            ->when($this->branchFilterId($request, $branch), fn ($q, int $id) => $q->where('branch_id', $id))
            // Plain requesters see only their own; managers may narrow to
            // "mine" for the my-requests view.
            ->when(! $supervisory || $request->boolean('mine'), fn ($q) => $q->where('requested_by', $user->id))
            ->when($this->csvValues($request, 'status'), fn ($q, array $statuses) => $q->whereIn('status', $statuses))
            ->tap(fn ($q) => $this->applySearch($q, $request, fn ($w, string $n) => $w
                ->where('purpose', 'ilike', $this->needle($n))
                ->orWhereHas('requester', fn ($r) => $r->where('name', 'ilike', $this->needle($n)))))
            ->withCount('items')
            ->with(self::LIST_WITH);

        if ($branch === null) {
            $query->with('branch:id,name');
        }

        $this->applySort($query, $request, ['created_at', 'status', 'decided_at'], 'created_at');

        return RequisitionResource::collection($query->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $branch = $this->targetBranch($request);
        $user = $request->user();

        abort_unless(
            $user->hasPermissionForScope('inventory.request', $branch->school_id, $branch->id)
            || $user->hasPermissionForScope('inventory.manage', $branch->school_id, $branch->id),
            403,
        );

        $data = $this->validatePayload($request, $branch->school_id);

        $requisition = DB::transaction(function () use ($data, $branch, $user): Requisition {
            $requisition = Requisition::create([
                'school_id' => $branch->school_id,
                'branch_id' => $branch->id,
                'status' => RequisitionStatus::Pending,
                'requested_by' => $user->id,
                'purpose' => $data['purpose'] ?? null,
            ]);

            foreach ($data['items'] as $line) {
                RequisitionItem::create([
                    'requisition_id' => $requisition->id,
                    'inventory_item_id' => $line['inventory_item_id'],
                    'quantity_requested' => $line['quantity'],
                ]);
            }

            return $requisition;
        });

        // Four-eyes: the approvers learn a decision awaits (never the requester).
        app(Notifier::class)->toStaff($branch->school_id, $branch->id, 'inventory.approve', 'inventory.requisition_submitted', [
            'requester' => $user->name,
        ], [
            'link' => '/inventory?tab=requisitions',
            'exceptUserId' => $user->id,
        ]);

        return (new RequisitionResource($requisition->load(self::LIST_WITH)->loadCount('items')))
            ->additional(['message' => 'Request submitted — pending approval.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Requisition $requisition): RequisitionResource
    {
        $this->authorizeRequesterEdit($request, $requisition);

        $data = $this->validatePayload($request, $requisition->school_id);

        DB::transaction(function () use ($requisition, $data): void {
            $requisition->update(['purpose' => $data['purpose'] ?? null]);
            $requisition->items()->delete();

            foreach ($data['items'] as $line) {
                RequisitionItem::create([
                    'requisition_id' => $requisition->id,
                    'inventory_item_id' => $line['inventory_item_id'],
                    'quantity_requested' => $line['quantity'],
                ]);
            }
        });

        return (new RequisitionResource($requisition->load(self::LIST_WITH)->loadCount('items')))
            ->additional(['message' => 'Request saved.']);
    }

    public function cancel(Request $request, Requisition $requisition): RequisitionResource
    {
        $this->authorizeRequesterEdit($request, $requisition);

        $requisition->update(['status' => RequisitionStatus::Cancelled]);

        return (new RequisitionResource($requisition->load(self::LIST_WITH)->loadCount('items')))
            ->additional(['message' => 'Request cancelled.']);
    }

    public function approve(Request $request, Requisition $requisition): RequisitionResource
    {
        return $this->decide($request, $requisition, approved: true);
    }

    public function decline(Request $request, Requisition $requisition): RequisitionResource
    {
        return $this->decide($request, $requisition, approved: false);
    }

    /**
     * Issue stock against approved lines. Each line posts a ledger movement
     * (overdraw refused by StockLedger); the row completes when every
     * approved line is fully issued.
     */
    public function issue(Request $request, Requisition $requisition): RequisitionResource
    {
        $user = $request->user();

        abort_unless(
            $user->hasPermissionForScope('inventory.manage', $requisition->school_id, $requisition->branch_id),
            403,
        );

        if ($requisition->status !== RequisitionStatus::Approved) {
            throw ValidationException::withMessages([
                'requisition' => ['Only approved requests can be issued.'],
            ]);
        }

        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.requisition_item_id' => ['required', 'integer', 'distinct'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
        ]);

        $requisition->load(['items.item', 'requester:id,name']);
        $ledger = app(StockLedger::class);

        DB::transaction(function () use ($data, $requisition, $ledger, $user): void {
            foreach ($data['lines'] as $input) {
                /** @var RequisitionItem|null $line */
                $line = $requisition->items->firstWhere('id', (int) $input['requisition_item_id']);

                if ($line === null) {
                    throw ValidationException::withMessages([
                        'lines' => ['One of the lines does not belong to this request.'],
                    ]);
                }

                $approved = (float) ($line->quantity_approved ?? $line->quantity_requested);
                $remaining = round($approved - (float) $line->quantity_issued, 2);
                $qty = (float) $input['quantity'];

                if ($qty > $remaining) {
                    throw ValidationException::withMessages([
                        'lines' => ["{$line->item->name}: only {$remaining} {$line->item->unit} remain approved."],
                    ]);
                }

                $ledger->post(
                    $requisition->school_id,
                    $requisition->branch_id,
                    $line->item,
                    StockMovementType::Issue,
                    $qty,
                    [
                        'requisition_id' => $requisition->id,
                        'recipient' => $requisition->requester?->name,
                    ],
                    $user->id,
                );

                $line->update(['quantity_issued' => round((float) $line->quantity_issued + $qty, 2)]);
            }

            $fullyIssued = $requisition->items()
                ->whereRaw('quantity_issued < COALESCE(quantity_approved, quantity_requested)')
                ->doesntExist();

            if ($fullyIssued) {
                $requisition->update([
                    'status' => RequisitionStatus::Issued,
                    'fulfilled_at' => now(),
                ]);
            }
        });

        ActivityLogger::log($user, 'inventory.requisition_issued', $requisition, [
            'lines' => count($data['lines']),
        ], $requisition->school_id, $requisition->branch_id);

        app(Notifier::class)->toUser($requisition->requester, 'inventory.requisition_issued', [
            'status' => $requisition->status->value,
        ], [
            'link' => '/inventory?tab=my-requests',
            'schoolId' => $requisition->school_id,
            'branchId' => $requisition->branch_id,
            'exceptUserId' => $user->id,
        ]);

        return (new RequisitionResource($requisition->fresh()->load(self::LIST_WITH)->loadCount('items')))
            ->additional(['message' => 'Items issued.']);
    }

    private function decide(Request $request, Requisition $requisition, bool $approved): RequisitionResource
    {
        $user = $request->user();

        abort_unless(
            $user->hasPermissionForScope('inventory.approve', $requisition->school_id, $requisition->branch_id),
            403,
        );

        if ($requisition->status !== RequisitionStatus::Pending) {
            throw ValidationException::withMessages([
                'requisition' => ['Only pending requests can be decided.'],
            ]);
        }

        // Four-eyes: the requester never countersigns their own request.
        if ($requisition->requested_by === $user->id) {
            throw ValidationException::withMessages([
                'requisition' => ['You filed this request — a different approver must decide it.'],
            ]);
        }

        $data = $request->validate([
            'decline_reason' => [$approved ? 'nullable' : 'required', 'string', 'max:255'],
            // The approver may trim line quantities while approving.
            'lines' => ['sometimes', 'array'],
            'lines.*.requisition_item_id' => ['required_with:lines', 'integer', 'distinct'],
            'lines.*.quantity_approved' => ['required_with:lines', 'numeric', 'min:0', 'max:9999999999'],
        ]);

        DB::transaction(function () use ($requisition, $data, $approved, $user): void {
            if ($approved) {
                $overrides = collect($data['lines'] ?? [])
                    ->keyBy(fn (array $l) => (int) $l['requisition_item_id']);

                foreach ($requisition->items as $line) {
                    $override = $overrides->get($line->id);
                    $quantity = $override !== null
                        ? min((float) $override['quantity_approved'], (float) $line->quantity_requested)
                        : (float) $line->quantity_requested;

                    $line->update(['quantity_approved' => $quantity]);
                }
            }

            $requisition->update([
                'status' => $approved ? RequisitionStatus::Approved : RequisitionStatus::Declined,
                'decided_by' => $user->id,
                'decided_at' => now(),
                'decline_reason' => $data['decline_reason'] ?? null,
            ]);
        });

        ActivityLogger::log($user, 'inventory.requisition_decided', $requisition, [
            'approved' => $approved,
        ], $requisition->school_id, $requisition->branch_id);

        app(Notifier::class)->toUser($requisition->requester, 'inventory.requisition_decided', [
            'status' => $approved ? 'approved' : 'declined',
        ], [
            'link' => '/inventory?tab=my-requests',
            'schoolId' => $requisition->school_id,
            'branchId' => $requisition->branch_id,
            'exceptUserId' => $user->id,
        ]);

        return (new RequisitionResource($requisition->load(self::LIST_WITH)->loadCount('items')))
            ->additional(['message' => $approved ? 'Request approved.' : 'Request declined.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, int $schoolId): array
    {
        return $request->validate([
            'purpose' => ['nullable', 'string', 'max:255'],
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
        ]);
    }

    /** Pending rows belong to their requester; nobody else edits them. */
    private function authorizeRequesterEdit(Request $request, Requisition $requisition): void
    {
        $user = $request->user();

        abort_unless(
            $requisition->requested_by === $user->id
            && ($user->hasPermissionForScope('inventory.request', $requisition->school_id, $requisition->branch_id)
                || $user->hasPermissionForScope('inventory.manage', $requisition->school_id, $requisition->branch_id)),
            403,
        );

        if ($requisition->status !== RequisitionStatus::Pending) {
            throw ValidationException::withMessages([
                'requisition' => ['Only pending requests can be changed.'],
            ]);
        }
    }
}
