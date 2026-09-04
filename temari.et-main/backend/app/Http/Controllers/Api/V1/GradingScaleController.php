<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\GradingScaleResource;
use App\Models\GradingScale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Grading scales: the platform defaults plus the active school's custom
 * scales. Same catalog pattern as leave types — the SCHOOL owns custom rows
 * (one definition, every branch), platform rows are read-only here. Reading
 * needs grades.view; changing school scales needs grades.manage.
 */
class GradingScaleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        [$schoolId, $branchId] = $this->schoolScope($request);

        abort_unless($request->user()->hasPermissionForScope('grades.view', $schoolId, $branchId), 403);

        $scales = GradingScale::query()
            ->where(fn ($q) => $q->whereNull('school_id')->orWhere('school_id', $schoolId))
            ->when(! $request->boolean('all'), fn ($q) => $q->where('is_active', true))
            ->with('bands')
            ->withCount('policies')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => GradingScaleResource::collection($scales)]);
    }

    public function store(Request $request): JsonResponse
    {
        [$schoolId, $branchId] = $this->schoolScope($request);

        abort_unless($request->user()->hasPermissionForScope('grades.manage', $schoolId, $branchId), 403);

        $data = $this->validated($request, $schoolId);

        $scale = GradingScale::create([
            'school_id' => $schoolId,
            'code' => $data['code'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $this->replaceBands($scale, $data['bands']);

        return (new GradingScaleResource($scale->load('bands')))
            ->additional(['message' => 'Grading scale created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, GradingScale $gradingScale): GradingScaleResource
    {
        [$schoolId, $branchId] = $this->schoolScope($request);

        abort_unless(
            $gradingScale->school_id === $schoolId
            && $request->user()->hasPermissionForScope('grades.manage', $schoolId, $branchId),
            403,
        );

        $data = $this->validated($request, $schoolId, $gradingScale);

        $gradingScale->update([
            'code' => $data['code'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? $gradingScale->is_active,
        ]);

        $this->replaceBands($gradingScale, $data['bands']);

        return new GradingScaleResource($gradingScale->load('bands'));
    }

    public function destroy(Request $request, GradingScale $gradingScale): JsonResponse
    {
        [$schoolId, $branchId] = $this->schoolScope($request);

        abort_unless(
            $gradingScale->school_id === $schoolId
            && $request->user()->hasPermissionForScope('grades.manage', $schoolId, $branchId),
            403,
        );

        // Deactivate-not-delete once referenced: frozen results snapshot the
        // scale, but live policies must never lose their mapping.
        if ($gradingScale->policies()->exists()) {
            $gradingScale->update(['is_active' => false]);

            return response()->json(['message' => 'Scale is in use by a grading policy — deactivated instead.']);
        }

        $gradingScale->delete();

        return response()->json(['message' => 'Grading scale deleted.']);
    }

    /**
     * @return array{0: int, 1: ?int}
     */
    private function schoolScope(Request $request): array
    {
        $branch = $this->activeBranchOrNull($request);
        $schoolId = $branch?->school_id ?? $this->activeSchoolScopeId($request);

        abort_if($schoolId === null, 422, 'Select a school context to manage grading.');

        return [(int) $schoolId, $branch?->id];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, int $schoolId, ?GradingScale $scale = null): array
    {
        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:40',
                Rule::unique('grading_scales', 'code')
                    ->where('school_id', $schoolId)
                    ->whereNull('deleted_at')
                    ->ignore($scale?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'bands' => ['required', 'array', 'min:1'],
            'bands.*.min_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'bands.*.max_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'bands.*.letter' => ['nullable', 'string', 'max:8'],
            'bands.*.label' => ['required', 'string', 'max:60'],
            'bands.*.grade_points' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'bands.*.is_passing' => ['required', 'boolean'],
        ]);

        $this->assertBandsCoherent($data['bands']);

        return $data;
    }

    /**
     * Bands must be internally ordered (min ≤ max) and never overlap — a
     * score must resolve to exactly one band.
     *
     * @param  list<array<string, mixed>>  $bands
     */
    private function assertBandsCoherent(array $bands): void
    {
        $sorted = collect($bands)->sortBy('min_score')->values();

        $previousMax = null;
        foreach ($sorted as $band) {
            if ((float) $band['min_score'] > (float) $band['max_score']) {
                throw ValidationException::withMessages(['bands' => ['Each band needs min ≤ max.']]);
            }

            if ($previousMax !== null && (float) $band['min_score'] <= $previousMax) {
                throw ValidationException::withMessages(['bands' => ['Bands must not overlap.']]);
            }

            $previousMax = (float) $band['max_score'];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $bands
     */
    private function replaceBands(GradingScale $scale, array $bands): void
    {
        $scale->bands()->delete();

        foreach (collect($bands)->sortByDesc('min_score')->values() as $i => $band) {
            $scale->bands()->create([
                'min_score' => $band['min_score'],
                'max_score' => $band['max_score'],
                'letter' => $band['letter'] ?? null,
                'label' => $band['label'],
                'grade_points' => $band['grade_points'] ?? null,
                'is_passing' => $band['is_passing'],
                'sort_order' => $i + 1,
            ]);
        }
    }
}
