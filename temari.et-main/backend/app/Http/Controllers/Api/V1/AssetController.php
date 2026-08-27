<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\StockMovementType;
use App\Http\Controllers\Concerns\HandlesListQueries;
use App\Http\Controllers\Concerns\ResolvesInventoryScope;
use App\Http\Controllers\Controller;
use App\Http\Resources\AssetUnitResource;
use App\Models\AssetAssignment;
use App\Models\AssetUnit;
use App\Models\Employee;
use App\Models\Room;
use App\Models\Section;
use App\Models\StudentEnrollment;
use App\Services\Inventory\StockLedger;
use App\Services\Notify\Notifier;
use App\Support\ActivityLogger;
use App\Support\Ethiopia;
use App\Support\PublicId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The property register (phase 2): tag-tracked units of is_asset items with
 * a custody chain. Identity book only — quantities stay in the stock ledger.
 * Custody rule: exactly one holder at a time, enforced by a partial unique;
 * LOST auto-closes custody; DISPOSED requires the unit back in the store.
 */
class AssetController extends Controller
{
    use HandlesListQueries;
    use ResolvesInventoryScope;

    private const LIST_WITH = [
        'item:id,name',
        'openAssignment.employee:id,first_name,father_name,grandfather_name',
        'openAssignment.student:id,first_name,father_name,grandfather_name',
        'openAssignment.room:id,name',
        'openAssignment.section:id,name',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $school = $this->inventorySchool($request, ['inventory.view', 'inventory.manage']);
        $branch = $this->activeBranchOrNull($request);

        $query = AssetUnit::query()
            ->where('school_id', $school->id)
            ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
            ->when($this->branchFilterId($request, $branch), fn ($q, int $id) => $q->where('branch_id', $id))
            ->when($request->integer('inventory_item_id'), fn ($q, int $id) => $q->where('inventory_item_id', $id))
            ->when($this->csvValues($request, 'status'), fn ($q, array $statuses) => $q->whereIn('status', $statuses))
            ->when($this->csvValues($request, 'condition'), fn ($q, array $conditions) => $q->whereIn('condition', $conditions))
            // Clearance lane: everything currently in one person's hands.
            ->when($request->filled('holder_type') && $request->filled('holder_id'), function ($q) use ($request): void {
                $column = match ($request->string('holder_type')->value()) {
                    'employee' => 'employee_id',
                    'student' => 'student_id',
                    'room' => 'room_id',
                    'section' => 'section_id',
                    default => null,
                };
                if ($column !== null) {
                    $q->whereHas('openAssignment', fn ($a) => $a->where($column, $request->integer('holder_id')));
                }
            })
            ->tap(fn ($q) => $this->applySearch($q, $request, fn ($w, string $n) => $w
                ->where('tag', PublicId::normalize($n))
                ->orWhere('serial_number', 'ilike', $this->needle($n))
                ->orWhereHas('item', fn ($i) => $i->where('name', 'ilike', $this->needle($n)))))
            ->with(self::LIST_WITH);

        if ($branch === null) {
            $query->with('branch:id,name');
        }

        $this->applySort($query, $request, ['created_at', 'status', 'condition', 'tag', 'acquired_on'], 'created_at');

        return AssetUnitResource::collection($query->paginate($this->perPage($request)));
    }

