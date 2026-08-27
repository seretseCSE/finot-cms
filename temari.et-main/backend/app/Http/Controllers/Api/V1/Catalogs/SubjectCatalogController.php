<?php

namespace App\Http\Controllers\Api\V1\Catalogs;

use App\Http\Controllers\Concerns\HandlesListQueries;
use App\Http\Resources\SubjectResource;
use App\Models\Subject;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Platform view over ALL subjects (national curriculum + every school's
 * custom rows) with CRUD limited to the PLATFORM rows (school_id null) —
 * school-custom subjects are tenant data, shown here read-only for context
 * and managed by the school through /subjects.
 */
class SubjectCatalogController extends CatalogController
{
    use HandlesListQueries;

    private const SORTS = ['code', 'name', 'category', 'weight', 'is_active', 'created_at'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->assertCatalogManager($request);

        $query = $this->buildQuery($request);
        $this->applySort($query, $request, self::SORTS, 'code', 'asc');

        return SubjectResource::collection($query->paginate($this->perPage($request)));
    }

    public function export(Request $request): AnonymousResourceCollection
    {
        $this->assertCatalogManager($request);

        $query = $this->buildQuery($request);
        $this->applySort($query, $request, self::SORTS, 'code', 'asc');

        return SubjectResource::collection($query->limit(1000)->get());
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertCatalogManager($request);

        $data = $this->validated($request);

        $subject = DB::transaction(function () use ($data): Subject {
            $subject = Subject::create([
                ...Arr::except($data, ['grade_level_ids']),
                'school_id' => null, // the studio only mints national-curriculum rows
            ]);
            $subject->gradeLevels()->sync($data['grade_level_ids'] ?? []);

            return $subject;
        });

        return (new SubjectResource($subject->load('gradeLevels:grade_levels.id,sort_order')->loadCount('assignments')))
            ->additional(['message' => 'Subject added to the curriculum catalog.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Subject $subject): SubjectResource
    {
        $this->assertCatalogManager($request);
        $this->assertPlatformRow($subject);

        $data = $this->validated($request, $subject);

        DB::transaction(function () use ($data, $subject): void {
            $subject->update(Arr::except($data, ['grade_level_ids']));

            if (array_key_exists('grade_level_ids', $data)) {
                $subject->gradeLevels()->sync($data['grade_level_ids'] ?? []);
            }
        });

        return (new SubjectResource($subject->load(['school', 'gradeLevels:grade_levels.id,sort_order'])->loadCount('assignments')))
            ->additional(['message' => 'Subject updated.']);
    }

    public function destroy(Request $request, Subject $subject): JsonResponse
    {
        $this->assertCatalogManager($request);
        $this->assertPlatformRow($subject);

        abort_if(
            $subject->assignments()->exists(),
            422,
            'This subject is assigned to sections — deactivate it instead of deleting.',
        );

        $subject->delete();

        return response()->json(['message' => 'Subject deleted.']);
    }

    private function assertPlatformRow(Subject $subject): void
    {
        abort_if(
            $subject->school_id !== null,
            422,
            'School-custom subjects are managed by their school, not the platform catalog.',
        );
    }

    /**
     * @return Builder<Subject>
     */
    private function buildQuery(Request $request): Builder
    {
        $query = Subject::query()
            ->with(['school', 'gradeLevels:grade_levels.id,sort_order'])
            ->withCount('assignments');

        $this->applySearch($query, $request, fn ($q, string $n) => $q
            ->where('name', 'ilike', $this->needle($n))
            ->orWhere('code', 'ilike', $this->needle($n)));

        if ($categories = $this->csvValues($request, 'category')) {
            $query->whereIn('category', $categories);
        }

        if ($roomTypes = $this->csvValues($request, 'room_type')) {
            $query->whereIn('room_type', $roomTypes);
        }

        // platform | custom — origin filter (both/neither = no-op).
        $scopes = $this->csvValues($request, 'scope');
        $platform = in_array('platform', $scopes, true);
        $custom = in_array('custom', $scopes, true);
        if ($platform xor $custom) {
            $platform ? $query->whereNull('school_id') : $query->whereNotNull('school_id');
        }

        $this->applyBooleanFilter($query, $request, 'is_active', 'is_active');

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Subject $subject = null): array
    {
        return $request->validate([
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('subjects', 'code')->ignore($subject?->id)->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', Rule::in(Subject::CATEGORIES)],
            // Explicit grade set; empty = taught in every grade.
            'grade_level_ids' => ['sometimes', 'nullable', 'array', 'max:20'],
            'grade_level_ids.*' => ['integer', Rule::exists('grade_levels', 'id')],
            'weight' => ['required', 'integer', 'min:1', 'max:5'],
            'room_type' => ['nullable', Rule::in(Subject::ROOM_TYPES)],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
