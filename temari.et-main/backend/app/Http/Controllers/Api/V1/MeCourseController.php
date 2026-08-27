<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EnrollmentStatus;
use App\Enums\QuizAttemptStatus;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\LessonProgress;
use App\Models\QuizAttempt;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\SubjectAssignment;
use App\Support\SearchTerm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * THE LEARNER'S COURSE LANE (ADR-012 + ADR-016). Everything here keys on the
 * USER: platform courses admit anyone authenticated (no-school B2C learners
 * are first-class); a linked student additionally reaches their school's and
 * their classes' courses. Sequential courses lock later lessons until the
 * earlier ones are done; quiz lessons complete only through a submitted
 * attempt on the linked quiz — the server never takes the client's word.
 */
class MeCourseController extends Controller
{
    /** My course shelf: everything I can take, with progress. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $courses = $this->visibleCourses($user->id)
            ->when($request->filled('subject_id'), fn ($q) => $q->where('subject_id', $request->integer('subject_id')))
            ->when($request->filled('stream'), fn ($q) => $q->where(
                fn ($w) => $w->whereNull('stream')->orWhere('stream', $request->string('stream')),
            ))
            ->tap(fn ($query) => SearchTerm::apply($query, $request->string('q')->trim()->value(), fn ($w, string $n) => $w
                ->where('title', 'ilike', SearchTerm::contains($n))))
            ->with('subject:id,name')
            ->withCount('lessons')
            ->orderByDesc('id')
            ->paginate(min($request->integer('per_page', 25), 100));

        $completed = LessonProgress::query()
            ->where('user_id', $user->id)
            ->whereIn('course_id', collect($courses->items())->pluck('id'))
            ->where('status', 'completed')
            ->selectRaw('course_id, count(*) as c, max(updated_at) as last_at')
            ->groupBy('course_id')
            ->get()
            ->keyBy('course_id');

        return response()->json([
            'data' => collect($courses->items())->map(function (Course $course) use ($completed): array {
                $done = (int) ($completed[$course->id]->c ?? 0);
                $total = (int) $course->lessons_count;

                return [
                    ...$this->courseRow($course),
                    'lessons_count' => $total,
                    'completed_count' => min($done, $total),
                    'progress_percent' => $total > 0 ? (int) round(min($done, $total) / $total * 100) : 0,
                    'last_activity_at' => $completed[$course->id]->last_at ?? null,
                ];
            }),
            'meta' => ['current_page' => $courses->currentPage(), 'last_page' => $courses->lastPage(), 'total' => $courses->total()],
        ]);
    }

    /** The player tree: modules → lessons with my progress and locks. */
    public function show(Request $request, Course $course): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->visibleCourses($user->id)->whereKey($course->id)->exists(), 403);

        $course->load(['subject:id,name', 'modules.lessons.quiz:id,title,status,settings']);

        $progress = LessonProgress::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->get()
            ->keyBy('course_lesson_id');

        // Flatten module order for sequential locking + "continue" pointer.
        // NB: ->get(), never array access — a first visit has NO progress rows.
        $ordered = $course->modules->flatMap(fn ($m) => $m->lessons);
        $firstIncompleteId = $ordered->first(
            fn (CourseLesson $lesson): bool => $progress->get($lesson->id)?->status !== 'completed',
        )?->id;

        $locked = [];
        if ($course->is_sequential) {
            $blocked = false;
            foreach ($ordered as $lesson) {
                $locked[$lesson->id] = $blocked;
                if ($progress->get($lesson->id)?->status !== 'completed') {
                    $blocked = true;
                }
            }
        }

        $total = $ordered->count();
        $done = $ordered->filter(fn (CourseLesson $l): bool => $progress->get($l->id)?->status === 'completed')->count();

        return response()->json(['data' => [
            ...$this->courseRow($course),
            'is_sequential' => $course->is_sequential,
            'lessons_count' => $total,
            'completed_count' => $done,
            'progress_percent' => $total > 0 ? (int) round($done / $total * 100) : 0,
            'continue_lesson_id' => $firstIncompleteId,
            'modules' => $course->modules->map(fn ($module): array => [
                'id' => $module->id,
                'title' => $module->title,
                'description' => $module->description,
                'lessons' => $module->lessons->map(fn (CourseLesson $lesson): array => [
                    'id' => $lesson->id,
                    'type' => $lesson->type,
                    'title' => $lesson->title,
                    'duration_minutes' => $lesson->duration_minutes,
                    'status' => $progress->get($lesson->id)?->status,
                    'is_locked' => $locked[$lesson->id] ?? false,
                    'quiz_id' => $lesson->quiz_id,
                ]),
            ]),
        ]]);
    }

    /** One lesson's content — the player pane. Marks it started. */
    public function lesson(Request $request, CourseLesson $courseLesson): JsonResponse
    {
        $user = $request->user();
        $course = $courseLesson->course;
        abort_unless($this->visibleCourses($user->id)->whereKey($course->id)->exists(), 403);
        abort_unless($this->unlocked($user->id, $course, $courseLesson), 423, 'Finish the earlier lessons first.');

        LessonProgress::query()->firstOrCreate(
            ['user_id' => $user->id, 'course_lesson_id' => $courseLesson->id],
            ['course_id' => $course->id, 'status' => 'started'],
        );

        $content = $courseLesson->content ?? [];

        return response()->json(['data' => [
            'id' => $courseLesson->id,
            'course_id' => $course->id,
            'type' => $courseLesson->type,
            'title' => $courseLesson->title,
            'duration_minutes' => $courseLesson->duration_minutes,
            'quiz_id' => $courseLesson->quiz_id,
            'content' => match ($courseLesson->type) {
                'file' => [
                    'name' => $content['name'] ?? $courseLesson->title,
                    'size' => $content['size'] ?? null,
                    'mime_type' => $content['mime_type'] ?? null,
                    'url' => isset($content['path']) ? s3Url($content['path']) : null,
                ],
                'reading' => ['body' => $courseLesson->presentBody()],
                default => $content,
            },
        ]]);
    }

    /** Mark progress. Quiz lessons demand a submitted attempt — no shortcuts. */
    public function saveProgress(Request $request, CourseLesson $courseLesson): JsonResponse
    {
        $user = $request->user();
        $course = $courseLesson->course;
        abort_unless($this->visibleCourses($user->id)->whereKey($course->id)->exists(), 403);
        abort_unless($this->unlocked($user->id, $course, $courseLesson), 423, 'Finish the earlier lessons first.');

        $data = $request->validate(['status' => ['required', Rule::in(['started', 'completed'])]]);

        if ($data['status'] === 'completed' && $courseLesson->type === 'quiz' && $courseLesson->quiz_id !== null) {
            $sat = QuizAttempt::query()
                ->where('quiz_id', $courseLesson->quiz_id)
                ->where('user_id', $user->id)
                ->whereIn('status', [QuizAttemptStatus::Submitted->value, QuizAttemptStatus::Graded->value])
                ->exists();

            abort_unless($sat, 422, 'Take the quiz first — it completes this lesson.');
        }

        $progress = LessonProgress::query()->firstOrCreate(
            ['user_id' => $user->id, 'course_lesson_id' => $courseLesson->id],
            ['course_id' => $course->id, 'status' => 'started'],
        );

        // Completion never regresses to started.
        if ($data['status'] === 'completed' && $progress->status !== 'completed') {
            $progress->update(['status' => 'completed', 'completed_at' => now()]);
        }

        $total = $course->lessons()->count();
        $done = LessonProgress::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('status', 'completed')
            ->count();

        return response()->json(['data' => [
            'status' => $progress->refresh()->status,
            'completed_count' => $done,
            'lessons_count' => $total,
            'progress_percent' => $total > 0 ? (int) round($done / $total * 100) : 0,
        ]]);
    }

    // ── plumbing ─────────────────────────────────────────────────────────

    /**
     * Published courses this user may take: every platform course, plus —
     * through a linked student's ACTIVE enrollments — their school's
     * grade-window courses and their classes' courses.
     *
     * @return Builder<Course>
     */
    private function visibleCourses(int $userId): Builder
    {
        $student = Student::query()->where('user_id', $userId)->first();
        $enrollments = $student === null ? collect() : StudentEnrollment::query()
            ->where('student_id', $student->id)
            ->where('status', EnrollmentStatus::Active->value)
            ->with('section.gradeLevel')
            ->get();

        $anchorIds = $this->anchorIds($enrollments);

        return Course::query()
            ->where('status', 'published')
            ->where(function (Builder $q) use ($enrollments, $anchorIds): void {
                $q->whereNull('school_id');

                if ($anchorIds->isNotEmpty()) {
                    $q->orWhereIn('subject_assignment_id', $anchorIds);
                    $q->orWhereHas('targets', fn ($t) => $t->whereIn('subject_assignment_id', $anchorIds));
                }

                foreach ($enrollments as $enrollment) {
                    $gradeSort = $enrollment->section?->gradeLevel?->sort_order;

                    $q->orWhere(function (Builder $w) use ($enrollment, $gradeSort): void {
                        $w->where('school_id', $enrollment->school_id)
                            ->whereNull('subject_assignment_id')
                            ->where(fn ($b) => $b->whereNull('branch_id')->orWhere('branch_id', $enrollment->branch_id))
                            ->when($gradeSort !== null, fn ($g) => $g
                                ->where(fn ($x) => $x->whereNull('min_grade_sort')->orWhere('min_grade_sort', '<=', $gradeSort))
                                ->where(fn ($x) => $x->whereNull('max_grade_sort')->orWhere('max_grade_sort', '>=', $gradeSort)));
                    });
                }
            });
    }

    /** @return Collection<int, int> */
    private function anchorIds(Collection $enrollments): Collection
    {
        $sectionIds = $enrollments->pluck('section_id')->filter();

        if ($sectionIds->isEmpty()) {
            return collect();
        }

        return SubjectAssignment::query()->whereIn('section_id', $sectionIds)->pluck('id');
    }

    /** Sequential courses gate each lesson on everything before it. */
    private function unlocked(int $userId, Course $course, CourseLesson $lesson): bool
    {
        if (! $course->is_sequential) {
            return true;
        }

        $course->loadMissing('modules.lessons');
        $ordered = $course->modules->flatMap(fn ($m) => $m->lessons);

        $earlierIds = $ordered->takeUntil(fn (CourseLesson $l): bool => $l->id === $lesson->id)->pluck('id');

        if ($earlierIds->isEmpty()) {
            return true;
        }

        $completed = LessonProgress::query()
            ->where('user_id', $userId)
            ->whereIn('course_lesson_id', $earlierIds)
            ->where('status', 'completed')
            ->count();

        return $completed >= $earlierIds->count();
    }

    private function courseRow(Course $course): array
    {
        return [
            'id' => $course->id,
            'title' => $course->title,
            'description' => $course->presentDescription(),
            'is_platform' => $course->isPlatform(),
            'subject_name' => $course->subject?->name,
            'stream' => $course->stream,
            'language' => $course->language,
            'cover_url' => $course->cover_path !== null ? s3Url($course->cover_path) : null,
        ];
    }
}
