<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Returns the schools/branches the authenticated user may switch into. Drives
 * the frontend context switcher so that school managers (principal/school_admin)
 * can drill into any branch of their school, and platform staff into any school.
 */
class ContextController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $schools = $user->isPlatformUser()
            ? $this->platformContexts()
            : $this->membershipContexts($user);

        return response()->json([
            'data' => [
                'is_platform' => $user->isPlatformUser(),
                'schools' => $schools->values(),
            ],
        ]);
    }

    /**
     * Platform staff may operate in every active school and branch.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function platformContexts(): Collection
    {
        return School::query()
            ->where('is_active', true)
            ->with(['branches' => fn ($q) => $q->where('is_active', true)->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->map(fn (School $school): array => [
                // Branches were loaded off the school — pin the inverse so the
                // effective-mode fallback never lazy-loads (tripwire-safe).
                'id' => $school->id,
                'name' => $school->name,
                'logo_url' => $school->logoUrl(),
                'can_manage' => true,
                'calendar_mode' => $school->calendarMode(),
                'clock_mode' => $school->clockMode(),
                'branches' => $school->branches->each->setRelation('school', $school)->map(fn (Branch $b): array => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'calendar_mode' => $b->effectiveCalendarMode(),
                    'clock_mode' => $b->effectiveClockMode(),
                ])->all(),
            ]);
    }

    /**
     * For a normal user, derive contexts from active memberships: a school-level
     * role unlocks every branch of that school; a branch-level role unlocks only
     * that branch.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function membershipContexts(User $user): Collection
    {
        $memberships = $user->memberships()
            ->where('is_active', true)
            ->whereNotNull('school_id')
            ->get();

        $schoolIds = $memberships->pluck('school_id')->unique()->all();
        $schools = School::query()
            ->whereIn('id', $schoolIds)
            ->with(['branches' => fn ($q) => $q->where('is_active', true)->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        return collect($schoolIds)
            ->map(function (int $schoolId) use ($memberships, $schools, $user): ?array {
                $school = $schools->get($schoolId);
                if ($school === null) {
                    return null;
                }

                $canManage = $user->managesSchool($schoolId);

                $branches = $canManage
                    ? $school->branches
                    : $school->branches->whereIn(
                        'id',
                        $memberships->where('school_id', $schoolId)->pluck('branch_id')->filter()->all(),
                    );

                return [
                    'id' => $school->id,
                    'name' => $school->name,
                    'logo_url' => $school->logoUrl(),
                    'can_manage' => $canManage,
                    'calendar_mode' => $school->calendarMode(),
                    'clock_mode' => $school->clockMode(),
                    'branches' => $branches->each->setRelation('school', $school)->map(fn (Branch $b): array => [
                        'id' => $b->id,
                        'name' => $b->name,
                        'calendar_mode' => $b->effectiveCalendarMode(),
                        'clock_mode' => $b->effectiveClockMode(),
                    ])->values()->all(),
                ];
            })
            ->filter();
    }
}
