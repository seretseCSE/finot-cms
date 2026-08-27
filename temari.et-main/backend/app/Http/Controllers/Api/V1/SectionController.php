<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AcademicYearStatus;
use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreSectionRequest;
use App\Http\Requests\Api\V1\UpdateSectionRequest;
use App\Http\Resources\SectionResource;
use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\StudentTermResult;
use App\Models\Term;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Sections are stable class divisions; the homeroom teacher is YEAR-scoped
 * (section_homerooms). List + writes accept an `academic_year_id` — when
 * omitted, the branch's active year is used, so the page defaults to "now".
 */
class SectionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Section::class);
        $branch = $this->activeBranchOrNull($request);
        $schoolScopeId = $this->activeSchoolScopeId($request);

        $yearId = $request->filled('academic_year_id') ? $request->integer('academic_year_id') : null;

        // Homerooms are shown for the requested year, else each branch's active year.
        $homerooms = fn ($q) => $yearId !== null
            ? $q->where('academic_year_id', $yearId)->with('employee:id,first_name,father_name')
            : $q->whereHas('academicYear', fn ($y) => $y->where('status', AcademicYearStatus::Active->value))
                ->with('employee:id,first_name,father_name');

        $query = $branch
            ? $branch->sections()->with(['gradeLevel', 'homerooms' => $homerooms])
            : Section::query()
                ->when($schoolScopeId, fn ($q) => $q->where('sections.school_id', $schoolScopeId))
                ->when($this->branchFilterId($request, $branch), fn ($q, int $id) => $q->where('sections.branch_id', $id))
                ->when($this->schoolFilterId($request, $branch), fn ($q, int $id) => $q->where('sections.school_id', $id))
                ->with(['gradeLevel', 'branch.school', 'homerooms' => $homerooms]);

        $user = $request->user();

        // Grading pages (report cards, rosters) narrow the CALLER to exactly the
        // sections they homeroom for a specific year — the report-card/roster
        // data is homeroom-scoped there, so the picker must not offer a class
        // that would load nothing. It's a stricter, year-aware self-restriction
        // than the ownership lane below (which keys off the ACTIVE year and also
        // lets in teaching assignments), so it SUPERSEDES it — otherwise a past
        // year's homeroom, absent from the active-year ownership set, would be
        // filtered right back out.
        if ($branch !== null && $request->boolean('mine_homeroom')) {
            $query->whereIn(
                'sections.id',
                $yearId !== null
                    ? $user->homeroomSectionIds($yearId)
                    : $user->homeroomSectionIdsInBranch($branch->id),
            );
        } elseif ($branch !== null && ! $user->hasContextPermission('sections.view')) {
            // Ownership lane: a teacher (sections.view_own without sections.view)
            // sees only THEIR sections — homeroom or active teaching assignment.
            $query->whereIn('sections.id', $user->ownedSectionIds($branch->id));
        }

        // Attendance picker: the class register is homeroom territory, so the
        // page narrows a teacher's list to the sections they homeroom.
        if ($branch !== null && $request->boolean('homeroom_only') && ! $user->hasContextPermission('attendance.view')) {
            $query->whereIn('sections.id', $user->homeroomSectionIdsInBranch($branch->id));
        }

        $sections = $query
            ->when(
                $request->filled('grade_level_id'),
                fn ($q) => $q->where('grade_level_id', $request->integer('grade_level_id')),
            )
            ->join('grade_levels', 'grade_levels.id', '=', 'sections.grade_level_id')
            ->orderBy('grade_levels.sort_order')
            ->orderBy('sections.name')
            ->select('sections.*')
            ->paginate((int) min($request->integer('per_page', 25), 100));

        return SectionResource::collection($sections);
    }

    public function store(StoreSectionRequest $request): JsonResponse
    {
        $branch = $this->targetBranch($request);

        abort_unless(
            $request->user()->hasPermissionForScope('sections.create', $branch->school_id, $branch->id),
            403,
        );

        $data = $request->validated();

        $section = DB::transaction(function () use ($branch, $data): Section {
            // Recreating a deleted section resurrects the original row, so
            // historical enrollments and homerooms keep their section id.
            $section = Section::withTrashed()->firstOrNew([
                'branch_id' => $branch->id,
                'grade_level_id' => (int) $data['grade_level_id'],
                'name' => $data['name'],
            ]);

            if ($section->trashed()) {
                $section->restore();
            }

            $section->fill([
                'school_id' => $branch->school_id,
                'is_active' => true,
                ...Arr::except($data, ['homeroom_employee_id', 'academic_year_id']),
            ])->save();

            $this->applyHomeroom($section, $data);

            return $section;
        });

        return (new SectionResource($section->load($this->detailRelations($data))))
            ->additional(['message' => 'Section created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Section $section): SectionResource
    {
        $this->authorize('view', $section);

        return new SectionResource($section->load($this->detailRelations($request->all())));
    }

    /**
     * The class profile for ONE semester: the section's active roster with
     * each student's frozen term result (average /100, rank, letter…), the
     * year's homeroom teacher and headline stats. Marks appear only for
     * users holding grades.view in the section's scope.
     */
    public function roster(Request $request, Section $section): JsonResponse
    {
        $this->authorize('view', $section);

        $request->validate(['term_id' => ['required', 'integer', 'exists:terms,id']]);

        $term = Term::query()->with('academicYear:id,name')->findOrFail($request->integer('term_id'));
        abort_unless($term->branch_id === $section->branch_id, 404);

        $section->load('gradeLevel:id,name,sort_order');

        $homeroom = $section->homerooms()
            ->where('academic_year_id', $term->academic_year_id)
            ->with('employee:id,first_name,father_name,grandfather_name,phone,user_id')
            ->first();

        // Marks show for supervisory grades.view — or for THIS section's own
        // homeroom teacher, who assembles the report cards (grades.manage_own).
        $user = $request->user();
        $showMarks = $user->hasPermissionForScope('grades.view', $section->school_id, $section->branch_id)
            || ($user->hasPermissionForScope('grades.manage_own', $section->school_id, $section->branch_id)
                && in_array($section->id, $user->homeroomSectionIds($term->academic_year_id), true));

        // Pending enrollments hold seats but appear on NO class list.
        $enrollments = $section->enrollments()
            ->where('academic_year_id', $term->academic_year_id)
            ->where('status', EnrollmentStatus::Active->value)
            ->with('student:id,public_id,first_name,father_name,grandfather_name,gender,date_of_birth,photo_path')
            ->get();

        $results = $showMarks
            ? StudentTermResult::query()
                ->where('term_id', $term->id)
                ->where('section_id', $section->id)
                ->get()
                ->keyBy('student_enrollment_id')
            : collect();

        $students = $enrollments
            ->sortBy(fn ($e) => $e->student?->full_name)
            ->values()
            ->map(function ($enrollment) use ($results): array {
                $student = $enrollment->student;
                $result = $results->get($enrollment->id);

                return [
                    'enrollment_id' => $enrollment->id,
                    'student_id' => $student?->id,
                    'public_id' => $student?->public_id,
                    'full_name' => $student?->full_name,
                    'gender' => $student?->gender?->value,
                    'date_of_birth' => $student?->date_of_birth?->toDateString(),
                    'photo_url' => $student?->photo_url,
                    'enrolled_on' => $enrollment->enrolled_on?->toDateString(),
                    'result' => $result === null ? null : [
                        'average' => $result->average !== null ? (float) $result->average : null,
                        'rank' => $result->rank,
                        'rank_of' => $result->rank_of,
                        'letter' => $result->grading['overall']['letter'] ?? null,
                        'is_passing' => isset($result->grading['overall'])
                            ? (bool) ($result->grading['overall']['is_passing'] ?? false)
                            : null,
                        'conduct' => $result->conduct,
                        'absence_days' => $result->absence_days,
                    ],
                ];
            });

        $withAverage = $results->whereNotNull('average');
        $graded = $withAverage->filter(fn ($r) => isset($r->grading['overall']));
        $passing = $graded->filter(fn ($r) => (bool) ($r->grading['overall']['is_passing'] ?? false));

        return response()->json([
            'data' => [
                'section' => [
                    'id' => $section->id,
                    'name' => $section->name,
                    'grade_level' => $section->gradeLevel?->name,
                    'room_number' => $section->room_number,
                    'capacity' => $section->capacity,
                    'is_active' => $section->is_active,
                ],
                'term' => [
                    'id' => $term->id,
                    'name' => $term->name,
                    'status' => $term->status->value,
                    'academic_year_id' => $term->academic_year_id,
                    'academic_year_name' => $term->academicYear?->name,
                ],
                'homeroom' => $homeroom?->employee === null ? null : [
                    'employee_id' => $homeroom->employee->id,
                    'user_id' => $homeroom->employee->user_id,
                    'name' => $homeroom->employee->full_name,
                    'phone' => $homeroom->employee->phone,
                ],
                'subjects_count' => $section->subjectAssignments()
                    ->where('term_id', $term->id)
                    ->where('is_active', true)
                    ->count(),
                'can_view_marks' => $showMarks,
                'summary' => [
                    'students' => $enrollments->count(),
                    'male' => $enrollments->filter(fn ($e) => $e->student?->gender?->value === 'male')->count(),
                    'female' => $enrollments->filter(fn ($e) => $e->student?->gender?->value === 'female')->count(),
                    'with_results' => $withAverage->count(),
                    'average' => $withAverage->isEmpty() ? null : round((float) $withAverage->avg('average'), 2),
                    'pass_rate' => $graded->isEmpty()
                        ? null
                        : round($passing->count() / $graded->count() * 100, 1),
                ],
                'students' => $students,
            ],
        ]);
    }

    public function update(UpdateSectionRequest $request, Section $section): SectionResource
    {
        $this->authorize('update', $section);

        $data = $request->validated();

        DB::transaction(function () use ($section, $data): void {
            $section->update(Arr::except($data, ['homeroom_employee_id', 'academic_year_id']));
            $this->applyHomeroom($section, $data);
        });

        return new SectionResource($section->load($this->detailRelations($data)));
    }

    public function destroy(Section $section): JsonResponse
    {
        $this->authorize('delete', $section);

        $section->delete();

        return response()->json(['message' => 'Section deleted.']);
    }

    /**
     * Persist the homeroom for the payload's year (default: the branch's
     * active year). Only acts when the key is present in the payload.
     *
     * @param  array<string, mixed>  $data
     */
    private function applyHomeroom(Section $section, array $data): void
    {
        if (! array_key_exists('homeroom_employee_id', $data)) {
            return;
        }

        $yearId = $data['academic_year_id'] ?? $this->activeYearId($section->branch_id);

        // A homeroom must anchor to a year — failing silently would look like
        // the selection "didn't save" on the page.
        abort_if(
            $yearId === null && $data['homeroom_employee_id'] !== null,
            422,
            'Create an academic year before assigning homeroom teachers.',
        );

        if ($yearId !== null) {
            $section->setHomeroom((int) $yearId, $data['homeroom_employee_id']);
        }
    }

    private function activeYearId(int $branchId): ?int
    {
        return AcademicYear::query()
            ->where('branch_id', $branchId)
            ->where('status', AcademicYearStatus::Active->value)
            ->value('id');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function detailRelations(array $data): array
    {
        $yearId = $data['academic_year_id'] ?? null;

        return [
            'gradeLevel',
            'homerooms' => fn ($q) => $yearId
                ? $q->where('academic_year_id', $yearId)->with('employee:id,first_name,father_name')
                : $q->whereHas('academicYear', fn ($y) => $y->where('status', AcademicYearStatus::Active->value))
                    ->with('employee:id,first_name,father_name'),
        ];
    }
}
