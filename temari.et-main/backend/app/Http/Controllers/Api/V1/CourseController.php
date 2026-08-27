<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\LessonProgress;
use App\Models\Quiz;
use App\Models\SubjectAssignment;
use App\Support\SearchTerm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The course studio — staff lane (ADR-016). One engine, three scopes:
 * platform courses (`?platform=1`, exam_prep.manage — the EUEE catalog),
 * school/branch courses (lms.manage, grade-windowed), and class courses
 * (the owning teacher, anchored to a subject_assignment). Structure is
 * modules → lessons; quiz lessons reference the quiz engine.
 */
class CourseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Course::query()
            ->with([
                'subject:id,name', 'branch:id,name', 'creator:id,name',
                'subjectAssignment.section:id,name', 'targets.subjectAssignment.section:id,name',
            ])
            ->withCount(['modules', 'lessons'])
            ->orderByDesc('id');

        if ($request->boolean('platform')) {
            abort_unless($user->hasPlatformPermission('exam_prep.manage'), 403);
            $query->whereNull('school_id');
        } else {
            $branch = $this->activeBranchOrNull($request);
            $schoolId = $branch?->school_id ?? $this->activeSchoolScopeId($request);
            abort_if($schoolId === null, 422, 'Select a school context to view courses.');

            if ($user->hasPermissionForScope('lms.view', $schoolId, $branch?->id)) {
                $query->where('school_id', $schoolId)
                    ->when($branch !== null, fn ($q) => $q->where(
                        fn ($w) => $w->whereNull('branch_id')->orWhere('branch_id', $branch->id),
                    ))
                    ->when($this->branchFilterId($request, $branch), fn ($q, $id) => $q->where(
                        fn ($w) => $w->whereNull('branch_id')->orWhere('branch_id', $id),
                    ));
            } elseif ($user->hasPermissionForScope('lms.manage_own', $schoolId, $branch?->id)) {
                // Teachers: their own class courses + what they authored.
                $query->where(fn ($q) => $q
                    ->where('created_by', $user->id)
                    ->orWhereHas('subjectAssignment.employee', fn ($e) => $e->where('user_id', $user->id))
                    ->orWhereHas('targets.subjectAssignment.employee', fn ($e) => $e->where('user_id', $user->id)),
                );
            } else {
                abort(403);
            }
        }

        $courses = $query
            ->when($request->filled('subject_id'), fn ($q) => $q->where('subject_id', $request->integer('subject_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->tap(fn ($query) => SearchTerm::apply($query, $request->string('q')->trim()->value(), fn ($w, string $n) => $w
                ->where('title', 'ilike', SearchTerm::contains($n))))
            ->paginate(min($request->integer('per_page', 25), 100));

        return CourseResource::collection($courses)->response();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request, creating: true);
        $user = $request->user();

        $targetIds = $this->requestedTargetIds($data);

        if ($request->boolean('platform')) {
            abort_unless($user->hasPlatformPermission('exam_prep.manage'), 403);
            $anchor = ['school_id' => null, 'branch_id' => null, 'subject_assignment_id' => null];
            $targetIds = collect();
        } elseif ($targetIds->isNotEmpty()) {
            // A class course — targeted at one or more of the poster's classes
            // (the first is the ANCHOR, like exams/materials).
            $classes = $this->authorizedTargets($request, $targetIds);
            $first = $classes->first();
            $anchor = [
                'school_id' => $first->school_id,
                'branch_id' => $first->branch_id,
                'subject_assignment_id' => $first->id,
            ];
        } elseif ($request->boolean('school_wide')) {
            $branch = $this->activeBranchOrNull($request);
            $schoolId = $branch?->school_id ?? $this->activeSchoolScopeId($request);
            abort_if($schoolId === null, 422, 'Select a school context to add a course.');
            abort_unless($user->hasPermissionForScope('lms.manage', $schoolId, null), 403);
            $anchor = ['school_id' => $schoolId, 'branch_id' => null, 'subject_assignment_id' => null];
        } else {
            $branch = $this->targetBranch($request);
            abort_unless($user->hasPermissionForScope('lms.manage', $branch->school_id, $branch->id), 403);
            $anchor = ['school_id' => $branch->school_id, 'branch_id' => $branch->id, 'subject_assignment_id' => null];
        }

        $course = Course::create([
            ...$anchor,
            'title' => $data['title'],
            'description' => $this->cleanRichText($data['description'] ?? null),
            'subject_id' => $data['subject_id'] ?? null,
            'min_grade_sort' => $data['min_grade_sort'] ?? null,
            'max_grade_sort' => $data['max_grade_sort'] ?? null,
            'stream' => $data['stream'] ?? null,
            'language' => $data['language'] ?? 'en',
            'cover_path' => $this->storeCover($request),
            'is_sequential' => $data['is_sequential'] ?? false,
            'created_by' => $user->id,
        ]);

        foreach ($targetIds as $id) {
            $course->targets()->create(['subject_assignment_id' => $id]);
        }

        return (new CourseResource($course
            ->load(['subject:id,name', 'targets.subjectAssignment.section:id,name'])
            ->loadCount(['modules', 'lessons'])))
            ->additional(['message' => 'Course created.'])
            ->response()
            ->setStatusCode(201);
    }

    /** The full builder tree: modules with lessons (keys stay staff-side). */
    public function show(Course $course): JsonResponse
    {
        $this->authorize('view', $course);

        $course->load([
            'subject:id,name', 'branch:id,name', 'creator:id,name',
            'subjectAssignment.section:id,name',
            'targets.subjectAssignment.section:id,name',
            'modules.lessons.quiz:id,title,status',
        ])->loadCount(['modules', 'lessons']);

        $payload = (new CourseResource($course))->resolve();
        $payload['modules'] = $course->modules->map(fn (CourseModule $module): array => [
            'id' => $module->id,
            'title' => $module->title,
            'description' => $module->description,
            'sort_order' => $module->sort_order,
            'lessons' => $module->lessons->map(fn (CourseLesson $lesson): array => $this->lessonRow($lesson)),
        ]);

        return response()->json(['data' => $payload]);
    }

    public function update(Request $request, Course $course): JsonResponse
    {
        $this->authorize('update', $course);

        $data = $this->validatePayload($request, creating: false);

        $updates = collect($data)->only([
            'title', 'subject_id', 'min_grade_sort', 'max_grade_sort',
            'stream', 'language', 'is_sequential',
        ])->all();

        if (array_key_exists('description', $data)) {
            $updates['description'] = $this->cleanRichText($data['description']);
        }

        // Status rides the same autosave payload: publishing demands content
        // (mirrors the publish endpoint); re-drafting/archiving is free.
        if (isset($data['status']) && $data['status'] !== $course->status) {
            if ($data['status'] === 'published' && ! $course->lessons()->exists()) {
                throw ValidationException::withMessages(['status' => ['Add at least one lesson before publishing.']]);
            }
            $updates['status'] = $data['status'];
            if ($data['status'] === 'published') {
                $updates['published_at'] = $course->published_at ?? now();
            }
        }

        if (($cover = $this->storeCover($request)) !== null) {
            if ($course->cover_path !== null) {
                Storage::disk(config('filesystems.default'))->delete($course->cover_path);
            }
            $updates['cover_path'] = $cover;
        }

        $course->update($updates);

        // Re-target: the anchor follows the first id (never orphan a class course).
        if (! $course->isPlatform() && isset($data['subject_assignment_ids'])) {
            $targetIds = $this->requestedTargetIds($data);

            if ($targetIds->isNotEmpty()) {
                $classes = $this->authorizedTargets($request, $targetIds);
                $course->update(['subject_assignment_id' => $classes->first()->id]);
                $course->targets()->whereNotIn('subject_assignment_id', $targetIds)->delete();
                foreach ($targetIds as $id) {
                    $course->targets()->firstOrCreate(['subject_assignment_id' => $id]);
                }
            } elseif ($course->subject_assignment_id === null) {
                // Grade-window/school-wide course explicitly clearing targets.
                $course->targets()->delete();
            }
        }

        return (new CourseResource($course->refresh()
            ->load(['subject:id,name', 'targets.subjectAssignment.section:id,name'])
            ->loadCount(['modules', 'lessons'])))->response();
    }

    public function publish(Course $course): JsonResponse
    {
        $this->authorize('update', $course);

        if (! $course->lessons()->exists()) {
            throw ValidationException::withMessages(['modules' => ['Add at least one lesson before publishing.']]);
        }

        $course->update(['status' => 'published', 'published_at' => $course->published_at ?? now()]);

        return (new CourseResource($course->loadCount(['modules', 'lessons'])))
            ->additional(['message' => 'Course published.'])
            ->response();
    }

    public function archive(Course $course): JsonResponse
    {
        $this->authorize('update', $course);

        $course->update(['status' => 'archived']);

        return (new CourseResource($course->loadCount(['modules', 'lessons'])))
            ->additional(['message' => 'Course archived.'])
            ->response();
    }

    public function destroy(Course $course): JsonResponse
    {
        $this->authorize('delete', $course);

        if ($course->status === 'published' && LessonProgress::query()->where('course_id', $course->id)->exists()) {
            $course->update(['status' => 'archived']);

            return response()->json(['message' => 'Learners have progress here — the course was archived instead of deleted.']);
        }

        $course->delete();

        return response()->json(['message' => 'Course deleted.']);
    }

    // ── modules ──────────────────────────────────────────────────────────

    public function storeModule(Request $request, Course $course): JsonResponse
    {
        $this->authorize('update', $course);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $module = $course->modules()->create([
            ...$data,
            'sort_order' => ((int) $course->modules()->max('sort_order')) + 1,
        ]);

        return response()->json([
            'data' => ['id' => $module->id, 'title' => $module->title, 'description' => $module->description, 'sort_order' => $module->sort_order, 'lessons' => []],
            'message' => 'Module added.',
        ], 201);
    }

    public function updateModule(Request $request, CourseModule $courseModule): JsonResponse
    {
        $this->authorize('update', $courseModule->course);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $courseModule->update($data);

        return response()->json(['data' => ['id' => $courseModule->id, 'title' => $courseModule->title, 'description' => $courseModule->description]]);
    }

    public function destroyModule(CourseModule $courseModule): JsonResponse
    {
        $this->authorize('update', $courseModule->course);

        $courseModule->lessons()->delete();
        $courseModule->delete();

        return response()->json(['message' => 'Module deleted.']);
    }

    // ── lessons ──────────────────────────────────────────────────────────

    public function storeLesson(Request $request, CourseModule $courseModule): JsonResponse
    {
        $course = $courseModule->course;
        $this->authorize('update', $course);

        $data = $this->validateLesson($request, creating: true);
        $this->assertLessonQuizInScope($data, $course);

        $lesson = $courseModule->lessons()->create([
            'course_id' => $course->id,
            'type' => $data['type'],
            'title' => $data['title'],
            'content' => $this->lessonContent($request, $data),
            'quiz_id' => $data['type'] === 'quiz' ? ($data['quiz_id'] ?? null) : null,
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'is_preview' => $data['is_preview'] ?? false,
            'sort_order' => ((int) $courseModule->lessons()->max('sort_order')) + 1,
        ]);

        return response()->json(['data' => $this->lessonRow($lesson), 'message' => 'Lesson added.'], 201);
    }

    public function updateLesson(Request $request, CourseLesson $courseLesson): JsonResponse
    {
        $this->authorize('update', $courseLesson->course);

        $data = $this->validateLesson($request, creating: false);
        $this->assertLessonQuizInScope($data, $courseLesson->course);

        $updates = collect($data)->only(['type', 'title', 'quiz_id', 'duration_minutes', 'is_preview'])->all();

        $type = $updates['type'] ?? $courseLesson->type;
        if ($type !== 'quiz') {
            $updates['quiz_id'] = null;
        }

        $content = $this->lessonContent($request, [...$data, 'type' => $type], $courseLesson);
        if ($content !== null) {
            $updates['content'] = $content;
        }

        $courseLesson->update($updates);

        return response()->json(['data' => $this->lessonRow($courseLesson->refresh())]);
    }

    public function destroyLesson(CourseLesson $courseLesson): JsonResponse
    {
        $this->authorize('update', $courseLesson->course);

        $courseLesson->delete();

        return response()->json(['message' => 'Lesson deleted.']);
    }

    /** One call re-orders the whole tree (drag-and-drop save). */
    public function reorder(Request $request, Course $course): JsonResponse
    {
        $this->authorize('update', $course);

        $data = $request->validate([
            'modules' => ['required', 'array'],
            'modules.*.id' => ['required', 'integer'],
            'modules.*.lesson_ids' => ['sometimes', 'array'],
            'modules.*.lesson_ids.*' => ['integer'],
        ]);

        $moduleIds = $course->modules()->pluck('id')->map(fn ($id): int => (int) $id);
        $lessonIds = $course->lessons()->pluck('id')->map(fn ($id): int => (int) $id);

        foreach (array_values($data['modules']) as $index => $entry) {
            if (! $moduleIds->contains((int) $entry['id'])) {
                continue;
            }

            CourseModule::whereKey((int) $entry['id'])->update(['sort_order' => $index]);

            foreach (array_values($entry['lesson_ids'] ?? []) as $lessonIndex => $lessonId) {
                if (! $lessonIds->contains((int) $lessonId)) {
                    continue;
                }

                CourseLesson::whereKey((int) $lessonId)->update([
                    'course_module_id' => (int) $entry['id'],
                    'sort_order' => $lessonIndex,
                ]);
            }
        }

        return response()->json(['message' => 'Order saved.']);
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function validatePayload(Request $request, bool $creating): array
    {
        return $request->validate([
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'subject_assignment_id' => ['sometimes', 'nullable', 'integer', 'exists:subject_assignments,id'],
            'subject_assignment_ids' => ['sometimes', 'array', 'max:1000'],
            'subject_assignment_ids.*' => ['integer', 'exists:subject_assignments,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'min_grade_sort' => ['nullable', 'integer', 'min:1', 'max:50'],
            'max_grade_sort' => ['nullable', 'integer', 'min:1', 'max:50', 'gte:min_grade_sort'],
            'stream' => ['nullable', Rule::in(Course::STREAMS)],
            'language' => ['sometimes', 'string', 'in:en,am,om'],
            'is_sequential' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(Course::STATUSES)],
            'cover' => ['sometimes', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
            'platform' => ['sometimes', 'boolean'],
            'school_wide' => ['sometimes', 'boolean'],
            'branch_id' => ['sometimes', 'nullable', 'integer'],
        ]);
    }

    /**
     * The requested audience as one flat id list — the array form wins, the
     * legacy single `subject_assignment_id` folds into it.
     *
     * @return Collection<int, int>
     */
    private function requestedTargetIds(array $data): Collection
    {
        $ids = collect($data['subject_assignment_ids'] ?? [])->map(fn ($id): int => (int) $id);

        if ($ids->isEmpty() && isset($data['subject_assignment_id'])) {
            $ids = collect([(int) $data['subject_assignment_id']]);
        }

        return $ids->unique()->values();
    }

    /**
     * Load and authorize every targeted class: the caller must manage each
     * one, and all must share one school.
     *
     * @param  Collection<int, int>  $targetIds
     * @return Collection<int, SubjectAssignment>
     */
    private function authorizedTargets(Request $request, Collection $targetIds): Collection
    {
        $classes = SubjectAssignment::query()->findMany($targetIds);
        abort_unless($classes->count() === $targetIds->count(), 422);

        // Preserve the caller's order — the first id is the anchor.
        $classes = $targetIds->map(fn (int $id) => $classes->firstWhere('id', $id));

        foreach ($classes as $class) {
            abort_unless($this->mayManageAnchor($request, $class), 403);
        }

        $schoolId = (int) $classes->first()->school_id;
        abort_unless(
            $classes->every(fn (SubjectAssignment $a): bool => (int) $a->school_id === $schoolId),
            422,
            'All target classes must belong to one school.',
        );

        return $classes;
    }

    private function validateLesson(Request $request, bool $creating): array
    {
        return $request->validate([
            'type' => [$creating ? 'required' : 'sometimes', Rule::in(CourseLesson::TYPES)],
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:2000'],
            'body' => ['nullable', 'string', 'max:100000'],
            'file' => ['sometimes', 'file', 'max:102400',
                'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,jpg,jpeg,png,webp,mp3,mp4,zip'],
            'quiz_id' => ['nullable', 'integer', 'exists:quizzes,id'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'is_preview' => ['sometimes', 'boolean'],
        ]);
    }

    /** Quiz lessons may only reference quizzes of the SAME scope. */
    private function assertLessonQuizInScope(array $data, Course $course): void
    {
        $quizId = $data['quiz_id'] ?? null;

        if ($quizId === null || ($data['type'] ?? null) !== 'quiz') {
            return;
        }

        $quiz = Quiz::findOrFail((int) $quizId);

        $ok = $course->isPlatform()
            ? $quiz->is_platform
            : ((int) $quiz->school_id === (int) $course->school_id);

        if (! $ok) {
            throw ValidationException::withMessages(['quiz_id' => ['The quiz must belong to the same scope as the course.']]);
        }
    }

    /**
     * Build the content payload per type; null means "leave unchanged" on
     * update. Video: a URL (YouTube detected client-side); reading: markdown
     * body; file: an R2 upload.
     */
    private function lessonContent(Request $request, array $data, ?CourseLesson $existing = null): ?array
    {
        $type = $data['type'] ?? $existing?->type;

        if ($type === 'video' && isset($data['url'])) {
            return ['url' => $data['url']];
        }

        if ($type === 'reading' && isset($data['body'])) {
            return ['body' => $this->cleanRichText($data['body'])];
        }

        if ($type === 'file' && $request->hasFile('file')) {
            if (isset($existing->content['path'])) {
                Storage::disk(config('filesystems.default'))->delete($existing->content['path']);
            }

            $file = $request->file('file');
            $path = $file->store('lms/lessons', ['disk' => config('filesystems.default')]);

            return [
                'path' => $path,
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
            ];
        }

        return $existing === null ? [] : null;
    }

    private function lessonRow(CourseLesson $lesson): array
    {
        $content = $lesson->content ?? [];

        return [
            'id' => $lesson->id,
            'course_module_id' => $lesson->course_module_id,
            'type' => $lesson->type,
            'title' => $lesson->title,
            'duration_minutes' => $lesson->duration_minutes,
            'is_preview' => $lesson->is_preview,
            'sort_order' => $lesson->sort_order,
            'quiz_id' => $lesson->quiz_id,
            'quiz_title' => $lesson->relationLoaded('quiz') ? $lesson->quiz?->title : null,
            'content' => match ($lesson->type) {
                'file' => [
                    'name' => $content['name'] ?? $lesson->title,
                    'size' => $content['size'] ?? null,
                    'mime_type' => $content['mime_type'] ?? null,
                    'url' => isset($content['path']) ? s3Url($content['path']) : null,
                ],
                'reading' => ['body' => $lesson->presentBody()],
                default => $content,
            },
        ];
    }

    private function storeCover(Request $request): ?string
    {
        if (! $request->hasFile('cover')) {
            return null;
        }

        return $request->file('cover')->store('lms/course-covers', ['disk' => config('filesystems.default')]);
    }

    private function mayManageAnchor(Request $request, SubjectAssignment $anchor): bool
    {
        $user = $request->user();

        return $user->hasPermissionForScope('lms.manage', $anchor->school_id, $anchor->branch_id)
            || ($user->hasPermissionForScope('lms.manage_own', $anchor->school_id, $anchor->branch_id)
                && $anchor->isOwnedBy($user));
    }
}