    /** Bulk-register N units of an asset item (each gets its own tag). */
    public function store(Request $request): JsonResponse
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
                    ->where('is_asset', true)
                    ->whereNull('deleted_at'),
            ],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'serial_numbers' => ['nullable', 'array', 'max:100'],
            'serial_numbers.*' => ['nullable', 'string', 'max:120'],
            'condition' => ['required', Rule::enum(AssetCondition::class)],
            'acquired_on' => ['nullable', 'date', 'after:2000-01-01', 'before_or_equal:'.Ethiopia::today()],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'note' => ['nullable', 'string', 'max:255'],
            // Registering units normally ALSO puts them on the stock ledger,
            // keeping the two books aligned. Flip off only when the stock was
            // already received earlier (e.g. via a PO delivery).
            'add_to_stock' => ['sometimes', 'boolean'],
        ]);

        $units = DB::transaction(function () use ($data, $branch, $request): array {
            $units = [];
            for ($i = 0; $i < (int) $data['quantity']; $i++) {
                $units[] = AssetUnit::create([
                    'school_id' => $branch->school_id,
                    'branch_id' => $branch->id,
                    'inventory_item_id' => $data['inventory_item_id'],
                    'tag' => PublicId::generate('asset_units', 'tag'),
                    'serial_number' => $data['serial_numbers'][$i] ?? null,
                    'condition' => $data['condition'],
                    'status' => AssetStatus::InStore,
                    'acquired_on' => $data['acquired_on'] ?? null,
                    'unit_cost' => $data['unit_cost'] ?? null,
                    'note' => $data['note'] ?? null,
                ]);
            }

            if ($data['add_to_stock'] ?? true) {
                app(StockLedger::class)->post(
                    $branch->school_id,
                    $branch->id,
                    $units[0]->item()->firstOrFail(),
                    StockMovementType::Receive,
                    count($units),
                    [
                        'unit_cost' => $data['unit_cost'] ?? null,
                        'note' => 'Asset registration',
                    ],
                    $request->user()->id,
                );
            }

            ActivityLogger::log($request->user(), 'inventory.assets_registered', $units[0], [
                'item_id' => (int) $data['inventory_item_id'],
                'count' => count($units),
            ], $branch->school_id, $branch->id);

            return $units;
        });

        return AssetUnitResource::collection(
            collect($units)->each->load(self::LIST_WITH)
        )->additional(['message' => 'Units registered.'])->response()->setStatusCode(201);
    }

    public function update(Request $request, AssetUnit $asset): AssetUnitResource
    {
        $this->authorizeUnit($request, $asset);

        $data = $request->validate([
            'serial_number' => ['nullable', 'string', 'max:120'],
            'condition' => ['sometimes', Rule::enum(AssetCondition::class)],
            'acquired_on' => ['nullable', 'date', 'after:2000-01-01', 'before_or_equal:'.Ethiopia::today()],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $asset->update($data);

        return (new AssetUnitResource($asset->load(self::LIST_WITH)))
            ->additional(['message' => 'Unit saved.']);
    }

    /**
     * Status transitions outside custody: repair, lost, dispose, restore.
     * LOST closes any open assignment (the holder lost it — that is the
     * note); DISPOSED only from the store, never out of someone's hands.
     */
    public function setStatus(Request $request, AssetUnit $asset): AssetUnitResource
    {
        $this->authorizeUnit($request, $asset);

        $data = $request->validate([
            'status' => ['required', Rule::in(['in_store', 'under_repair', 'lost', 'disposed'])],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $target = AssetStatus::from($data['status']);

        if ($asset->status === AssetStatus::Disposed) {
            throw ValidationException::withMessages([
                'status' => ['Disposed units are final — register a new unit instead.'],
            ]);
        }

        if ($target === AssetStatus::Disposed && $asset->status === AssetStatus::Assigned) {
            throw ValidationException::withMessages([
                'status' => ['Take the unit back from its holder before disposing of it.'],
            ]);
        }

        DB::transaction(function () use ($asset, $target, $data, $request): void {
            if ($target === AssetStatus::Lost) {
                $asset->openAssignment()->update([
                    'returned_on' => Ethiopia::today(),
                    'return_condition' => AssetCondition::Damaged->value,
                    'returned_by' => $request->user()->id,
                    'note' => trim(($data['note'] ?? '').' (lost)'),
                ]);
            }

            $asset->update([
                'status' => $target,
                'note' => $data['note'] ?? $asset->note,
            ]);
        });

        ActivityLogger::log($request->user(), 'inventory.asset_status', $asset, [
            'status' => $target->value,
        ], $asset->school_id, $asset->branch_id);

        return (new AssetUnitResource($asset->load(self::LIST_WITH)))
            ->additional(['message' => 'Unit updated.']);
    }

    /** Hand the unit to a holder — employee, student, room or section. */
    public function assign(Request $request, AssetUnit $asset): AssetUnitResource
    {
        $this->authorizeUnit($request, $asset);

        if ($asset->status !== AssetStatus::InStore) {
            throw ValidationException::withMessages([
                'asset' => ['Only units in the store can be assigned.'],
            ]);
        }

        $data = $request->validate([
            'holder_type' => ['required', Rule::in(['employee', 'student', 'room', 'section'])],
            'holder_id' => ['required', 'integer'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $holderColumn = $this->validateHolder($asset, $data['holder_type'], (int) $data['holder_id']);

        DB::transaction(function () use ($asset, $data, $holderColumn, $request): void {
            AssetAssignment::create([
                'school_id' => $asset->school_id,
                'branch_id' => $asset->branch_id,
                'asset_unit_id' => $asset->id,
                'holder_type' => $data['holder_type'],
                $holderColumn => (int) $data['holder_id'],
                'assigned_on' => Ethiopia::today(),
                'note' => $data['note'] ?? null,
                'assigned_by' => $request->user()->id,
            ]);

            $asset->update(['status' => AssetStatus::Assigned]);
        });

        // An employee holder is told what is now in their name.
        if ($data['holder_type'] === 'employee') {
            $employee = Employee::query()->find($data['holder_id']);
            if ($employee?->user_id !== null) {
                app(Notifier::class)->toUser($employee->user, 'inventory.asset_assigned', [
                    'item' => $asset->item()->value('name'),
                    'tag' => $asset->tag,
                ], [
                    'link' => '/inventory?tab=assets',
                    'schoolId' => $asset->school_id,
                    'branchId' => $asset->branch_id,
                    'exceptUserId' => $request->user()->id,
                ]);
            }
        }

        ActivityLogger::log($request->user(), 'inventory.asset_assigned', $asset, [
            'holder_type' => $data['holder_type'],
            'holder_id' => (int) $data['holder_id'],
        ], $asset->school_id, $asset->branch_id);

        return (new AssetUnitResource($asset->load(self::LIST_WITH)))
            ->additional(['message' => 'Unit assigned.']);
    }

    /** Take the unit back into the store, recording its returned condition. */
    public function returnUnit(Request $request, AssetUnit $asset): AssetUnitResource
    {
        $this->authorizeUnit($request, $asset);

        if ($asset->status !== AssetStatus::Assigned) {
            throw ValidationException::withMessages([
                'asset' => ['This unit is not assigned to anyone.'],
            ]);
        }

        $data = $request->validate([
            'condition' => ['nullable', Rule::enum(AssetCondition::class)],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($asset, $data, $request): void {
            $asset->openAssignment()->update([
                'returned_on' => Ethiopia::today(),
                'return_condition' => $data['condition'] ?? null,
                'returned_by' => $request->user()->id,
            ]);

            $asset->update([
                'status' => AssetStatus::InStore,
                ...(isset($data['condition']) ? ['condition' => $data['condition']] : []),
                ...(isset($data['note']) ? ['note' => $data['note']] : []),
            ]);
        });

        ActivityLogger::log($request->user(), 'inventory.asset_returned', $asset, [], $asset->school_id, $asset->branch_id);

        return (new AssetUnitResource($asset->load(self::LIST_WITH)))
            ->additional(['message' => 'Unit returned to store.']);
    }

    public function destroy(Request $request, AssetUnit $asset): JsonResponse
    {
        $this->authorizeUnit($request, $asset);

        // A unit that has ever been in someone's hands is history, not a typo.
        if ($asset->assignments()->exists()) {
            throw ValidationException::withMessages([
                'asset' => ['This unit has custody history — mark it disposed instead of deleting.'],
            ]);
        }

        $asset->delete();

        return response()->json(['message' => 'Unit deleted.']);
    }

    /**
     * Scoped holder picker for the assign flow and textbook issuing — id +
     * label only, so the storekeeper can NAME people without holding the HR
     * or student registers (`employees.view` / `students.view`).
     */
    public function holders(Request $request): JsonResponse
    {
        $branch = $this->targetBranch($request);

        abort_unless(
            $request->user()->hasPermissionForScope('inventory.manage', $branch->school_id, $branch->id),
            403,
        );

        $data = $request->validate([
            'type' => ['required', Rule::in(['employee', 'student', 'room', 'section'])],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $needle = trim($data['search'] ?? '');
        $like = '%'.addcslashes($needle, '\%_').'%';

        $rows = match ($data['type']) {
            'employee' => Employee::query()
                ->where('branch_id', $branch->id)
                ->where('is_active', true)
                ->when($needle !== '', fn ($q) => $q->where('search_text', 'ilike', $like))
                ->orderBy('first_name')
                ->limit(20)
                ->get()
                ->map(fn (Employee $e): array => ['id' => $e->id, 'label' => $e->full_name]),
            'student' => StudentEnrollment::query()
                ->where('branch_id', $branch->id)
                ->live()
                ->whereHas('student', fn ($s) => $s
                    ->when($needle !== '', fn ($q) => $q
                        ->where(fn ($w) => $w
                            ->where('search_text', 'ilike', $like)
                            ->orWhere('public_id', PublicId::normalize($needle)))))
                ->with('student:id,first_name,father_name,grandfather_name,public_id')
                ->limit(20)
                ->get()
                ->unique('student_id')
                ->values()
                ->map(fn (StudentEnrollment $e): array => [
                    'id' => $e->student_id,
                    'label' => (string) $e->student?->full_name,
                    'sublabel' => $e->student?->public_id,
                ]),
            'room' => Room::query()
                ->where('branch_id', $branch->id)
                ->where('is_active', true)
                ->when($needle !== '', fn ($q) => $q->where('name', 'ilike', $like))
                ->orderBy('name')
                ->limit(20)
                ->get()
                ->map(fn (Room $r): array => ['id' => $r->id, 'label' => $r->name]),
            'section' => Section::query()
                ->where('branch_id', $branch->id)
                ->where('is_active', true)
                ->when($needle !== '', fn ($q) => $q->where('name', 'ilike', $like))
                ->orderBy('name')
                ->limit(20)
                ->get()
                ->map(fn (Section $s): array => ['id' => $s->id, 'label' => $s->name]),
        };

        return response()->json(['data' => $rows->values()]);
    }

    /** The holder must exist in the UNIT's own branch — never across tenants. */
    private function validateHolder(AssetUnit $asset, string $type, int $id): string
    {
        $ok = match ($type) {
            'employee' => Employee::query()->whereKey($id)->where('branch_id', $asset->branch_id)->exists(),
            'student' => StudentEnrollment::query()->where('student_id', $id)->where('branch_id', $asset->branch_id)->live()->exists(),
            'room' => Room::query()->whereKey($id)->where('branch_id', $asset->branch_id)->exists(),
            'section' => Section::query()->whereKey($id)->where('branch_id', $asset->branch_id)->exists(),
        };

        if (! $ok) {
            throw ValidationException::withMessages([
                'holder_id' => ['Pick a holder from this branch.'],
            ]);
        }

        return match ($type) {
            'employee' => 'employee_id',
            'student' => 'student_id',
            'room' => 'room_id',
            'section' => 'section_id',
        };
    }

    private function authorizeUnit(Request $request, AssetUnit $asset): void
    {
        abort_unless(
            $request->user()->hasPermissionForScope('inventory.manage', $asset->school_id, $asset->branch_id),
            403,
        );
    }
}
