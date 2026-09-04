<?php

namespace App\Http\Controllers\Api\V1\Catalogs;

use App\Enums\HealthConditionCategory;
use App\Http\Controllers\Concerns\HandlesListQueries;
use App\Http\Resources\HealthConditionResource;
use App\Models\HealthCondition;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Platform CRUD over the health-condition catalog. Conditions referenced by
 * student records can only be deactivated (hidden from pickers) — deleting
 * them would orphan medical history.
 */
class HealthConditionCatalogController extends CatalogController
{
    use HandlesListQueries;

    private const SORTS = ['name', 'category', 'is_active', 'created_at'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->assertCatalogManager($request);

        $query = $this->buildQuery($request);
        $this->applySort($query, $request, self::SORTS, 'name', 'asc');

        return HealthConditionResource::collection($query->paginate($this->perPage($request)));
    }

    public function export(Request $request): AnonymousResourceCollection
    {
        $this->assertCatalogManager($request);

        $query = $this->buildQuery($request);
        $this->applySort($query, $request, self::SORTS, 'name', 'asc');

        return HealthConditionResource::collection($query->limit(1000)->get());
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertCatalogManager($request);

        $condition = HealthCondition::create($this->validated($request));

        return (new HealthConditionResource($condition->loadCount('students')))
            ->additional(['message' => 'Health condition added.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, HealthCondition $healthCondition): HealthConditionResource
    {
        $this->assertCatalogManager($request);

        $healthCondition->update($this->validated($request, $healthCondition));

        return (new HealthConditionResource($healthCondition->loadCount('students')))
            ->additional(['message' => 'Health condition updated.']);
    }

    public function destroy(Request $request, HealthCondition $healthCondition): JsonResponse
    {
        $this->assertCatalogManager($request);

        abort_if(
            $healthCondition->students()->exists(),
            422,
            'Student health records reference this condition — deactivate it instead of deleting.',
        );

        $healthCondition->delete();

        return response()->json(['message' => 'Health condition deleted.']);
    }

    /**
     * @return Builder<HealthCondition>
     */
    private function buildQuery(Request $request): Builder
    {
        $query = HealthCondition::query()->withCount('students');

        $this->applySearch($query, $request, fn ($q, string $n) => $q
            ->where('name', 'ilike', $this->needle($n)));

        if ($categories = $this->csvValues($request, 'category')) {
            $query->whereIn('category', $categories);
        }

        $this->applyBooleanFilter($query, $request, 'is_active', 'is_active');

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?HealthCondition $condition = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('health_conditions', 'name')->ignore($condition?->id),
            ],
            'category' => ['required', Rule::enum(HealthConditionCategory::class)],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
