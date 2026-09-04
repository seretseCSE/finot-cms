<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AssignmentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\QuizAttemptStatus;
use App\Enums\QuizStatus;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\CourseMaterial;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentGuardian;
use App\Models\SubjectAssignment;
use App\Services\Chat\ChatService;
use App\Services\Lms\QuizAttemptService;
use App\Services\Notify\Notifier;
use App\Support\CourseworkFiles;
use App\Support\QuestionRules;
use App\Support\SearchTerm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * THE STUDENT/PARENT LMS LANE (ADR-012 + ADR-016). Access derives from
 * relationships only: a student's own enrollment reaches their class's
 * materials/assignments/exams; a guardian link (can_view_grades-gated for
 * scores) reaches a child's summary. The attempt endpoints double as the
 * exam-prep lane: platform mocks admit ANY authenticated user, so no-school
 * B2C takers are first-class citizens here — never through staff routes.
 */
class MeLmsController extends Controller
{
    // ── student: the class feed ──────────────────────────────────────────

    /** The "Today" feed: due-soon work, open exams, fresh materials. */
    public function overview(Request $request): JsonResponse
    {
        $student = $this->ownStudent($request);
        $anchorIds = $this->classAnchorIds($student);

        $assignments = Assignment::query()
            ->whereIn('subject_assignment_id', $anchorIds)
            ->where('status', AssignmentStatus::Published->value)
            ->where(fn ($q) => $q->whereNull('available_from')->orWhere('available_from', '<=', now()))
            ->visibleToStudent($student->id)
            ->with(['subjectAssignment.subject:id,name'])
            ->orderByRaw('due_at IS NULL, due_at')
            ->limit(20)
            ->get();

        $submissions = AssignmentSubmission::query()
            ->where('student_id', $student->id)
            ->whereIn('assignment_id', $assignments->pluck('id'))
            ->get()
            ->keyBy('assignment_id');

        $exams = Quiz::query()
            ->whereHas('targets', fn ($t) => $t->whereIn('subject_assignment_id', $anchorIds))
            ->where('status', QuizStatus::Published->value)
            ->with(['subjectAssignment.subject:id,name'])
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $attemptCounts = QuizAttempt::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('quiz_id', $exams->pluck('id'))
            ->where('status', '!=', QuizAttemptStatus::Invalidated->value)
            ->selectRaw('quiz_id, count(*) as c, max(status) as last_status')
            ->groupBy('quiz_id')
            ->get()
            ->keyBy('quiz_id');

        $materials = $this->materialsQuery($student)
            ->where('created_at', '>=', now()->subDays(14))
            ->limit(6)
            ->get();

        $quizProgress = $this->quizProgressFor($assignments, $request->user()->id);

        $examMaps = $this->examAttemptMaps($exams->pluck('id'), $request->user()->id);

        return response()->json(['data' => [
            'assignments' => $assignments->map(fn (Assignment $a): array => $this->assignmentRow($a, $submissions->get($a->id), quizProgress: $quizProgress[$a->quiz_id] ?? null)),
            'exams' => $exams->map(fn (Quiz $quiz): array => $this->examRow($quiz, $request->user()->id, (int) ($attemptCounts[$quiz->id]->c ?? 0), $examMaps)),
            'materials' => $materials->map(fn (CourseMaterial $m): array => $this->materialRow($m)),
        ]]);
    }

