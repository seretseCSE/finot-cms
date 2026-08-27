<?php

namespace App\Http\Controllers\Api\V1\Catalogs;

use App\Http\Controllers\Concerns\HandlesListQueries;
use App\Http\Resources\SchoolDirectoryEntryResource;
use App\Models\SchoolDirectoryEntry;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Platform admin view over the Ethiopian school directory: the paginated
 * register (vs. the 20-row picker on /school-directory) plus verified-by-
 * default additions. Verify / edit / delete reuse the existing
 * SchoolDirectoryController routes.
 */
class SchoolDirectoryCatalogController extends CatalogController
{
    use HandlesListQueries;

    private const SORTS = ['name', 'region', 'city', 'is_verified', 'created_at'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->assertCatalogManager($request);

        $query = $this->buildQuery($request);
        $this->applySort($query, $request, self::SORTS, 'name', 'asc');

        return SchoolDirectoryEntryResource::collection($query->paginate($this->perPage($request)));
    }

    public function export(Request $request): AnonymousResourceCollection
    {
        $this->assertCatalogManager($request);

        $query = $this->buildQuery($request);
        $this->applySort($query, $request, self::SORTS, 'name', 'asc');

        return SchoolDirectoryEntryResource::collection($query->limit(5000)->get());
    }

    /**
     * Distinct regions present in the directory — powers the region filter.
     */
    public function regions(Request $request): JsonResponse
    {
        $this->assertCatalogManager($request);

        $regions = SchoolDirectoryEntry::query()
            ->whereNotNull('region')
            ->distinct()
            ->orderBy('region')
            ->pluck('region');

        return response()->json(['data' => $regions]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertCatalogManager($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:100'],
            'zone' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'is_verified' => ['sometimes', 'boolean'],
        ]);

        $entry = SchoolDirectoryEntry::create([
            ...$data,
            // Platform-curated rows are trusted by default.
            'is_verified' => $data['is_verified'] ?? true,
        ]);

        return (new SchoolDirectoryEntryResource($entry))
            ->additional(['message' => 'School added to the directory.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @return Builder<SchoolDirectoryEntry>
     */
    private function buildQuery(Request $request): Builder
    {
        $query = SchoolDirectoryEntry::query()->with(['school:id,name', 'createdBySchool:id,name']);

        $this->applySearch($query, $request, fn ($q, string $n) => $q
            ->where('name', 'ilike', $this->needle($n))
            ->orWhere('city', 'ilike', $this->needle($n))
            ->orWhere('zone', 'ilike', $this->needle($n)));

        if ($regions = $this->csvValues($request, 'region')) {
            $query->whereIn('region', $regions);
        }

        $this->applyBooleanFilter($query, $request, 'is_verified', 'is_verified');

        // On Temari vs off-platform (both/neither = no-op).
        $values = $this->csvValues($request, 'on_platform');
        $on = in_array('true', $values, true);
        $off = in_array('false', $values, true);
        if ($on xor $off) {
            $on ? $query->whereNotNull('school_id') : $query->whereNull('school_id');
        }

        return $query;
    }
}
