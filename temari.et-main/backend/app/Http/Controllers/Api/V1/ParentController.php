<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Http\Resources\ParentResource;
use App\Models\ParentProfile;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Staff-facing parents register. Parents are GLOBAL persons — a school only
 * ever sees the parents of students it administers (guardianship → student →
 * provenance-or-enrollment scope, mirroring StudentController). Managing a
 * parent's files still flows through ParentProfile::adminScopes().
 */
class ParentController extends Controller
{
    use HandlesListQueries;

    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->hasContextPermission('guardians.view'), 403);

        $query = $this->baseQuery($request)
            ->with(['user'])
            ->withCount(['guardianships as children_count' => fn ($q) => $q->where('is_active', true)]);

        $this->applyFilters($query, $request);
        $this->applySort($query, $request, ['first_name', 'created_at', 'occupation', 'children_count'], 'created_at');

        return ParentResource::collection($query->paginate($this->perPage($request)));
    }

    public function export(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->hasContextPermission('guardians.view'), 403);

        $query = $this->baseQuery($request)
            ->with(['user'])
            ->withCount(['guardianships as children_count' => fn ($q) => $q->where('is_active', true)]);

        $this->applyFilters($query, $request);

        return ParentResource::collection($query->orderBy('created_at')->limit(5000)->get());
    }

    public function show(Request $request, ParentProfile $parent): ParentResource
    {
        $allowed = collect($parent->adminScopes())->contains(
            fn (array $scope) => $request->user()->hasPermissionForScope('guardians.view', $scope[0], $scope[1]),
        );
        abort_unless($allowed || $request->user()->isSuperAdmin(), 403);

        $parent->load([
            'user',
            'guardianships.student.currentEnrollment.gradeLevel',
            'guardianships.student.currentEnrollment.branch.school',
        ])->loadCount(['guardianships as children_count' => fn ($q) => $q->where('is_active', true)]);

        // Family documents are live data, not archive material: they load only
        // for staff whose scope holds live custody of a linked child. A former
        // school (all children transferred/withdrawn) sees the bare profile.
        $custodial = $request->user()->isSuperAdmin()
            || collect($parent->activeAdminScopes())->contains(
                fn (array $scope) => $request->user()->hasPermissionForScope('guardians.view', $scope[0], $scope[1]),
            );

        if ($custodial) {
            $parent->load('attachments');
        }

        return new ParentResource($parent);
    }

    /**
     * Parents visible in the active context = guardians of a student the
     * context administers (registered here OR enrolled here).
     *
     * @return Builder<ParentProfile>
     */
    private function baseQuery(Request $request): Builder
    {
        $branch = $this->activeBranchOrNull($request);
        $schoolScopeId = $this->activeSchoolScopeId($request);

        return ParentProfile::query()
            ->when($branch, function ($q) use ($branch): void {
                $q->whereHas('guardianships.student', function ($s) use ($branch): void {
                    $s->where(function ($inner) use ($branch): void {
                        $inner->where('branch_id', $branch->id)
                            ->orWhereHas('enrollments', fn ($e) => $e->where('branch_id', $branch->id));
                    });
                });
            })
            ->when(! $branch && $schoolScopeId, function ($q) use ($schoolScopeId): void {
                $q->whereHas('guardianships.student', function ($s) use ($schoolScopeId): void {
                    $s->where(function ($inner) use ($schoolScopeId): void {
                        $inner->where('school_id', $schoolScopeId)
                            ->orWhereHas('enrollments', fn ($e) => $e->where('school_id', $schoolScopeId));
                    });
                });
            })
            // Toolbar narrowing (school → branch cascade; platform sees school first).
            ->when($request->filled('branch_id'), function ($q) use ($request): void {
                $id = $request->integer('branch_id');
                $q->whereHas('guardianships.student', function ($s) use ($id): void {
                    $s->where(function ($inner) use ($id): void {
                        $inner->where('branch_id', $id)
                            ->orWhereHas('enrollments', fn ($e) => $e->where('branch_id', $id));
                    });
                });
            })
            ->when(! $schoolScopeId && $request->filled('school_id'), function ($q) use ($request): void {
                $id = $request->integer('school_id');
                $q->whereHas('guardianships.student', function ($s) use ($id): void {
                    $s->where(function ($inner) use ($id): void {
                        $inner->where('school_id', $id)
                            ->orWhereHas('enrollments', fn ($e) => $e->where('school_id', $id));
                    });
                });
            });
    }

    /**
     * @param  Builder<ParentProfile>  $query
     */
    private function applyFilters(Builder $query, Request $request): void
    {
        // The account's `search_text` (name, phone raw + digits-only, email,
        // public id) plus the profile's own name parts and occupation — the
        // profile row carries the names for parents with no login yet.
        $this->applySearch($query, $request, fn ($q, string $n) => $q
            ->where('search_text', 'ilike', $this->needle($n))
            ->orWhere('occupation', 'ilike', $this->needle($n))
            ->orWhereHas('user', fn ($u) => $u->where('search_text', 'ilike', $this->needle($n))));

        $this->applyBooleanFilter($query, $request, 'is_verified', 'is_verified');

        // Login state: a parent "has a login" once their PIN is set — the
        // users row itself always exists (provisioned at registration).
        if ($request->filled('has_login')) {
            $wantsLogin = $request->boolean('has_login');
            $query->whereHas('user', fn ($u) => $wantsLogin
                ? $u->whereNotNull('password')
                : $u->whereNull('password'));
        }

        $this->applyDateRange($query, $request, 'created_at', 'created_from', 'created_to');
    }
}