    public function materials(Request $request): JsonResponse
    {
        $student = $this->ownStudent($request);

        $materials = $this->materialsQuery($student)
            ->when($request->filled('subject_id'), fn ($q) => $q->where('subject_id', $request->integer('subject_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->tap(fn ($query) => SearchTerm::apply($query, $request->string('q')->trim()->value(), fn ($w, string $n) => $w
                ->where('title', 'ilike', SearchTerm::contains($n))))
            ->paginate(min($request->integer('per_page', 25), 100));

        return response()->json([
            'data' => collect($materials->items())->map(fn (CourseMaterial $m): array => $this->materialRow($m)),
            'meta' => ['current_page' => $materials->currentPage(), 'last_page' => $materials->lastPage(), 'total' => $materials->total()],
        ]);
    }

    public function assignments(Request $request): JsonResponse
    {
        $student = $this->ownStudent($request);

        $assignments = Assignment::query()
            ->whereIn('subject_assignment_id', $this->classAnchorIds($student))
            ->whereIn('status', [AssignmentStatus::Published->value, AssignmentStatus::Closed->value])
            ->where(fn ($q) => $q->whereNull('available_from')->orWhere('available_from', '<=', now()))
            ->visibleToStudent($student->id)
            ->with(['subjectAssignment.subject:id,name', 'subjectAssignment.section:id,name'])
            ->orderByRaw('due_at IS NULL, due_at')
            ->orderByDesc('id')
            ->paginate(min($request->integer('per_page', 25), 100));

        $submissions = AssignmentSubmission::query()
            ->where('student_id', $student->id)
            ->whereIn('assignment_id', collect($assignments->items())->pluck('id'))
            ->get()
            ->keyBy('assignment_id');

        $quizProgress = $this->quizProgressFor(collect($assignments->items()), $request->user()->id);

        return response()->json([
            'data' => collect($assignments->items())
                ->map(fn (Assignment $a): array => $this->assignmentRow($a, $submissions->get($a->id), quizProgress: $quizProgress[$a->quiz_id] ?? null)),
            'meta' => ['current_page' => $assignments->currentPage(), 'last_page' => $assignments->lastPage(), 'total' => $assignments->total()],
        ]);
    }

    public function showAssignment(Request $request, Assignment $assignment): JsonResponse
    {
        $student = $this->ownStudent($request);
        $this->assertReachesStudent($assignment->subject_assignment_id, $student);
        abort_unless($assignment->reachesStudent($student->id), 403, 'This assignment was not posted to you.');

        $submission = AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->where('student_id', $student->id)
            ->first();

        $assignment->load(['subjectAssignment.subject:id,name', 'subjectAssignment.section:id,name']);

        $quizProgress = $this->quizProgressFor(collect([$assignment]), $request->user()->id);

        return response()->json(['data' => $this->assignmentRow($assignment, $submission, detailed: true, userId: $request->user()->id, quizProgress: $quizProgress[$assignment->quiz_id] ?? null)]);
    }

    /** Turn in work (text, files, photos, audio, a link). */
    public function submitAssignment(Request $request, Assignment $assignment): JsonResponse
    {
        $student = $this->ownStudent($request);
        $this->assertReachesStudent($assignment->subject_assignment_id, $student);
        abort_unless($assignment->reachesStudent($student->id), 403, 'This assignment was not posted to you.');

        abort_unless($assignment->status === AssignmentStatus::Published, 422, 'This assignment is not accepting submissions.');
        abort_if($assignment->kind === 'quiz', 422, 'This is a quiz assignment — open the quiz to do the work.');

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:50000'],
            'link_url' => ['nullable', 'url', 'max:2048'],
            'files' => ['sometimes', 'array', 'max:10'],
            // Photos straight from the phone camera and voice recordings are
            // first-class here — most Ethiopian homework lives on paper.
            'files.*' => CourseworkFiles::rules(),
        ]);

        $types = $assignment->submission_types;
        $acceptsUploads = array_intersect(['file', 'photo', 'audio'], $types) !== [];

        if (($data['body'] ?? null) === null && ($data['link_url'] ?? null) === null && ! $request->hasFile('files')) {
            throw ValidationException::withMessages(['body' => ['Write your answer, attach your work, or paste a link.']]);
        }
        if (! in_array('text', $types, true) && ($data['body'] ?? null) !== null) {
            throw ValidationException::withMessages(['body' => ['This assignment does not accept written answers.']]);
        }
        if (! $acceptsUploads && $request->hasFile('files')) {
            throw ValidationException::withMessages(['files' => ['This assignment does not accept uploads.']]);
        }
        if (! in_array('link', $types, true) && ($data['link_url'] ?? null) !== null) {
            throw ValidationException::withMessages(['link_url' => ['This assignment does not accept links.']]);
        }

        $isLate = $assignment->due_at !== null && now()->greaterThan($assignment->due_at);
        if ($isLate && $assignment->late_policy === 'reject') {
            throw ValidationException::withMessages(['body' => ['The deadline has passed — late submissions are not accepted.']]);
        }

        $existing = AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->where('student_id', $student->id)
            ->first();

        if ($existing !== null) {
            $policy = $assignment->resubmission_policy;

            if ($policy === 'never') {
                throw ValidationException::withMessages(['body' => ['This assignment allows a single submission.']]);
            }
            if ($policy === 'once' && $existing->attempt_count >= 2) {
                throw ValidationException::withMessages(['body' => ['You have used your one resubmission.']]);
            }
            if ($existing->status !== SubmissionStatus::Submitted) {
                throw ValidationException::withMessages(['body' => ['Your submission was already graded — it can no longer change.']]);
            }
        }

        $files = collect($existing?->files ?? []);
        foreach ($request->file('files', []) as $file) {
            $path = $file->store("lms/submissions/{$assignment->id}/{$student->id}", ['disk' => config('filesystems.default')]);
            $files->push([
                'name' => $file->getClientOriginalName(),
                'path' => $path,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
            ]);
        }

        $enrollment = StudentEnrollment::query()
            ->where('student_id', $student->id)
            ->where('section_id', $assignment->subjectAssignment?->section_id)
            ->where('status', EnrollmentStatus::Active->value)
            ->first();

        $submission = AssignmentSubmission::query()->updateOrCreate(
            ['assignment_id' => $assignment->id, 'student_id' => $student->id],
            [
                'student_enrollment_id' => $enrollment?->id,
                // The written answer is WYSIWYG — sanitize like any rich
                // text; inline images persist as data-path markers.
                'body' => isset($data['body'])
                    ? QuestionRules::normalizeStemMedia(QuestionRules::sanitizeStem($data['body']))
                    : $existing?->body,
                'link_url' => $data['link_url'] ?? $existing?->link_url,
                'files' => $files->values()->all(),
                'attempt_count' => ($existing?->attempt_count ?? 0) + 1,
                'submitted_at' => now(),
                'is_late' => $isLate,
                'status' => SubmissionStatus::Submitted->value,
            ],
        );

