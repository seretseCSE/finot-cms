<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\QuestionType;
use App\Enums\QuizAttemptStatus;
use App\Enums\QuizStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\QuestionResource;
use App\Http\Resources\QuizAttemptResource;
use App\Http\Resources\QuizResource;
use App\Models\Assessment;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\SubjectAssignment;
use App\Rules\NotPastDay;
use App\Services\Lms\GradebookSync;
use App\Services\Lms\QuizAttemptService;
use App\Services\Lms\QuizPublisher;
use App\Support\QuestionRules;
use App\Support\SearchTerm;
use App\Support\TermGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Staff lane for quizzes/exams (ADR-016). Class quizzes anchor to a
 * subject_assignment (teacher-owned or supervisory); platform mocks are
 * Temari.et staff territory (`?platform=1`, exam_prep.manage). Structure
 * freezes once someone has sat the exam; scores flow to the gradebook
 * through the linked assessment slot.
 */
class QuizController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Quiz::query()
            ->with([
                'subjectAssignment.section:id,name,grade_level_id', 'subjectAssignment.section.gradeLevel:id,name', 'subjectAssignment.subject:id,name',
                'targetAssignments.section:id,name,grade_level_id',
                'subject:id,name', 'gradeLevel:id,name', 'assessment:id,name',
            ])
            ->withCount(['quizQuestions', 'attempts'])
            ->withTakerStats()
            ->orderByDesc('id');

        if ($request->boolean('platform')) {
            abort_unless($user->hasPlatformPermission('exam_prep.manage'), 403);
            $query->where('is_platform', true)
                ->when($request->filled('grade_level_id'), fn ($q) => $q->where('grade_level_id', $request->integer('grade_level_id')))
                ->when($request->filled('subject_id'), fn ($q) => $q->where('subject_id', $request->integer('subject_id')))
                ->when($request->filled('exam_kind'), fn ($q) => $q->where('exam_kind', $request->string('exam_kind')))
                ->when($request->filled('exam_year_ec'), fn ($q) => $q->where('exam_year_ec', $request->integer('exam_year_ec')))
                ->when($request->filled('stream'), fn ($q) => $q->where('stream', $request->string('stream')));
        } elseif ($request->filled('subject_assignment_id')) {
            $assignment = SubjectAssignment::findOrFail($request->integer('subject_assignment_id'));
            abort_unless($this->mayViewAssignmentLms($request, $assignment), 403);
            $query->whereHas('targets', fn ($t) => $t->where('subject_assignment_id', $assignment->id));
        } else {
            $branch = $this->activeBranchOrNull($request);
            $schoolId = $branch?->school_id ?? $this->activeSchoolScopeId($request);
            abort_if($schoolId === null, 422, 'Select a school context to view exams.');

            if ($user->hasPermissionForScope('lms.view', $schoolId, $branch?->id)) {
                $query->where('school_id', $schoolId)
                    ->when($branch !== null, fn ($q) => $q->where('branch_id', $branch->id))
                    ->when($this->branchFilterId($request, $branch), fn ($q, $id) => $q->where('branch_id', $id));
            } elseif ($user->hasPermissionForScope('lms.manage_own', $schoolId, $branch?->id)) {
                // Teachers: exams touching any of their own classes.
                $own = $this->ownAssignmentIds($request);
                $query->whereHas('targets', fn ($t) => $t->whereIn('subject_assignment_id', $own));
            } else {
                abort(403);
            }

            // School-lane narrowing: grade / subject / section via the targets.
            $query
                ->when($request->filled('grade_level_id'), fn ($q) => $q->whereHas(
                    'targetAssignments.section', fn ($s) => $s->where('grade_level_id', $request->integer('grade_level_id')),
                ))
                ->when($request->filled('subject_id'), fn ($q) => $q->whereHas(
                    'targetAssignments', fn ($t) => $t->where('subject_id', $request->integer('subject_id')),
                ))
                ->when($request->filled('section_id'), fn ($q) => $q->whereHas(
                    'targetAssignments', fn ($t) => $t->where('section_id', $request->integer('section_id')),
                ));
        }

        $quizzes = $query
            ->when($request->filled('kind'), fn ($q) => $q->where('kind', $request->string('kind')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->tap(fn ($query) => SearchTerm::apply($query, $request->string('q')->trim()->value(), fn ($w, string $n) => $w
                ->where('title', 'ilike', SearchTerm::contains($n))))
            ->paginate(min($request->integer('per_page', 25), 100));

        return QuizResource::collection($quizzes)->response();
    }

    public function store(Request $request, QuizAttemptService $attempts): JsonResponse
    {
        $data = $this->validatePayload($request);
        $user = $request->user();

        $targets = collect();

        if ($request->boolean('platform')) {
            abort_unless($user->hasPlatformPermission('exam_prep.manage'), 403);
            $anchor = ['is_platform' => true, 'school_id' => null, 'branch_id' => null, 'subject_assignment_id' => null];
        } else {
            $targets = $this->resolveTargets($request, $data);
            $assignment = $targets->first();
            TermGate::assertWritable($assignment->term);
            $anchor = [
                'is_platform' => false,
                'school_id' => $assignment->school_id,
                'branch_id' => $assignment->branch_id,
                'subject_assignment_id' => $assignment->id,
            ];
            $this->assertAssessmentBelongs($data['assessment_id'] ?? null, $assignment);
        }

        $this->assertQuestionScope($data, $anchor['school_id']);

        $quiz = Quiz::create([
            ...$anchor,
            'kind' => $data['kind'],
            'title' => $data['title'],
            'instructions' => $this->cleanInstructions($data['instructions'] ?? null),
            'subject_id' => $data['subject_id'] ?? null,
            'grade_level_id' => $data['grade_level_id'] ?? null,
            // Prep identity is a platform-lane concept only.
            'exam_kind' => $anchor['is_platform'] ? ($data['exam_kind'] ?? null) : null,
            'exam_year_ec' => $anchor['is_platform'] ? ($data['exam_year_ec'] ?? null) : null,
            'stream' => $anchor['is_platform'] ? ($data['stream'] ?? null) : null,
            'language' => $data['language'] ?? 'en',
            'settings' => $data['settings'],
            'draw' => $data['draw'] ?? null,
            'parts' => $this->cleanParts($data['parts'] ?? null),
            'assessment_id' => $data['assessment_id'] ?? null,
            'created_by' => $user->id,
        ]);

        if (isset($data['access_code'])) {
            $quiz->forceFill(['access_code_hash' => Hash::make($data['access_code'])])->save();
        }

        $quiz->targets()->createMany(
            $targets->map(fn (SubjectAssignment $a): array => ['subject_assignment_id' => $a->id]),
        );

        $this->syncQuestions($quiz, $data['questions'] ?? null);

        return (new QuizResource($quiz->loadCount(['quizQuestions', 'attempts'])->load('targetAssignments.section:id,name,grade_level_id')))
            ->additional(['message' => 'Exam created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Quiz $quiz): JsonResponse
    {
        $this->authorize('view', $quiz);

        // Re-fetch through the stats scope so detail carries the same
        // expected/taken pair as the list.
        $quiz = Quiz::query()->withTakerStats()->findOrFail($quiz->id);

        $quiz->load([
            'subjectAssignment.section:id,name,grade_level_id', 'subjectAssignment.section.gradeLevel:id,name',
            'subjectAssignment.subject:id,name',
            'targetAssignments.section:id,name,grade_level_id',
            'subject:id,name', 'gradeLevel:id,name', 'assessment:id,name,max_score',
            'quizQuestions.question.creator:id,name',
        ])->loadCount('attempts');

        $payload = (new QuizResource($quiz))->resolve();
        $payload['questions'] = $quiz->quizQuestions->map(function ($qq): array {
            $question = (new QuestionResource($qq->question))->resolve();
            $question['quiz_points'] = $qq->effectivePoints();
            $question['sort_order'] = $qq->sort_order;
            $question['part_index'] = $qq->part_index;

            return $question;
        });
        $payload['groups'] = $this->groupStemsFor($quiz->quizQuestions->pluck('question'));

        return response()->json(['data' => $payload]);
    }

    /**
     * A dry-run paper for staff: the exam exactly as one student would draw
     * it. Fixed papers return their picks; random-draw exams execute a fresh
     * SAMPLE draw (nothing is persisted) so the paper can be checked even
     * after publishing. Includes answer keys only for staff who may manage.
     */
    public function preview(Request $request, Quiz $quiz, QuizAttemptService $attempts): JsonResponse
    {
        $this->authorize('view', $quiz);

        $entries = $attempts->samplePaper($quiz);

        $questions = Question::query()
            ->whereIn('id', $entries->pluck('id'))
            ->with('creator:id,name')
            ->get()
            ->keyBy('id');

        $rows = $entries->map(function (array $entry) use ($questions): ?array {
            /** @var ?Question $question */
            $question = $questions->get($entry['id']);
            if ($question === null) {
                return null;
            }

            $row = (new QuestionResource($question))->resolve();
            unset($row['answer_key']);
            $row['quiz_points'] = (float) $entry['points'];
            $row['part_index'] = $entry['part'];

            return $row;
        })->filter()->values();

        return response()->json(['data' => [
            'is_sample' => is_array($quiz->draw) && $quiz->draw !== [],
            'questions' => $rows,
            'groups' => $this->groupStemsFor($questions->values()),
            'parts' => $quiz->presentParts(),
        ]]);
    }

    /**
     * Hydrated passages for every question group the given questions belong
     * to, keyed by group id.
     *
     * @param  Collection<int, ?Question>  $questions
     */
    private function groupStemsFor(Collection $questions): array
    {
        $ids = $questions->filter()->pluck('parent_id')->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return Question::query()
            ->whereIn('id', $ids)
            ->get()
            ->mapWithKeys(function (Question $group): array {
                $body = $group->presentBody();

                return [$group->id => [
                    'id' => $group->id,
                    'stem' => (string) ($body['stem'] ?? ''),
                    ...(isset($body['attachments']) ? ['attachments' => $body['attachments']] : []),
                ]];
            })
            ->all();
    }

    public function update(Request $request, Quiz $quiz): JsonResponse
    {
        $this->authorize('update', $quiz);

        if ($quiz->subjectAssignment !== null) {
            TermGate::assertWritable($quiz->subjectAssignment->term);
        }

        $data = $this->validatePayload($request, $quiz);

        $hasAttempts = $quiz->attempts()->exists();
        $structural = isset($data['questions']) || array_key_exists('draw', $data);

        if ($hasAttempts && $structural) {
            throw ValidationException::withMessages([
                'questions' => ['Someone has already taken this exam — its questions can no longer change.'],
            ]);
        }

        if (! $quiz->is_platform && isset($data['subject_assignment_ids'])) {
            $this->syncTargets($request, $quiz, $data);
        }

        if (! $quiz->is_platform) {
            $this->assertAssessmentBelongs($data['assessment_id'] ?? null, $quiz->subjectAssignment);
        }

        $this->assertQuestionScope($data, $quiz->school_id);

        if (array_key_exists('instructions', $data)) {
            $data['instructions'] = $this->cleanInstructions($data['instructions']);
        }

        if (array_key_exists('parts', $data)) {
            $data['parts'] = $this->cleanParts($data['parts']);

            // Once someone sat the paper the STRUCTURE is frozen: retitling a
            // part is fine, but adding/removing parts would orphan the frozen
            // part indexes on quiz_questions and on every attempt's paper.
            if ($hasAttempts && count($data['parts'] ?? []) !== count($quiz->parts ?? [])) {
                throw ValidationException::withMessages([
                    'parts' => ['Someone has already taken this exam — its parts can no longer be added or removed.'],
                ]);
            }
        }

        $quiz->update(collect($data)->only([
            'kind', 'title', 'instructions', 'subject_id', 'grade_level_id',
            ...($quiz->is_platform ? ['exam_kind', 'exam_year_ec', 'stream'] : []),
            'language', 'settings', 'draw', 'parts', 'assessment_id',
        ])->all());

        if (array_key_exists('access_code', $data)) {
            $quiz->forceFill([
                'access_code_hash' => $data['access_code'] !== null ? Hash::make($data['access_code']) : null,
            ])->save();
        }

        if (isset($data['questions'])) {
            $this->syncQuestions($quiz, $data['questions']);
        }

        return (new QuizResource($quiz->refresh()->loadCount(['quizQuestions', 'attempts'])->load('targetAssignments.section:id,name,grade_level_id')))
            ->response();
    }

    public function destroy(Quiz $quiz): JsonResponse
    {
        $this->authorize('delete', $quiz);

        if ($quiz->attempts()->exists()) {
            $quiz->update(['status' => QuizStatus::Archived->value]);

            return response()->json(['message' => 'This exam has attempts — it was archived instead of deleted.']);
        }

        $quiz->quizQuestions()->delete();
        $quiz->delete();

        return response()->json(['message' => 'Exam deleted.']);
    }

    /** Publish: freeze the paper's worth and open the availability window. */
    public function publish(Quiz $quiz, QuizPublisher $publisher): JsonResponse
    {
        $this->authorize('manage', $quiz);

        if ($quiz->subjectAssignment !== null) {
            TermGate::assertWritable($quiz->subjectAssignment->term);
        }

        $publisher->publish($quiz);

        return (new QuizResource($quiz->loadCount(['quizQuestions', 'attempts'])))
            ->additional(['message' => 'Exam published.'])
            ->response();
    }

    /** Close early: no new attempts; live attempts run out their clocks. */
    public function close(Quiz $quiz, QuizPublisher $publisher): JsonResponse
    {
        $this->authorize('manage', $quiz);

        $publisher->close($quiz);

        return (new QuizResource($quiz->loadCount(['quizQuestions', 'attempts'])))
            ->additional(['message' => 'Exam closed.'])
            ->response();
    }

    /** Live monitor + results register. */
    public function attempts(Request $request, Quiz $quiz): JsonResponse
    {
        $this->authorize('view', $quiz);

        $attempts = $quiz->attempts()
            ->with(['student:id,first_name,father_name,grandfather_name,public_id,photo_path', 'user:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('started_at')
            ->paginate(min($request->integer('per_page', 50), 100));

        return QuizAttemptResource::collection($attempts)->response();
    }

    /** One attempt with per-question answers + keys — the grading screen. */
    public function showAttempt(Quiz $quiz, QuizAttempt $attempt, QuizAttemptService $service): JsonResponse
    {
        $this->authorize('view', $quiz);
        abort_unless($attempt->quiz_id === $quiz->id, 404);

        $attempt->load(['student:id,first_name,father_name,grandfather_name,public_id', 'user:id,name']);
        $answers = $attempt->answers()->get()->keyBy('question_id');
        $questions = Question::query()
            ->whereIn('id', collect($attempt->question_ids)->pluck('id'))
            ->get()
            ->keyBy('id');

        $rows = collect($attempt->question_ids)->map(function (array $entry, int $index) use ($questions, $answers): ?array {
            /** @var ?Question $question */
            $question = $questions->get($entry['id']);
            if ($question === null) {
                return null;
            }
            $answer = $answers->get($question->id);

            return [
                'question_id' => $question->id,
                'number' => $index + 1,
                'part' => $entry['part'] ?? null,
                'group_id' => isset($entry['group']) ? (int) $entry['group'] : ($question->parent_id !== null ? (int) $question->parent_id : null),
                'type' => $question->type->value,
                'points' => (float) $entry['points'],
                'body' => $question->presentBody(),
                'answer_key' => $question->answer_key,
                'explanation' => $question->explanation,
                'answer' => $answer?->answer,
                'auto_score' => $answer?->auto_score !== null ? (float) $answer->auto_score : null,
                'manual_score' => $answer?->manual_score !== null ? (float) $answer->manual_score : null,
                'feedback' => $answer?->feedback,
                'needs_manual' => $answer !== null && $answer->auto_score === null && $answer->manual_score === null,
            ];
        })->filter()->values();

        return response()->json(['data' => [
            'attempt' => (new QuizAttemptResource($attempt))->resolve(),
            'integrity_log' => $attempt->integrity_log ?? [],
            'parts' => $quiz->presentParts(),
            'questions' => $rows,
            'groups' => $service->groupStems($rows->all()),
        ]]);
    }

    /** Manual grading: per-question scores + feedback, then recompute. */
    public function gradeAttempt(Request $request, Quiz $quiz, QuizAttempt $attempt, QuizAttemptService $service): JsonResponse
    {
        $this->authorize('manage', $quiz);
        abort_unless($attempt->quiz_id === $quiz->id, 404);
        abort_if($attempt->status === QuizAttemptStatus::InProgress, 422, 'This attempt is still in progress.');

        $data = $request->validate([
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.manual_score' => ['nullable', 'numeric', 'min:0'],
            'answers.*.feedback' => ['nullable', 'string', 'max:2000'],
        ]);

        $paper = collect($attempt->question_ids)->keyBy('id');

        foreach ($data['answers'] as $row) {
            $entry = $paper->get((int) $row['question_id']);
            if ($entry === null) {
                continue;
            }

            if (isset($row['manual_score']) && (float) $row['manual_score'] > (float) $entry['points']) {
                throw ValidationException::withMessages([
                    'answers' => ["A score exceeds the question's maximum of {$entry['points']}."],
                ]);
            }

            $attempt->answers()->updateOrCreate(
                ['question_id' => (int) $row['question_id']],
                [
                    'manual_score' => $row['manual_score'] ?? null,
                    'feedback' => $row['feedback'] ?? null,
                    'graded_by' => $request->user()->id,
                ],
            );
        }

        $service->refreshScore($attempt);

        return (new QuizAttemptResource($attempt->refresh()))
            ->additional(['message' => 'Grades saved.'])
            ->response();
    }

    /** Invalidate a flagged attempt (frees the seat for a fresh sitting). */
    public function invalidateAttempt(Request $request, Quiz $quiz, QuizAttempt $attempt): JsonResponse
    {
        $this->authorize('manage', $quiz);
        abort_unless($attempt->quiz_id === $quiz->id, 404);

        $attempt->forceFill(['status' => QuizAttemptStatus::Invalidated->value])->save();

        return response()->json(['message' => 'Attempt invalidated.']);
    }

    /** Re-push graded attempts into the gradebook (after a marklist reopen). */
    public function sync(Quiz $quiz, GradebookSync $gradebook): JsonResponse
    {
        $this->authorize('manage', $quiz);

        $count = $gradebook->syncQuiz($quiz);

        return response()->json(['message' => "Gradebook updated for {$count} students.", 'meta' => ['count' => $count]]);
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function validatePayload(Request $request, ?Quiz $quiz = null): array
    {
        $creating = $quiz === null;

        return $request->validate([
            // The sections sitting this paper (same subject + semester). The
            // single-id field remains accepted as a one-section shorthand.
            'subject_assignment_ids' => [
                Rule::requiredIf($creating && ! $request->boolean('platform') && ! $request->filled('subject_assignment_id')),
                'nullable', 'array', 'min:1', 'max:30',
            ],
            'subject_assignment_ids.*' => ['integer', 'exists:subject_assignments,id'],
            'subject_assignment_id' => ['nullable', 'integer', 'exists:subject_assignments,id'],
            'kind' => [$creating ? 'required' : 'sometimes', Rule::in(Quiz::KINDS)],
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:10000'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'grade_level_id' => ['nullable', 'integer', 'exists:grade_levels,id'],
            'exam_kind' => ['nullable', Rule::in(Quiz::EXAM_KINDS)],
            'exam_year_ec' => ['nullable', 'integer', 'min:1980', 'max:2100'],
            'stream' => ['nullable', Rule::in(Quiz::STREAMS)],
            'language' => ['sometimes', 'string', 'in:en,am,om'],
            'settings' => [$creating ? 'required' : 'sometimes', 'array'],
            'settings.duration_minutes' => ['nullable', 'integer', 'min:0', 'max:600'],
            // An exam can't open or close on a day already gone — but an edit
            // that keeps an existing past window untouched still validates.
            'settings.opens_at' => ['nullable', 'date', 'after:2000-01-01', 'before:2100-01-01', new NotPastDay($quiz->settings['opens_at'] ?? null)],
            'settings.closes_at' => ['nullable', 'date', 'after:settings.opens_at', new NotPastDay($quiz->settings['closes_at'] ?? null)],
            'settings.attempts_allowed' => ['nullable', 'integer', 'min:0', 'max:10'],
            'settings.shuffle_questions' => ['nullable', 'boolean'],
            'settings.shuffle_options' => ['nullable', 'boolean'],
            'settings.navigation' => ['nullable', Rule::in(['free', 'sequential'])],
            'settings.results_policy' => ['nullable', Rule::in(['immediately', 'after_close', 'manual'])],
            'settings.results_released' => ['nullable', 'boolean'],
            'settings.reveal_answers' => ['nullable', 'boolean'],
            'settings.grade_attempt' => ['nullable', Rule::in(['best', 'last', 'first'])],
            'access_code' => ['sometimes', 'nullable', 'string', 'min:4', 'max:12'],
            'assessment_id' => ['nullable', 'integer', 'exists:assessments,id'],
            'questions' => ['sometimes', 'array', 'max:200'],
            'questions.*.question_id' => ['required', 'integer', 'exists:questions,id'],
            'questions.*.points' => ['nullable', 'numeric', 'min:0.25', 'max:100'],
            'questions.*.part' => ['nullable', 'integer', 'min:0', 'max:19'],
            // Paper parts ("Part I — Multiple Choice…"), referenced by index.
            'parts' => ['sometimes', 'nullable', 'array', 'max:20'],
            'parts.*.title' => ['required', 'string', 'max:200'],
            'parts.*.instructions' => ['nullable', 'string', 'max:10000'],
            'draw' => ['sometimes', 'nullable', 'array', 'max:10'],
            'draw.*.question_bank_id' => ['required', 'integer', 'exists:question_banks,id'],
            'draw.*.count' => ['required', 'integer', 'min:1', 'max:100'],
            'draw.*.difficulty' => ['nullable', Rule::in(['easy', 'medium', 'hard'])],
            'draw.*.tags' => ['nullable', 'array', 'max:5'],
            'draw.*.tags.*' => ['string', 'max:60'],
        ]);
    }

    /**
     * The sections sitting this paper, in the order picked (first = anchor).
     * All must share subject + semester + branch, and the user must manage
     * every one of them.
     *
     * @return Collection<int, SubjectAssignment>
     */
    private function resolveTargets(Request $request, array $data): Collection
    {
        $ids = collect($data['subject_assignment_ids'] ?? [])
            ->whenEmpty(fn () => collect([$data['subject_assignment_id'] ?? null]))
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $found = SubjectAssignment::with('term')->findMany($ids);
        $assignments = $ids->map(fn (int $id) => $found->firstWhere('id', $id))->filter()->values();

        abort_if($assignments->isEmpty(), 422, 'Pick at least one section.');

        foreach ($assignments as $assignment) {
            abort_unless($this->mayManageAssignmentLms($request, $assignment), 403);
        }

        if ($assignments->pluck('subject_id')->unique()->count() > 1
            || $assignments->pluck('term_id')->unique()->count() > 1
            || $assignments->pluck('branch_id')->unique()->count() > 1) {
            throw ValidationException::withMessages([
                'subject_assignment_ids' => ['All sections must share the same subject, semester and branch.'],
            ]);
        }

        return $assignments;
    }

    /**
     * Re-point the exam's sections. Sections whose students already sat the
     * paper can never be removed; the first target becomes the new anchor.
     */
    private function syncTargets(Request $request, Quiz $quiz, array $data): void
    {
        $targets = $this->resolveTargets($request, $data);
        $keepIds = $targets->pluck('id');

        $removed = $quiz->targets()
            ->whereNotIn('subject_assignment_id', $keepIds)
            ->with('subjectAssignment:id,section_id')
            ->get();

        if ($removed->isNotEmpty()) {
            $sectionIds = $removed->pluck('subjectAssignment.section_id')->filter();
            $hasAttempts = $quiz->attempts()
                ->whereHas('enrollment', fn ($q) => $q->whereIn('section_id', $sectionIds))
                ->exists();

            if ($hasAttempts) {
                throw ValidationException::withMessages([
                    'subject_assignment_ids' => ['A section with recorded attempts cannot be removed from this exam.'],
                ]);
            }

            $quiz->targets()->whereIn('id', $removed->pluck('id'))->delete();
        }

        foreach ($keepIds as $id) {
            $quiz->targets()->firstOrCreate(['subject_assignment_id' => $id]);
        }

        $anchor = $targets->first();
        $quiz->forceFill([
            'subject_assignment_id' => $anchor->id,
            'school_id' => $anchor->school_id,
            'branch_id' => $anchor->branch_id,
        ])->save();
        $quiz->setRelation('subjectAssignment', $anchor);
    }

    /**
     * Paper parts, sanitized and reindexed: titles trimmed, per-part
     * instructions through the same WYSIWYG pipeline as the exam's own.
     *
     * @return list<array{title: string, instructions: ?string}>|null
     */
    private function cleanParts(?array $parts): ?array
    {
        if ($parts === null || $parts === []) {
            return null;
        }

        return array_values(array_map(fn (array $part): array => [
            'title' => trim((string) ($part['title'] ?? '')),
            'instructions' => $this->cleanInstructions($part['instructions'] ?? null),
        ], $parts));
    }

    /** WYSIWYG instructions: sanitized, uploaded images stored as data-path. */
    private function cleanInstructions(?string $html): ?string
    {
        if ($html === null || trim(strip_tags($html)) === '' && ! str_contains($html, '<img')) {
            return null;
        }

        return QuestionRules::normalizeStemMedia(QuestionRules::sanitizeStem($html));
    }

    /**
     * Media for exam instructions (images referenced from the WYSIWYG).
     * Open to anyone who can author LMS content in their active context.
     */
    public function upload(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->hasPlatformPermission('exam_prep.manage')
            || $user->hasContextPermission('lms.manage')
            || $user->hasContextPermission('lms.manage_own'),
            403,
        );

        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,gif'],
        ]);

        $file = $request->file('file');
        $path = $file->store('lms/exam-media', ['disk' => config('filesystems.default')]);

        return response()->json(['data' => [
            'name' => $file->getClientOriginalName(),
            'path' => $path,
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'url' => s3Url($path),
        ]], 201);
    }

    /** Every referenced question/bank must live in the quiz's own scope. */
    private function assertQuestionScope(array $data, ?int $schoolId): void
    {
        $bankIds = collect($data['draw'] ?? [])->pluck('question_bank_id');

        if (isset($data['questions'])) {
            $bankIds = $bankIds->merge(
                Question::query()->whereIn('id', collect($data['questions'])->pluck('question_id'))
                    ->pluck('question_bank_id'),
            );
        }

        if ($bankIds->isEmpty()) {
            return;
        }

        $foreign = QuestionBank::query()
            ->whereIn('id', $bankIds->unique())
            ->when(
                $schoolId === null,
                fn ($q) => $q->whereNotNull('school_id'),
                fn ($q) => $q->where(fn ($w) => $w->whereNull('school_id')->orWhere('school_id', '!=', $schoolId)),
            );

        // School quizzes may also draw from their own school's banks only —
        // platform banks stay platform (mock content is released via the
        // exam-prep lane, not leaked through class quizzes).
        if ($foreign->exists()) {
            throw ValidationException::withMessages([
                'questions' => ['All questions must come from your own question banks.'],
            ]);
        }
    }

    private function assertAssessmentBelongs(?int $assessmentId, ?SubjectAssignment $assignment): void
    {
        if ($assessmentId === null || $assignment === null) {
            return;
        }

        $ok = Assessment::query()
            ->whereKey($assessmentId)
            ->where('subject_assignment_id', $assignment->id)
            ->exists();

        if (! $ok) {
            throw ValidationException::withMessages([
                'assessment_id' => ['The gradebook slot must belong to this class.'],
            ]);
        }
    }

    private function syncQuestions(Quiz $quiz, ?array $questions): void
    {
        if ($questions === null) {
            return;
        }

        $quiz->quizQuestions()->delete();

        // A part reference must point into the quiz's own parts array —
        // anything past the end (or with no parts at all) files as ungrouped.
        $partCount = count($quiz->parts ?? []);

        // A picked GROUP id expands to its published sub-questions (authored
        // order, own points) — the group container itself never sits a paper.
        $referenced = Question::query()
            ->whereIn('id', collect($questions)->pluck('question_id'))
            ->with(['children' => fn ($q) => $q->where('status', 'published')])
            ->get()
            ->keyBy('id');

        $sort = 0;

        foreach (array_values($questions) as $row) {
            $part = isset($row['part']) ? (int) $row['part'] : null;
            $partIndex = $part !== null && $part < $partCount ? $part : null;

            /** @var ?Question $question */
            $question = $referenced->get((int) $row['question_id']);

            if ($question?->type === QuestionType::Group) {
                foreach ($question->children as $child) {
                    $quiz->quizQuestions()->create([
                        'question_id' => $child->id,
                        'points' => null,
                        'sort_order' => $sort++,
                        'part_index' => $partIndex,
                    ]);
                }

                continue;
            }

            $quiz->quizQuestions()->create([
                'question_id' => (int) $row['question_id'],
                'points' => $row['points'] ?? null,
                'sort_order' => $sort++,
                'part_index' => $partIndex,
            ]);
        }
    }

    private function mayViewAssignmentLms(Request $request, SubjectAssignment $assignment): bool
    {
        $user = $request->user();

        return $user->hasPermissionForScope('lms.view', $assignment->school_id, $assignment->branch_id)
            || ($user->hasPermissionForScope('lms.manage_own', $assignment->school_id, $assignment->branch_id)
                && $assignment->isOwnedBy($user));
    }

    private function mayManageAssignmentLms(Request $request, SubjectAssignment $assignment): bool
    {
        $user = $request->user();

        return $user->hasPermissionForScope('lms.manage', $assignment->school_id, $assignment->branch_id)
            || ($user->hasPermissionForScope('lms.manage_own', $assignment->school_id, $assignment->branch_id)
                && $assignment->isOwnedBy($user));
    }

    /**
     * A teacher may hold employee files at several branches — ownership is
     * judged through employee.user_id, never a single employee row.
     *
     * @return list<int>
     */
    private function ownAssignmentIds(Request $request): array
    {
        return SubjectAssignment::query()
            ->whereHas('employee', fn ($q) => $q->where('user_id', $request->user()->id))
            ->pluck('id')
            ->all();
    }
}
