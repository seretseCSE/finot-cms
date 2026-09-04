<?php

namespace App\Http\Controllers\Api\V1\Catalogs;

use App\Enums\Cycle;
use App\Http\Resources\GradeLevelResource;
use App\Models\GradeLevel;
use App\Models\StudentEnrollment;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Platform CRUD over the nationally fixed grade ladder (KG-1 … Grade 12).
 * The whole catalog is 14 rows — no pagination; ordered by sort_order, which
 * also anchors subject grade windows, so re-ordering is an expert action.
 */
class GradeLevelCatalogController extends CatalogController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->assertCatalogManager($request);

        return GradeLevelResource::collection(
            GradeLevel::withCount('sections')->orderBy('sort_order')->get(),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertCatalogManager($request);

        $level = GradeLevel::create($this->validated($request));

        return (new GradeLevelResource($level->loadCount('sections')))
            ->additional(['message' => 'Grade level created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, GradeLevel $gradeLevel): GradeLevelResource
    {
        $this->assertCatalogManager($request);

        $gradeLevel->update($this->validated($request, $gradeLevel));

        return (new GradeLevelResource($gradeLevel->loadCount('sections')))
            ->additional(['message' => 'Grade level updated.']);
    }

    /**
     * Persist a whole-ladder reorder in one shot. The client sends every grade
     * level id in its new top-to-bottom order; we renumber `sort_order`
     * sequentially (1..N) inside a transaction. sort_order anchors subject grade
     * windows, so this is an expert action — but doing it atomically avoids the
     * per-row unique-collision dance the single edit form forces.
     */
    public function reorder(Request $request): AnonymousResourceCollection
    {
        $this->assertCatalogManager($request);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:grade_levels,id'],
        ]);

        $ids = $data['ids'];
        abort_if(
            count($ids) !== GradeLevel::count(),
            422,
            'The reorder must include every grade level exactly once.',
        );

        DB::transaction(function () use ($ids): void {
            foreach ($ids as $position => $id) {
                GradeLevel::whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });

        return GradeLevelResource::collection(
            GradeLevel::withCount('sections')->orderBy('sort_order')->get(),
        );
    }

    public function destroy(Request $request, GradeLevel $gradeLevel): JsonResponse
    {
        $this->assertCatalogManager($request);

        $inUse = $gradeLevel->sections()->withTrashed()->exists()
            || StudentEnrollment::withTrashed()->where('grade_level_id', $gradeLevel->id)->exists();

        abort_if($inUse, 422, 'This grade level has sections or enrollments — it cannot be deleted.');

        try {
            $gradeLevel->delete();
        } catch (QueryException) {
            // Backstop for the remaining restrict FKs (teacher specialties,
            // promotions, term results, transfer requests, fee pivots).
            abort(422, 'This grade level is referenced by other records — it cannot be deleted.');
        }

        return response()->json(['message' => 'Grade level deleted.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?GradeLevel $gradeLevel = null): array
    {
        return $request->validate([
            'code' => [
                'required', 'string', 'max:10', 'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('grade_levels', 'code')->ignore($gradeLevel?->id),
            ],
            'name' => ['required', 'string', 'max:100'],
            'cycle' => ['required', Rule::enum(Cycle::class)],
            'sort_order' => [
                'required', 'integer', 'min:1', 'max:100',
                Rule::unique('grade_levels', 'sort_order')->ignore($gradeLevel?->id),
            ],
            'has_national_exam' => ['sometimes', 'boolean'],
        ]);
    }
}
