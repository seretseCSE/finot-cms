<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreSubjectRequest;
use App\Http\Requests\Api\V1\UpdateSubjectRequest;
use App\Http\Resources\SubjectResource;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SubjectController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Subject::class);

        $user = $request->user();
        $schoolId = $user?->activeSchoolId();

        $subjects = Subject::query()
            // Platform staff in the global context see every school's subjects;
            // otherwise scope to platform-global subjects plus the active school's.
            ->when(
                ! $user?->isPlatformUser(),
                fn ($q) => $q->where(fn ($inner) => $inner->whereNull('school_id')->orWhere('school_id', $schoolId)),
            )
            ->where('is_active', true)
            ->with(['school', 'gradeLevels:grade_levels.id,sort_order'])
            ->orderBy('code')
            ->paginate((int) min($request->integer('per_page', 100), 100));

        return SubjectResource::collection($subjects);
    }

    public function store(StoreSubjectRequest $request): JsonResponse
    {
        $this->authorize('create', Subject::class);

        $subject = DB::transaction(function () use ($request): Subject {
            $subject = Subject::create([
                ...Arr::except($request->validated(), ['grade_level_ids']),
                'school_id' => $request->user()?->activeSchoolId(),
            ]);
            $subject->gradeLevels()->sync($request->validated('grade_level_ids') ?? []);

            return $subject;
        });

        return (new SubjectResource($subject->load('gradeLevels:grade_levels.id,sort_order')))
            ->additional(['message' => 'Subject created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Subject $subject): SubjectResource
    {
        $this->authorize('viewAny', Subject::class);

        return new SubjectResource($subject->load('gradeLevels:grade_levels.id,sort_order'));
    }

    public function update(UpdateSubjectRequest $request, Subject $subject): SubjectResource
    {
        $this->authorize('update', $subject);

        DB::transaction(function () use ($request, $subject): void {
            $subject->update(Arr::except($request->validated(), ['grade_level_ids']));

            if ($request->has('grade_level_ids')) {
                $subject->gradeLevels()->sync($request->validated('grade_level_ids') ?? []);
            }
        });

        return new SubjectResource($subject->load('gradeLevels:grade_levels.id,sort_order'));
    }

    public function destroy(Subject $subject): JsonResponse
    {
        $this->authorize('delete', $subject);

        $subject->delete();

        return response()->json(['message' => 'Subject deleted.']);
    }
}
