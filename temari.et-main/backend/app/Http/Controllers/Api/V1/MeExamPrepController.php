<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\QuizAttemptStatus;
use App\Enums\QuizStatus;
use App\Http\Controllers\Controller;
use App\Models\CourseMaterial;
use App\Models\GradeLevel;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Subject;
use App\Support\SearchTerm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * THE OPEN EXAM-PREP LANE (ADR-016): Temari.et's national past papers and
 * mock exams, browsable by ANY authenticated user — school students and
 * no-school B2C learners alike. No memberships, no context headers; taking
 * an exam runs through the same /me attempt engine as class exams.
 */
class MeExamPrepController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $quizzes = Quiz::query()
            ->where('is_platform', true)
            ->where('status', QuizStatus::Published->value)
            ->with(['subject:id,name', 'gradeLevel:id,name'])
            ->when($request->filled('grade_level_id'), fn ($q) => $q->where('grade_level_id', $request->integer('grade_level_id')))
            ->when($request->filled('subject_id'), fn ($q) => $q->where('subject_id', $request->integer('subject_id')))
            ->when($request->filled('kind'), fn ($q) => $q->where('kind', $request->string('kind')))
            ->when($request->filled('exam_kind'), fn ($q) => $q->where('exam_kind', $request->string('exam_kind')))
            ->when($request->filled('exam_year_ec'), fn ($q) => $q->where('exam_year_ec', $request->integer('exam_year_ec')))
            ->when($request->filled('stream'), fn ($q) => $q->where(
                fn ($w) => $w->whereNull('stream')->orWhere('stream', $request->string('stream')),
            ))
            ->when($request->filled('language'), fn ($q) => $q->where('language', $request->string('language')))
            ->tap(fn ($query) => SearchTerm::apply($query, $request->string('q')->trim()->value(), fn ($w, string $n) => $w
                ->where('title', 'ilike', SearchTerm::contains($n))))
            ->orderByDesc('exam_year_ec')
            ->orderByDesc('id')
            ->paginate(min($request->integer('per_page', 25), 100));

        $ids = collect($quizzes->items())->pluck('id');

        $counts = QuizAttempt::query()
            ->where('user_id', $userId)
            ->whereIn('quiz_id', $ids)
            ->where('status', '!=', QuizAttemptStatus::Invalidated->value)
            ->selectRaw('quiz_id, count(*) as c, max(score) as best')
            ->groupBy('quiz_id')
            ->get()
            ->keyBy('quiz_id');

        $live = QuizAttempt::query()
            ->where('user_id', $userId)
            ->whereIn('quiz_id', $ids)
            ->where('status', QuizAttemptStatus::InProgress->value)
            ->pluck('id', 'quiz_id');

        return response()->json([
            'data' => collect($quizzes->items())->map(function (Quiz $quiz) use ($counts, $live): array {
                $used = (int) ($counts[$quiz->id]->c ?? 0);
                $allowed = (int) $quiz->setting('attempts_allowed', 0);

                // Practice papers reveal answers immediately; mocks simulate
                // the real sitting (timer, results at the end).
                $mode = $quiz->exam_kind === 'practice'
                    || ((string) $quiz->setting('results_policy', 'immediately') === 'immediately'
                        && (bool) $quiz->setting('reveal_answers', false))
                    ? 'practice'
                    : 'mock';

                return [
                    'id' => $quiz->id,
                    'kind' => $quiz->kind,
                    'exam_kind' => $quiz->exam_kind,
                    'exam_year_ec' => $quiz->exam_year_ec,
                    'stream' => $quiz->stream,
                    'mode' => $mode,
                    'title' => $quiz->title,
                    'subject_id' => $quiz->subject_id,
                    'subject_name' => $quiz->subject?->name,
                    'grade_level_id' => $quiz->grade_level_id,
                    'grade_level_name' => $quiz->gradeLevel?->name,
                    'language' => $quiz->language,
                    'duration_minutes' => $quiz->setting('duration_minutes'),
                    'attempts_allowed' => $allowed,
                    'attempts_used' => $used,
                    'best_score' => isset($counts[$quiz->id]) && $counts[$quiz->id]->best !== null
                        ? (float) $counts[$quiz->id]->best
                        : null,
                    'window_open' => $quiz->windowOpen(),
                    'can_start' => $quiz->windowOpen() && ($allowed === 0 || $used < $allowed || isset($live[$quiz->id])),
                    'live_attempt_id' => $live[$quiz->id] ?? null,
                    'question_count' => is_array($quiz->draw) && $quiz->draw !== []
                        ? collect($quiz->draw)->sum('count')
                        : $quiz->quizQuestions()->count(),
                ];
            }),
            'meta' => ['current_page' => $quizzes->currentPage(), 'last_page' => $quizzes->lastPage(), 'total' => $quizzes->total()],
        ]);
    }

    /** The browse facets: which grades/subjects actually have content. */
    public function facets(): JsonResponse
    {
        $published = Quiz::query()
            ->where('is_platform', true)
            ->where('status', QuizStatus::Published->value);

        $gradeIds = (clone $published)->whereNotNull('grade_level_id')->distinct()->pluck('grade_level_id');
        $subjectIds = (clone $published)->whereNotNull('subject_id')->distinct()->pluck('subject_id');
        $years = (clone $published)->whereNotNull('exam_year_ec')->distinct()->orderByDesc('exam_year_ec')->pluck('exam_year_ec');
        $examKinds = (clone $published)->whereNotNull('exam_kind')->distinct()->pluck('exam_kind');

        return response()->json(['data' => [
            'grade_levels' => GradeLevel::query()->whereIn('id', $gradeIds)->orderBy('sort_order')->get(['id', 'name']),
            'subjects' => Subject::query()->whereIn('id', $subjectIds)->orderBy('name')->get(['id', 'name']),
            'years_ec' => $years,
            'exam_kinds' => $examKinds,
        ]]);
    }

    /** The platform study library (past papers, notes, videos). */
    public function materials(Request $request): JsonResponse
    {
        $materials = CourseMaterial::query()
            ->whereNull('school_id')
            ->where('is_active', true)
            ->with('subject:id,name')
            ->when($request->filled('subject_id'), fn ($q) => $q->where('subject_id', $request->integer('subject_id')))
            ->when($request->filled('grade_sort'), fn ($q) => $q
                ->where(fn ($w) => $w->whereNull('min_grade_sort')->orWhere('min_grade_sort', '<=', $request->integer('grade_sort')))
                ->where(fn ($w) => $w->whereNull('max_grade_sort')->orWhere('max_grade_sort', '>=', $request->integer('grade_sort'))))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->tap(fn ($query) => SearchTerm::apply($query, $request->string('q')->trim()->value(), fn ($w, string $n) => $w
                ->where('title', 'ilike', SearchTerm::contains($n))))
            ->orderByDesc('is_pinned')
            ->orderByDesc('id')
            ->paginate(min($request->integer('per_page', 25), 100));

        return response()->json([
            'data' => collect($materials->items())->map(function (CourseMaterial $material): array {
                $content = $material->content ?? [];

                return [
                    'id' => $material->id,
                    'title' => $material->title,
                    'description' => $material->description,
                    'type' => $material->type,
                    'subject_name' => $material->subject?->name,
                    'is_pinned' => $material->is_pinned,
                    'posted_at' => $material->created_at,
                    'content' => $material->type === 'file'
                        ? [
                            'name' => $content['name'] ?? $material->title,
                            'size' => $content['size'] ?? null,
                            'mime_type' => $content['mime_type'] ?? null,
                            'url' => isset($content['path']) ? s3Url($content['path']) : null,
                        ]
                        : $content,
                ];
            }),
            'meta' => ['current_page' => $materials->currentPage(), 'last_page' => $materials->lastPage(), 'total' => $materials->total()],
        ]);
    }
}
