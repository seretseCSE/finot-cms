<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FinanceCategory;
use App\Models\School;
use App\Support\FinanceCategories;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A school's cashbook categories (expense | income). First read provisions
 * the Ethiopian-bursar defaults; referenced categories are deactivated,
 * never deleted (platform-catalog convention).
 */
class FinanceCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $school = $this->booksSchool($request, 'finance.books.view');

        FinanceCategories::ensureSeeded($school);

        $categories = FinanceCategory::query()
            ->where('school_id', $school->id)
            ->when(! $request->boolean('include_inactive'), fn ($q) => $q->where('is_active', true))
            ->when($request->filled('kind'), fn ($q) => $q->where('kind', $request->string('kind')->value()))
            ->orderBy('kind')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $categories->map(fn (FinanceCategory $c): array => self::payload($c)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $school = $this->booksSchool($request, 'finance.books.manage');

        $data = $request->validate([
            'kind' => ['required', Rule::in(['expense', 'income'])],
            'name' => ['required', 'string', 'max:120'],
        ]);

        $exists = FinanceCategory::withTrashed()
            ->where('school_id', $school->id)
            ->where('kind', $data['kind'])
            ->where('name', $data['name'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['name' => ['This category already exists.']]);
        }

        $category = FinanceCategory::create([...$data, 'school_id' => $school->id, 'is_active' => true]);

        return response()->json([
            'data' => self::payload($category),
            'message' => 'Category created.',
        ], 201);
    }

    public function update(Request $request, FinanceCategory $financeCategory): JsonResponse
    {
        $this->authorizeCategory($request, $financeCategory, 'finance.books.manage');

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $financeCategory->update($data);

        return response()->json([
            'data' => self::payload($financeCategory),
            'message' => 'Category saved.',
        ]);
    }

    public function destroy(Request $request, FinanceCategory $financeCategory): JsonResponse
    {
        $this->authorizeCategory($request, $financeCategory, 'finance.books.manage');

        if ($financeCategory->expenses()->withTrashed()->exists()
            || $financeCategory->otherIncomes()->withTrashed()->exists()) {
            throw ValidationException::withMessages([
                'category' => ['This category has recorded entries — deactivate it instead of deleting.'],
            ]);
        }

        $financeCategory->delete();

        return response()->json(['message' => 'Category deleted.']);
    }

    /**
     * The school whose books the caller is working in: the active branch's
     * school, else the school-wide context.
     */
    private function booksSchool(Request $request, string $permission): School
    {
        abort_unless($request->user()->hasContextPermission($permission), 403);

        $branch = $this->activeBranchOrNull($request);
        $schoolId = $branch?->school_id ?? $this->activeSchoolScopeId($request);

        abort_if($schoolId === null, 422, 'Select a school context first.');

        return School::findOrFail($schoolId);
    }

    private function authorizeCategory(Request $request, FinanceCategory $category, string $permission): void
    {
        abort_unless(
            $request->user()->hasPermissionForScope($permission, $category->school_id, $this->activeBranchOrNull($request)?->id),
            403,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function payload(FinanceCategory $category): array
    {
        return [
            'id' => $category->id,
            'kind' => $category->kind,
            'name' => $category->name,
            'is_active' => $category->is_active,
        ];
    }
}