        // The teacher's inbox folds repeats into ":count new submissions"
        // instead of stacking forty siblings on a busy deadline day.
        $assignment->loadMissing('subjectAssignment.employee.user');
        app(Notifier::class)->toUser(
            $assignment->subjectAssignment?->employee?->user,
            'lms.submission_received',
            ['title' => $assignment->title],
            [
                'link' => "/lms/assignments/{$assignment->id}",
                'schoolId' => $assignment->subjectAssignment?->school_id,
                'branchId' => $assignment->subjectAssignment?->branch_id,
                'dedupeKey' => "submission:{$assignment->id}",
            ],
        );

        return response()->json([
            'data' => $this->submissionRow($submission),
            'message' => $isLate ? 'Submitted (marked late).' : 'Submitted — good work!',
        ], 201);
    }

    /**
     * Media for the student's rich written answer (inline images from the
     * WYSIWYG). Same data-path model as staff uploads, but student-gated,
     * images only, and filed under the student's own folder.
     */
    public function upload(Request $request): JsonResponse
    {
        $student = $this->ownStudent($request);

        $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,gif'],
        ]);

        $file = $request->file('file');
        $path = $file->store("lms/answer-media/{$student->id}", ['disk' => config('filesystems.default')]);

        return response()->json(['data' => [
            'name' => $file->getClientOriginalName(),
            'path' => $path,
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'url' => s3Url($path),
        ]], 201);
    }

    /**
     * My private thread with the teacher on one assignment — a CONTEXT
     * conversation of the chat engine (ADR-019), resolved (creating on first
     * open) and then driven entirely by the /me/chat/* endpoints.
     */
    public function assignmentThread(Request $request, Assignment $assignment, ChatService $chat): JsonResponse
    {
        $student = $this->ownStudent($request);
        $this->assertReachesStudent($assignment->subject_assignment_id, $student);
        abort_unless($assignment->reachesStudent($student->id), 403, 'This assignment was not posted to you.');

        $assignment->loadMissing('subjectAssignment.employee');

        $conversation = $chat->forContext(
            'assignment',
            $assignment->id,
            (int) $assignment->school_id,
            $assignment->branch_id,
            array_values(array_filter([
                $assignment->subjectAssignment?->employee?->user_id,
                $student->user_id,
            ])),
            $student->id,
        );

        return response()->json(['data' => ['conversation_id' => $conversation->id]]);
    }

    /** Remove one of my uploaded files while the submission is editable. */
    public function removeSubmissionFile(Request $request, Assignment $assignment): JsonResponse
    {
        $student = $this->ownStudent($request);
        $this->assertReachesStudent($assignment->subject_assignment_id, $student);

        $data = $request->validate(['path' => ['required', 'string']]);

        $submission = AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->where('student_id', $student->id)
            ->firstOrFail();

        abort_unless($submission->status === SubmissionStatus::Submitted, 422, 'Your submission was already graded.');

        $files = collect($submission->files ?? [])->reject(fn (array $f): bool => ($f['path'] ?? null) === $data['path']);
        Storage::disk(config('filesystems.default'))->delete($data['path']);

        $submission->update(['files' => $files->values()->all()]);

        return response()->json(['data' => $this->submissionRow($submission), 'message' => 'File removed.']);
    }

    // ── student: class exams ─────────────────────────────────────────────

    public function exams(Request $request): JsonResponse
    {
        $student = $this->ownStudent($request);
        $userId = $request->user()->id;

        $exams = Quiz::query()
            ->whereHas('targets', fn ($t) => $t->whereIn('subject_assignment_id', $this->classAnchorIds($student)))
            ->whereIn('status', [QuizStatus::Published->value, QuizStatus::Closed->value])
            ->with(['subjectAssignment.subject:id,name', 'subjectAssignment.section:id,name'])
            ->orderByDesc('id')
            ->paginate(min($request->integer('per_page', 25), 100));

        $ids = collect($exams->items())->pluck('id');

        $counts = QuizAttempt::query()
            ->where('user_id', $userId)
            ->whereIn('quiz_id', $ids)
            ->where('status', '!=', QuizAttemptStatus::Invalidated->value)
            ->selectRaw('quiz_id, count(*) as c')
            ->groupBy('quiz_id')
            ->pluck('c', 'quiz_id');

        $maps = $this->examAttemptMaps($ids, $userId);

        return response()->json([
            'data' => collect($exams->items())->map(fn (Quiz $quiz): array => $this->examRow($quiz, $userId, (int) ($counts[$quiz->id] ?? 0), $maps)),
            'meta' => ['current_page' => $exams->currentPage(), 'last_page' => $exams->lastPage(), 'total' => $exams->total()],
        ]);
    }

    // ── the attempt engine (class exams + platform exam prep) ────────────

    /** Start or resume. Access is decided by the engine per lane. */
    public function startExam(Request $request, Quiz $quiz, QuizAttemptService $engine): JsonResponse
    {
        $data = $request->validate(['access_code' => ['nullable', 'string', 'max:20']]);

        $attempt = $engine->start(
            $quiz,
            $request->user(),
            $data['access_code'] ?? null,
            (string) $request->user()->currentAccessToken()?->id,
        );

        return response()->json([
            'data' => $this->attemptState($attempt, $engine),
            'message' => 'Good luck!',
        ], 201);
    }

    /** The live paper — resume-safe, never contains answer keys. */
    public function attempt(Request $request, QuizAttempt $attempt, QuizAttemptService $engine): JsonResponse
    {
        $this->assertOwnAttempt($request, $attempt);

        if ($attempt->status === QuizAttemptStatus::InProgress && $attempt->isExpired()) {
            $engine->submit($attempt, expired: true);
            $attempt->refresh();
        }

        return response()->json(['data' => $this->attemptState($attempt, $engine, withPaper: true)]);
    }

    /** Autosave one answer. */
    public function answer(Request $request, QuizAttempt $attempt, QuizAttemptService $engine): JsonResponse
    {
        $this->assertOwnAttempt($request, $attempt);

        $data = $request->validate([
            'question_id' => ['required', 'integer'],
            'answer' => ['nullable'],
        ]);

        $engine->saveAnswer($attempt, (int) $data['question_id'], $data['answer'] ?? null);

        return response()->json(['data' => ['saved_at' => now()->toIso8601String(), 'remaining_seconds' => $attempt->remainingSeconds()]]);
    }

    /** Integrity beacons from the player (blur, fullscreen exit, paste…). */
    public function logEvent(Request $request, QuizAttempt $attempt, QuizAttemptService $engine): JsonResponse
    {
        $this->assertOwnAttempt($request, $attempt);

        $data = $request->validate([
            'type' => ['required', 'string', 'in:blur,focus,fullscreen_exit,paste,copy,reconnect,resize'],
        ]);

        if ($attempt->status === QuizAttemptStatus::InProgress) {
            $engine->logEvent($attempt, $data['type'], flag: in_array($data['type'], ['blur', 'fullscreen_exit', 'paste', 'copy'], true));
        }

        return response()->json(['data' => ['ok' => true]]);
    }

    public function submitExam(Request $request, QuizAttempt $attempt, QuizAttemptService $engine): JsonResponse
    {
        $this->assertOwnAttempt($request, $attempt);

        $attempt = $engine->submit($attempt);

        return response()->json([
            'data' => $this->attemptState($attempt, $engine),
            'message' => 'Your exam was submitted.',
        ]);
    }

    /** Results — visibility follows the quiz's reveal policy. */
    public function result(Request $request, QuizAttempt $attempt, QuizAttemptService $engine): JsonResponse
    {
        $this->assertOwnAttempt($request, $attempt);
        abort_if($attempt->status === QuizAttemptStatus::InProgress, 422, 'Finish the exam first.');

        $quiz = $attempt->quiz;

        if (! $this->resultsVisible($quiz, $attempt)) {
            $policy = (string) ($quiz?->setting('results_policy', 'immediately') ?? 'immediately');

            return response()->json(['data' => [
                'visible' => false,
                'status' => $attempt->status->value,
                'quiz_title' => $quiz?->title,
                'submitted_at' => $attempt->submitted_at,
                'question_count' => count($attempt->question_ids ?? []),
                'answered_count' => $attempt->answers()->count(),
                'results_policy' => $policy,
                'expected_release_at' => $policy === 'after_close' ? $quiz?->setting('closes_at') : null,
                'message' => 'Results will be released by your teacher.',
            ]]);
        }

        $reveal = (bool) $quiz->setting('reveal_answers', false);
        $answers = $attempt->answers()->get()->keyBy('question_id');
        $questions = Question::query()
            ->whereIn('id', collect($attempt->question_ids)->pluck('id'))
            ->get()
            ->keyBy('id');

        $rows = collect($attempt->question_ids)->map(function (array $entry, int $index) use ($questions, $answers, $reveal): ?array {
            /** @var ?Question $question */
            $question = $questions->get($entry['id']);
            if ($question === null) {
                return null;
            }
            $answer = $answers->get($question->id);

            return [
                'number' => $index + 1,
                'question_id' => $question->id,
                'part' => $entry['part'] ?? null,
                'group_id' => isset($entry['group']) ? (int) $entry['group'] : ($question->parent_id !== null ? (int) $question->parent_id : null),
                'type' => $question->type->value,
                'points' => (float) $entry['points'],
                'body' => $question->presentBody(),
                'answer' => $answer?->answer,
                'earned' => $answer?->effectiveScore(),
                'pending' => $answer !== null && $answer->effectiveScore() === null,
                'feedback' => $answer?->feedback,
                ...($reveal ? [
                    'answer_key' => $question->answer_key,
                    'explanation' => $question->explanation,
                ] : []),
            ];
        })->filter()->values();

        return response()->json(['data' => [
            'visible' => true,
            'quiz_title' => $quiz->title,
            'parts' => $quiz->presentParts(),
            'status' => $attempt->status->value,
            'pending_manual' => $attempt->pending_manual,
            'score' => $attempt->score !== null ? (float) $attempt->score : null,
            'max_score' => (float) $attempt->max_score,
            'submitted_at' => $attempt->submitted_at,
            'reveal_answers' => $reveal,
            'questions' => $rows,
            'groups' => $engine->groupStems($rows->all()),
        ]]);
    }

    /** My attempt history (exam-prep progress + past class exams). */
    public function myAttempts(Request $request): JsonResponse
    {
        $attempts = QuizAttempt::query()
            ->where('user_id', $request->user()->id)
            ->where('status', '!=', QuizAttemptStatus::InProgress->value)
            ->with(['quiz:id,title,kind,is_platform,subject_id,grade_level_id,settings,status,closed_at', 'quiz.subject:id,name', 'quiz.gradeLevel:id,name'])
            ->orderByDesc('submitted_at')
            ->paginate(min($request->integer('per_page', 25), 100));

        return response()->json([
            'data' => collect($attempts->items())->map(function (QuizAttempt $attempt): array {
                $visible = $this->resultsVisible($attempt->quiz, $attempt);

                return [
                    'id' => $attempt->id,
                    'quiz_id' => $attempt->quiz_id,
                    'quiz_title' => $attempt->quiz?->title,
                    'kind' => $attempt->quiz?->kind,
                    'is_platform' => (bool) $attempt->quiz?->is_platform,
                    'subject_name' => $attempt->quiz?->subject?->name,
                    'grade_level_name' => $attempt->quiz?->gradeLevel?->name,
                    'status' => $attempt->status->value,
                    'submitted_at' => $attempt->submitted_at,
                    'score' => $visible && $attempt->score !== null ? (float) $attempt->score : null,
                    'max_score' => (float) $attempt->max_score,
                    'results_visible' => $visible,
                ];
            }),
            'meta' => ['current_page' => $attempts->currentPage(), 'last_page' => $attempts->lastPage(), 'total' => $attempts->total()],
        ]);
    }

    // ── parent: child summary ────────────────────────────────────────────

    public function childLms(Request $request, Student $student): JsonResponse
    {
        $link = $this->guardianLinkFor($request, $student);

        $anchorIds = $this->classAnchorIds($student);

        $assignments = Assignment::query()
            ->whereIn('subject_assignment_id', $anchorIds)
            ->whereIn('status', [AssignmentStatus::Published->value, AssignmentStatus::Closed->value])
            ->visibleToStudent($student->id)
            ->with('subjectAssignment.subject:id,name')
            ->orderByRaw('due_at IS NULL, due_at')
            ->limit(30)
            ->get();

        $submissions = AssignmentSubmission::query()
            ->where('student_id', $student->id)
            ->whereIn('assignment_id', $assignments->pluck('id'))
            ->get()
            ->keyBy('assignment_id');

        $attempts = QuizAttempt::query()
            ->where('student_id', $student->id)
            ->where('status', '!=', QuizAttemptStatus::InProgress->value)
            ->with(['quiz:id,title,kind,settings,status,closed_at,subject_assignment_id', 'quiz.subjectAssignment.subject:id,name'])
            ->orderByDesc('submitted_at')
            ->limit(20)
            ->get();

        return response()->json(['data' => [
            'can_view_grades' => $link->can_view_grades,
            'assignments' => $assignments->map(function (Assignment $a) use ($submissions, $link): array {
                $submission = $submissions->get($a->id);

                return [
                    'id' => $a->id,
                    'title' => $a->title,
                    'subject_name' => $a->subjectAssignment?->subject?->name,
                    'due_at' => $a->due_at,
                    'status' => $a->status->value,
                    'submission_status' => $submission?->status->value,
                    'is_late' => (bool) $submission?->is_late,
                    'score' => $link->can_view_grades && $submission?->score !== null ? (float) $submission->score : null,
                    'max_score' => $a->max_score !== null ? (float) $a->max_score : null,
                ];
            }),
            'exams' => $attempts->map(function (QuizAttempt $attempt) use ($link): array {
                $visible = $link->can_view_grades && $this->resultsVisible($attempt->quiz, $attempt);

                return [
                    'quiz_title' => $attempt->quiz?->title,
                    'kind' => $attempt->quiz?->kind,
                    'subject_name' => $attempt->quiz?->subjectAssignment?->subject?->name,
                    'submitted_at' => $attempt->submitted_at,
                    'score' => $visible && $attempt->score !== null ? (float) $attempt->score : null,
                    'max_score' => (float) $attempt->max_score,
                ];
            }),
        ]]);
    }

    // ── shared row shapes ────────────────────────────────────────────────

    private function assignmentRow(Assignment $assignment, ?AssignmentSubmission $submission, bool $detailed = false, ?int $userId = null, ?array $quizProgress = null): array
    {
        return [
            'id' => $assignment->id,
            'kind' => $assignment->kind,
            'quiz_id' => $assignment->quiz_id,
            'title' => $assignment->title,
            'subject_name' => $assignment->subjectAssignment?->subject?->name,
            'section_name' => $assignment->subjectAssignment?->section?->name,
            'submission_types' => $assignment->submission_types,
            'resubmission_policy' => $assignment->resubmission_policy,
            'max_score' => $assignment->max_score !== null ? (float) $assignment->max_score : null,
            'due_at' => $assignment->due_at,
            'late_policy' => $assignment->late_policy,
            'late_penalty_percent' => $assignment->late_penalty_percent !== null ? (float) $assignment->late_penalty_percent : null,
            'status' => $assignment->status->value,
            'submission' => $submission !== null ? $this->submissionRow($submission) : null,
            // Quiz-kind work carries no AssignmentSubmission (the work lives in
            // quiz_attempts) — this lets the client read a completed quiz as
            // "turned in" instead of forever showing an un-submitted badge.
            'quiz_progress' => $assignment->kind === 'quiz' ? $quizProgress : null,
            ...($detailed ? [
                'instructions' => $assignment->presentInstructions(),
                'rubric' => $assignment->rubric,
                'attachments' => collect($assignment->attachments ?? [])->map(fn (array $f): array => [
                    'name' => $f['name'] ?? 'file',
                    'size' => $f['size'] ?? null,
                    'mime_type' => $f['mime_type'] ?? null,
                    'url' => isset($f['path']) ? s3Url($f['path']) : null,
                ])->all(),
                // Quiz-kind work: the linked quiz IS the submission lane.
                ...($assignment->kind === 'quiz' && $assignment->quiz_id !== null && $userId !== null
                    ? ['quiz' => ($quiz = Quiz::with(['subjectAssignment.subject:id,name', 'subjectAssignment.section:id,name'])->find($assignment->quiz_id)) !== null
                        ? $this->examRow($quiz, $userId)
                        : null]
                    : []),
            ] : []),
        ];
    }

    private function submissionRow(AssignmentSubmission $submission): array
    {
        return [
            'id' => $submission->id,
            'status' => $submission->status->value,
            'submitted_at' => $submission->submitted_at,
            'is_late' => $submission->is_late,
            'attempt_count' => $submission->attempt_count,
            'body' => $submission->presentBody(),
            'link_url' => $submission->link_url,
            'files' => collect($submission->files ?? [])->map(fn (array $f): array => [
                'name' => $f['name'] ?? 'file',
                'path' => $f['path'] ?? null,
                'size' => $f['size'] ?? null,
                'mime_type' => $f['mime_type'] ?? null,
                'url' => isset($f['path']) ? s3Url($f['path']) : null,
            ])->all(),
            'score' => $submission->score !== null ? (float) $submission->score : null,
            'rubric_scores' => $submission->rubric_scores,
            'feedback' => $submission->presentFeedback(),
            'graded_at' => $submission->graded_at,
        ];
    }

    /**
     * Quiz-kind assignments never get an AssignmentSubmission — the work is a
     * QuizAttempt. This batch-loads each quiz's attempts for the user (one
     * query, no N+1) so a finished quiz reads as "turned in", exposing its
     * score only when the quiz's results policy allows. Keyed by quiz_id.
     *
     * @param  Collection<int, Assignment>  $assignments
     * @return array<int, array{status: string, submitted_at: mixed, score: float|null, max_score: float, attempts_used: int}>
     */
    private function quizProgressFor($assignments, int $userId): array
    {
        $quizIds = $assignments
            ->where('kind', 'quiz')
            ->pluck('quiz_id')
            ->filter()
            ->unique()
            ->values();

        if ($quizIds->isEmpty()) {
            return [];
        }

        return QuizAttempt::query()
            ->where('user_id', $userId)
            ->whereIn('quiz_id', $quizIds)
            ->where('status', '!=', QuizAttemptStatus::Invalidated->value)
            ->with('quiz:id,settings,status,closed_at')
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('quiz_id')
            ->mapWithKeys(function ($group, $quizId): array {
                // The freshest FINISHED attempt is the one that counts as
                // turned in; fall back to the latest (still in-progress) one.
                $done = $group->firstWhere('status', '!=', QuizAttemptStatus::InProgress)
                    ?? $group->first();

                return [(int) $quizId => [
                    'status' => $done->status->value,
                    'submitted_at' => $done->submitted_at,
                    'score' => $this->resultsVisible($done->quiz, $done) && $done->score !== null
                        ? (float) $done->score
                        : null,
                    'max_score' => (float) $done->max_score,
                    'attempts_used' => $group->count(),
                ]];
            })
            ->all();
    }

    /**
     * Batch the two per-quiz attempt lookups examRow needs (the live sitting and
     * the latest finished one) into two queries for a whole page of quizzes,
     * instead of two per quiz. Returns collections keyed by quiz_id.
     *
     * @return array{live: Collection, finished: Collection}
     */
    private function examAttemptMaps(iterable $quizIds, int $userId): array
    {
        $ids = collect($quizIds)->all();

        if ($ids === []) {
            return ['live' => collect(), 'finished' => collect()];
        }

        $live = QuizAttempt::query()
            ->where('user_id', $userId)
            ->whereIn('quiz_id', $ids)
            ->where('status', QuizAttemptStatus::InProgress->value)
            ->get()
            ->keyBy('quiz_id');

        $finished = QuizAttempt::query()
            ->where('user_id', $userId)
            ->whereIn('quiz_id', $ids)
            ->whereNotIn('status', [QuizAttemptStatus::InProgress->value, QuizAttemptStatus::Invalidated->value])
            ->orderByDesc('submitted_at')
            ->get()
            ->unique('quiz_id') // ordered desc → keeps each quiz's latest sitting
            ->keyBy('quiz_id');

        return ['live' => $live, 'finished' => $finished];
    }

    /**
     * @param  array{live: Collection, finished: Collection}|null  $maps
     *                                                                    Prefetched per-quiz attempt lookups (see examAttemptMaps) so list endpoints
     *                                                                    avoid an N+1. When null, the two attempts are fetched for this quiz alone.
     */
    private function examRow(Quiz $quiz, int $userId, ?int $attemptCount = null, ?array $maps = null): array
    {
        $attemptCount ??= QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('user_id', $userId)
            ->where('status', '!=', QuizAttemptStatus::Invalidated->value)
            ->count();

        if ($maps !== null) {
            $live = $maps['live']->get($quiz->id);
            $finished = $maps['finished']->get($quiz->id);
        } else {
            $live = QuizAttempt::query()
                ->where('quiz_id', $quiz->id)
                ->where('user_id', $userId)
                ->where('status', QuizAttemptStatus::InProgress->value)
                ->first();

            // The most recent finished sitting — powers the "View result" affordance.
            $finished = QuizAttempt::query()
                ->where('quiz_id', $quiz->id)
                ->where('user_id', $userId)
                ->whereNotIn('status', [QuizAttemptStatus::InProgress->value, QuizAttemptStatus::Invalidated->value])
                ->orderByDesc('submitted_at')
                ->first();
        }

        // best_score/max are only surfaced when results_policy allows it.
        $resultVisible = $finished !== null && $this->resultsVisible($quiz, $finished);

        $allowed = (int) $quiz->setting('attempts_allowed', 1);

        return [
            'id' => $quiz->id,
            'kind' => $quiz->kind,
            'title' => $quiz->title,
            'instructions' => $quiz->presentInstructions(),
            'subject_name' => $quiz->subjectAssignment?->subject?->name ?? $quiz->subject?->name,
            'section_name' => $quiz->subjectAssignment?->section?->name,
            'grade_level_name' => $quiz->gradeLevel?->name,
            'language' => $quiz->language,
            'status' => $quiz->status->value,
            'duration_minutes' => $quiz->setting('duration_minutes'),
            'opens_at' => $quiz->setting('opens_at'),
            'closes_at' => $quiz->setting('closes_at'),
            'attempts_allowed' => $allowed,
            'attempts_used' => $attemptCount,
            'requires_access_code' => $quiz->requiresAccessCode(),
            'window_open' => $quiz->windowOpen(),
            'can_start' => $quiz->status === QuizStatus::Published
                && $quiz->windowOpen()
                && ($allowed === 0 || $attemptCount < $allowed || $live !== null),
            'live_attempt_id' => $live?->id,
            'result_attempt_id' => $finished?->id,
            'best_score' => $resultVisible && $finished->score !== null ? (float) $finished->score : null,
            'best_max_score' => $resultVisible && $finished->score !== null ? (float) $finished->max_score : null,
            'question_count' => is_array($quiz->draw) && $quiz->draw !== []
                ? collect($quiz->draw)->sum('count')
                : $quiz->quizQuestions()->count(),
        ];
    }

    private function materialRow(CourseMaterial $material): array
    {
        $content = $material->content ?? [];

        return [
            'id' => $material->id,
            'title' => $material->title,
            'description' => $material->presentDescription(),
            'type' => $material->type,
            'subject_name' => $material->subject?->name,
            'is_pinned' => $material->is_pinned,
            'posted_at' => $material->created_at,
            'content' => match ($material->type) {
                'file' => [
                    'name' => $content['name'] ?? $material->title,
                    'size' => $content['size'] ?? null,
                    'mime_type' => $content['mime_type'] ?? null,
                    'url' => isset($content['path']) ? s3Url($content['path']) : null,
                ],
                'text' => ['body' => $material->presentTextBody()],
                default => $content,
            },
        ];
    }

    private function attemptState(QuizAttempt $attempt, QuizAttemptService $engine, bool $withPaper = false): array
    {
        $quiz = $attempt->quiz;

        return [
            'attempt_id' => $attempt->id,
            'quiz_id' => $attempt->quiz_id,
            'quiz_title' => $quiz?->title,
            'kind' => $quiz?->kind,
            'status' => $attempt->status->value,
            'attempt_number' => $attempt->attempt_number,
            'started_at' => $attempt->started_at,
            'deadline_at' => $attempt->deadline_at,
            'remaining_seconds' => $attempt->remainingSeconds(),
            'navigation' => $quiz?->setting('navigation', 'free'),
            'max_score' => (float) $attempt->max_score,
            'question_count' => count($attempt->question_ids ?? []),
            'results_policy' => $quiz?->setting('results_policy', 'immediately'),
            ...($withPaper && $attempt->status === QuizAttemptStatus::InProgress
                ? (function () use ($quiz, $engine, $attempt): array {
                    $questions = $engine->paper($attempt);

                    return [
                        'instructions' => $quiz?->presentInstructions(),
                        'parts' => $quiz?->presentParts(),
                        'questions' => $questions,
                        'groups' => $engine->groupStems($questions),
                    ];
                })()
                : []),
        ];
    }

    // ── access plumbing (relationship lane only) ─────────────────────────

    /**
     * Materials that reach this student: rows targeted at their classes plus
     * grade-window rows of their school/branch (no targets).
     */
    private function materialsQuery(Student $student): Builder
    {
        $anchorIds = $this->classAnchorIds($student);
        $enrollments = StudentEnrollment::query()
            ->where('student_id', $student->id)
            ->where('status', EnrollmentStatus::Active->value)
            ->whereNotNull('section_id')
            ->with('section.gradeLevel')
            ->get();

        return CourseMaterial::query()
            ->where('is_active', true)
            ->with('subject:id,name')
            ->where(function ($q) use ($anchorIds, $enrollments): void {
                $q->whereHas('targets', fn ($t) => $t->whereIn('subject_assignment_id', $anchorIds));

                foreach ($enrollments as $enrollment) {
                    $gradeSort = $enrollment->section?->gradeLevel?->sort_order;

                    $q->orWhere(function ($w) use ($enrollment, $gradeSort): void {
                        $w->where('school_id', $enrollment->school_id)
                            ->whereDoesntHave('targets')
                            ->where(fn ($b) => $b->whereNull('branch_id')->orWhere('branch_id', $enrollment->branch_id))
                            ->when($gradeSort !== null, fn ($g) => $g
                                ->where(fn ($x) => $x->whereNull('min_grade_sort')->orWhere('min_grade_sort', '<=', $gradeSort))
                                ->where(fn ($x) => $x->whereNull('max_grade_sort')->orWhere('max_grade_sort', '>=', $gradeSort)));
                    });
                }
            })
            ->orderByDesc('is_pinned')
            ->orderByDesc('id');
    }

    private function ownStudent(Request $request): Student
    {
        $student = Student::query()->where('user_id', $request->user()->id)->first();
        abort_if($student === null, 404, 'No student record is linked to your account.');

        return $student;
    }

    /**
     * The subject assignments of every section the student actively sits in
     * — the whole relationship lane hangs off this list.
     *
     * @return list<int>
     */
    private function classAnchorIds(Student $student): array
    {
        $sectionIds = StudentEnrollment::query()
            ->where('student_id', $student->id)
            ->where('status', EnrollmentStatus::Active->value)
            ->whereNotNull('section_id')
            ->pluck('section_id');

        if ($sectionIds->isEmpty()) {
            return [];
        }

        return SubjectAssignment::query()
            ->whereIn('section_id', $sectionIds)
            ->pluck('id')
            ->all();
    }

    private function assertReachesStudent(?int $subjectAssignmentId, Student $student): void
    {
        abort_unless(
            $subjectAssignmentId !== null
            && in_array($subjectAssignmentId, $this->classAnchorIds($student), true),
            403,
            'This item belongs to a class you are not enrolled in.',
        );
    }

    private function assertOwnAttempt(Request $request, QuizAttempt $attempt): void
    {
        abort_unless((int) $attempt->user_id === (int) $request->user()->id, 403);
    }

    private function resultsVisible(?Quiz $quiz, QuizAttempt $attempt): bool
    {
        if ($quiz === null || $attempt->status === QuizAttemptStatus::InProgress) {
            return false;
        }

        return match ((string) $quiz->setting('results_policy', 'immediately')) {
            'after_close' => $quiz->status === QuizStatus::Closed || ! $quiz->windowOpen(),
            'manual' => (bool) $quiz->setting('results_released', false),
            default => true,
        };
    }

    private function guardianLinkFor(Request $request, Student $student): StudentGuardian
    {
        $parentId = $request->user()->parentProfile()->value('id');

        $link = StudentGuardian::query()
            ->where('is_active', true)
            ->where('parent_id', $parentId ?? 0)
            ->where('student_id', $student->id)
            ->first();

        abort_if($link === null, 403, 'This student is not linked to your account.');

        return $link;
    }
}
