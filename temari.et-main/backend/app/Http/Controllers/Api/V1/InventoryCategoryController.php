<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ResolvesInventoryScope;
use App\Http\Controllers\Controller;
use App\Http\Resources\InventoryCategoryResource;
use App\Models\InventoryCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * Item categories: platform seed rows shared by everyone plus the school's
 * own custom rows. Schools CRUD only their own; used categories deactivate,
 * never delete (platform-catalog convention).
 */
class InventoryCategoryController extends Controller
{
    use ResolvesInventoryScope;

    public function index(Request $request): AnonymousResourceCollection
    {
        $school = $this->inventorySchool($request, ['inventory.view', 'inventory.manage', 'inventory.request']);

        $categories = InventoryCategory::query()
            ->forSchool($school->id)
            ->when(! $request->boolean('include_inactive'), fn ($q) => $q->where('is_active', true))
            ->withCount('items')
            ->orderBy('name')
            ->get();

        return InventoryCategoryResource::collection($categories);
    }

    public function store(Request $request): JsonResponse
    {
        $school = $this->inventorySchool($request, ['inventory.manage']);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'icon' => ['nullable', 'string', 'max:40'],
        ]);

        $exists = InventoryCategory::query()
            ->forSchool($school->id)
            ->where('name', 'ilike', $data['name'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => ['A category with this name already exists.'],
            ]);
        }

        $category = InventoryCategory::create([...$data, 'school_id' => $school->id]);

        return (new InventoryCategoryResource($category))
            ->additional(['message' => 'Category created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, InventoryCategory $inventoryCategory): InventoryCategoryResource
    {
        $this->authorizeOwn($request, $inventoryCategory);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'icon' => ['nullable', 'string', 'max:40'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $inventoryCategory->update($data);

        return (new InventoryCategoryResource($inventoryCategory))
            ->additional(['message' => 'Category saved.']);
    }

    public function destroy(Request $request, InventoryCategory $inventoryCategory): JsonResponse
    {
        $this->authorizeOwn($request, $inventoryCategory);

        if ($inventoryCategory->items()->withTrashed()->exists()) {
            throw ValidationException::withMessages([
                'category' => ['This category has items — deactivate it instead of deleting.'],
            ]);
        }

        $inventoryCategory->delete();

        return response()->json(['message' => 'Category deleted.']);
    }

    /** Platform seed rows are never editable school-side. */
    private function authorizeOwn(Request $request, InventoryCategory $category): void
    {
        abort_if($category->school_id === null, 403, 'Platform categories cannot be changed.');

        abort_unless(
            $request->user()->hasPermissionForScope('inventory.manage', $category->school_id, $this->activeBranchOrNull($request)?->id),
            403,
        );
    }
}
