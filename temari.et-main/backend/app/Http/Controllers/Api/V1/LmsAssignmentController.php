<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AssignmentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AssignmentSubmissionResource;
use App\Http\Resources\LmsAssignmentResource;
use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\Quiz;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\SubjectAssignment;
use App\Models\User;
use App\Rules\NotPastDay;
use App\Services\Chat\ChatService;
use App\Services\Lms\GradebookSync;
use App\Services\Notify\Notifier;
use App\Support\CourseworkFiles;
use App\Support\QuestionRules;
use App\Support\SearchTerm;
use App\Support\TermGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Homework/classwork — staff lane (ADR-016). Anchored to the teacher's
 * subject_assignment; students turn in through /me. Graded scores flow to
 * the gradebook via the linked assessment slot.
 */
class LmsAssignmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Assignment::query()
            ->with(['subjectAssignment.section:id,name,grade_level_id', 'subjectAssignment.section.gradeLevel:id,name', 'subjectAssignment.subject:id,name', 'assessment:id,name'])
            ->withCount('submissions')
            ->orderByDesc('id');

        if ($request->filled('subject_assignment_id')) {
            $assignment = SubjectAssignment::findOrFail($request->integer('subject_assignment_id'));
            abort_unless($this->mayView($request, $assignment), 403);
            $query->where('subject_assignment_id', $assignment->id);
        } else {
            $branch = $this->activeBranchOrNull($request);
            $schoolId = $branch?->school_id ?? $this->activeSchoolScopeId($request);
            abort_if($schoolId === null, 422, 'Select a school context to view assignments.');

            if ($user->hasPermissionForScope('lms.view', $schoolId, $branch?->id)) {
                $query->where('school_id', $schoolId)
                    ->when($branch !== null, fn ($q) => $q->where('branch_id', $branch->id))
                    ->when($this->branchFilterId($request, $branch), fn ($q, $id) => $q->where('branch_id', $id));
            } elseif ($user->hasPermissionForScope('lms.manage_own', $schoolId, $branch?->id)) {
                $query->whereHas('subjectAssignment.employee', fn ($q) => $q->where('user_id', $user->id));
            } else {
                abort(403);
            }
        }

        $assignments = $query
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->tap(fn ($query) => SearchTerm::apply($query, $request->string('q')->trim()->value(), fn ($w, string $n) => $w
                ->where('title', 'ilike', SearchTerm::contains($n))))
            ->paginate(min($request->integer('per_page', 25), 100));

        return LmsAssignmentResource::collection($assignments)->response();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request);

        $anchor = SubjectAssignment::findOrFail((int) $data['subject_assignment_id']);
        abort_unless($this->mayManage($request, $anchor), 403);
        TermGate::assertWritable($anchor->term);
        $this->assertAssessmentBelongs($data['assessment_id'] ?? null, $anchor);
        $this->assertQuizBelongs($data, $anchor);
        $this->assertTargetsEnrolled($data['target_student_ids'] ?? null, $anchor);

        $kind = $data['kind'] ?? 'standard';
        $rubric = $data['rubric'] ?? null;
        // `status` is the editor's dropdown; the legacy `publish` flag maps in.
        $status = $data['status'] ?? ($request->boolean('publish') ? AssignmentStatus::Published->value : AssignmentStatus::Draft->value);

        $assignment = Assignment::create([
            'school_id' => $anchor->school_id,
            'branch_id' => $anchor->branch_id,
            'subject_assignment_id' => $anchor->id,
            'kind' => $kind,
            'quiz_id' => $kind === 'quiz' ? ($data['quiz_id'] ?? null) : null,
            'title' => $data['title'],
            'instructions' => $this->cleanInstructions($data['instructions'] ?? null),
            'submission_types' => $this->submissionTypesFor($kind, $data),
            'attachments' => $this->storeAttachments($request),
            'rubric' => $rubric,
            'target_student_ids' => $this->intTargets($data),
            // A rubric IS the mark scheme — its points define the total.
            'max_score' => $rubric !== null ? $this->rubricTotal($rubric) : ($data['max_score'] ?? null),
            'available_from' => $data['available_from'] ?? null,
            'due_at' => $data['due_at'] ?? null,
            'late_policy' => $data['late_policy'] ?? 'accept',
            'late_penalty_percent' => $data['late_penalty_percent'] ?? null,
            'resubmission_policy' => $data['resubmission_policy'] ?? 'until_graded',
            'status' => $status,
            'published_at' => $status === AssignmentStatus::Published->value ? now() : null,
            'assessment_id' => $data['assessment_id'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        if ($status === AssignmentStatus::Published->value) {
            $this->notifyPublished($assignment);
        }

        return (new LmsAssignmentResource($assignment->loadCount('submissions')))
            ->additional(['message' => 'Assignment created.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Tell every targeted student (with a login) about newly-published work —
     * in-app only, queued when the audience is large. First publish only:
     * draft→publish→draft→publish must not double-announce.
     */
    private function notifyPublished(Assignment $assignment): void
    {
        $anchor = $assignment->subjectAssignment()->with(['subject:id,name', 'section:id,name'])->first();

        if ($anchor === null) {
            return;
        }

        $targets = $assignment->target_student_ids;

        $users = User::query()
            ->whereHas('studentProfile.enrollments', fn ($q) => $q
                ->where('section_id', $anchor->section_id)
                ->where('status', EnrollmentStatus::Active->value)
                ->when($targets !== null, fn ($qq) => $qq->whereIn('student_id', $targets)))
            ->get();

        app(Notifier::class)->toUsers($users, 'lms.assignment_published', [
            'title' => $assignment->title,
            'subject' => $anchor->subject?->name ?? '',
            'due' => $assignment->due_at?->toDateString() ?? '—',
        ], [
            'link' => '/me/learn',
            'schoolId' => $anchor->school_id,
            'branchId' => $anchor->branch_id,
        ]);
    }

    public function show(Assignment $assignment): JsonResponse
    {
        $this->authorize('view', $assignment);

        $assignment->load([
            'subjectAssignment.section:id,name,grade_level_id', 'subjectAssignment.section.gradeLevel:id,name',
            'subjectAssignment.subject:id,name', 'assessment:id,name,max_score',
        ])->loadCount('submissions');

        // Quiz-kind work keeps no assignment_submissions of its own — the
        // linked quiz's attempts ARE the turn-ins. Load its completion stats
        // so the teacher screen can bridge to the exam grading lane instead of
        // rendering an empty submissions queue.
        if ($assignment->kind === 'quiz' && $assignment->quiz_id !== null) {
            $assignment->load(['quiz' => fn ($q) => $q->withTakerStats()]);
        }

        return (new LmsAssignmentResource($assignment))->response();
    }

    public function update(Request $request, Assignment $assignment): JsonResponse
    {
        $this->authorize('update', $assignment);
        TermGate::assertWritable($assignment->subjectAssignment->term);

        $data = $this->validatePayload($request, $assignment);
        $this->assertAssessmentBelongs($data['assessment_id'] ?? null, $assignment->subjectAssignment);
        $this->assertQuizBelongs($data, $assignment->subjectAssignment);
        $this->assertTargetsEnrolled($data['target_student_ids'] ?? null, $assignment->subjectAssignment);

        // Attachments: append new uploads, drop removed paths (R2 cleanup).
        $attachments = collect($assignment->attachments ?? []);
        foreach ((array) $request->input('removed_paths', []) as $path) {
            $attachments = $attachments->reject(fn (array $f): bool => ($f['path'] ?? null) === $path);
            Storage::disk(config('filesystems.default'))->delete($path);
        }
        $attachments = $attachments->concat($this->storeAttachments($request))->values()->all();

        $updates = collect($data)->only([
            'kind', 'quiz_id', 'title', 'instructions', 'submission_types', 'rubric',
            'target_student_ids', 'max_score', 'available_from', 'due_at',
            'late_policy', 'late_penalty_percent', 'resubmission_policy', 'assessment_id', 'status',
        ])->all();

        if (array_key_exists('instructions', $updates)) {
            $updates['instructions'] = $this->cleanInstructions($updates['instructions']);
        }

        if (array_key_exists('target_student_ids', $updates)) {
            $updates['target_student_ids'] = $this->intTargets($updates);
        }

        if (array_key_exists('rubric', $updates) && $updates['rubric'] !== null) {
            $updates['max_score'] = $this->rubricTotal($updates['rubric']);
        }

        if (($updates['kind'] ?? $assignment->kind) !== 'quiz') {
            $updates['quiz_id'] = null;
        }

        $justPublished = ($updates['status'] ?? null) === AssignmentStatus::Published->value
            && $assignment->published_at === null;

        if ($justPublished) {
            $updates['published_at'] = now();
        }

        $assignment->update([...$updates, 'attachments' => $attachments]);

        if ($justPublished) {
            $this->notifyPublished($assignment);
        }

        return (new LmsAssignmentResource($assignment->refresh()->loadCount('submissions')))->response();
    }

    public function publish(Assignment $assignment): JsonResponse
    {
        $this->authorize('update', $assignment);
        TermGate::assertWritable($assignment->subjectAssignment->term);

        $firstPublish = $assignment->published_at === null;

        $assignment->forceFill([
            'status' => AssignmentStatus::Published->value,
            'published_at' => $assignment->published_at ?? now(),
        ])->save();

        if ($firstPublish) {
            $this->notifyPublished($assignment);
        }

        return (new LmsAssignmentResource($assignment->loadCount('submissions')))
            ->additional(['message' => 'Assignment published.'])
            ->response();
    }

    /** Stop accepting new turn-ins. */
    public function close(Assignment $assignment): JsonResponse
    {
        $this->authorize('update', $assignment);

        $assignment->forceFill(['status' => AssignmentStatus::Closed->value])->save();

        return (new LmsAssignmentResource($assignment->loadCount('submissions')))
            ->additional(['message' => 'Assignment closed.'])
            ->response();
    }

    public function destroy(Assignment $assignment): JsonResponse
    {
        $this->authorize('delete', $assignment);

        if ($assignment->submissions()->exists()) {
            throw ValidationException::withMessages([
                'assignment' => ['Students have already submitted — close the assignment instead of deleting it.'],
            ]);
        }

        foreach ($assignment->attachments ?? [] as $file) {
            if (isset($file['path'])) {
                Storage::disk(config('filesystems.default'))->delete($file['path']);
            }
        }

        $assignment->delete();

        return response()->json(['message' => 'Assignment deleted.']);
    }

    /** The grading queue: every submission with student identity. */
    public function submissions(Request $request, Assignment $assignment): JsonResponse
    {
        $this->authorize('view', $assignment);

        $submissions = $assignment->submissions()
            ->with(['student:id,first_name,father_name,grandfather_name,public_id,photo_path', 'grader:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('submitted_at')
            ->paginate(min($request->integer('per_page', 50), 100));

        return AssignmentSubmissionResource::collection($submissions)->response();
    }

    /** Grade one turn-in; the gradebook slot updates in the same stroke. */
    public function gradeSubmission(Request $request, Assignment $assignment, AssignmentSubmission $submission, GradebookSync $gradebook): JsonResponse
    {
        $this->authorize('update', $assignment);
        abort_unless($submission->assignment_id === $assignment->id, 404);
        TermGate::assertWritable($assignment->subjectAssignment->term);

        $max = $assignment->max_score !== null ? (float) $assignment->max_score : null;
        $rubric = $assignment->rubric;

        $data = $request->validate([
            'score' => [$rubric === null ? 'required' : 'sometimes', 'nullable', 'numeric', 'min:0', ...($max !== null ? ["max:{$max}"] : [])],
            'rubric_scores' => [$rubric !== null ? 'sometimes' : 'prohibited', 'array'],
            'rubric_scores.*' => ['numeric', 'min:0'],
            'feedback' => ['nullable', 'string', 'max:10000'],
            'return' => ['sometimes', 'boolean'],
        ]);

        // A rubric grades criterion by criterion; the total is server-summed
        // with each line clamped to its own maximum.
        $rubricScores = null;
        $score = $data['score'] ?? null;

        if ($rubric !== null && isset($data['rubric_scores'])) {
            $rubricScores = [];
            $score = 0.0;
            foreach (array_values($rubric) as $index => $criterion) {
                $lineMax = (float) ($criterion['max_points'] ?? 0);
                $line = min((float) ($data['rubric_scores'][$index] ?? 0), $lineMax);
                $rubricScores[] = $line;
                $score += $line;
            }
            $score = round($score, 2);
        }

        abort_if($score === null, 422, 'Provide a score or rubric scores.');

        // The documented late policy: penalty percent docked from late scores.
        if ($submission->is_late && $assignment->late_penalty_percent !== null) {
            $score = max(0, round($score * (1 - (float) $assignment->late_penalty_percent / 100), 2));
        }

        $submission->forceFill([
            'score' => $score,
            'rubric_scores' => $rubricScores,
            // Feedback is WYSIWYG — same sanitizer as instructions.
            'feedback' => $this->cleanInstructions($data['feedback'] ?? null),
            'status' => $request->boolean('return', true) ? SubmissionStatus::Returned->value : SubmissionStatus::Graded->value,
            'graded_by' => $request->user()->id,
            'graded_at' => now(),
        ])->save();

        $gradebook->syncSubmission($submission);

        app(Notifier::class)->toUser($submission->student?->user, 'lms.assignment_graded', [
            'title' => $assignment->title,
        ], [
            'link' => '/me/learn',
            'schoolId' => $assignment->school_id,
            'branchId' => $assignment->branch_id,
        ]);

        return (new AssignmentSubmissionResource($submission->load('student:id,first_name,father_name,grandfather_name,public_id')))
            ->additional(['message' => 'Submission graded.'])
            ->response();
    }

    /**
     * Every student thread on this assignment — the teacher's inbox. A
     * student may write BEFORE submitting, so this is the only reliable
     * place staff discover new messages (the grading sheet only opens on
     * submitted work). Threads are CONTEXT conversations of the chat engine
     * (ADR-019): one per assignment × student.
     */
    public function threads(Assignment $assignment): JsonResponse
    {
        $this->authorize('view', $assignment);

        $conversations = Conversation::query()
            ->where('context_type', 'assignment')
            ->where('context_id', $assignment->id)
            ->whereNotNull('student_id')
            ->whereNotNull('last_message_at')
            ->with('student:id,user_id,first_name,father_name,grandfather_name,public_id,photo_path')
            ->orderByDesc('last_message_at')
            ->get();

        $stats = ChatMessage::query()
            ->whereIn('conversation_id', $conversations->pluck('id'))
            ->where('status', ChatMessage::STATUS_SENT)
            ->whereNull('deleted_at')
            ->selectRaw('conversation_id, count(*) as messages_count, max(id) as last_id')
            ->groupBy('conversation_id')
            ->get()
            ->keyBy('conversation_id');

        $lastMessages = ChatMessage::query()
            ->whereIn('id', $stats->pluck('last_id'))
            ->get()
            ->keyBy('conversation_id');

        $threads = $conversations->map(function (Conversation $c) use ($stats, $lastMessages): ?array {
            $last = $lastMessages->get($c->id);
            if ($c->student === null || $last === null) {
                return null;
            }

            $studentSpokeLast = $c->student->user_id !== null
                && (int) $last->user_id === (int) $c->student->user_id;

            return [
                'conversation_id' => $c->id,
                'student_id' => (int) $c->student_id,
                'student_name' => $c->student->full_name,
                'student_public_id' => $c->student->public_id,
                'student_photo_url' => $c->student->photo_url,
                'messages_count' => (int) ($stats->get($c->id)?->messages_count ?? 0),
                'last_body' => $last->body,
                'last_is_staff' => ! $studentSpokeLast,
                'last_at' => $last->created_at,
                // A student spoke last → the teacher hasn't replied yet.
                'awaiting_reply' => $studentSpokeLast,
            ];
        })->filter()->values();

        return response()->json(['data' => $threads]);
    }

    /**
     * Resolve (creating on first open) the context conversation with one
     * student — the chat UI takes over from here (/chat/conversations/{id}).
     * The caller joins as a participant only with UPDATE authority on the
     * assignment (owning teacher / supervisor); view-only staff read via
     * their audit lane.
     */
    public function thread(Request $request, Assignment $assignment, ChatService $chat): JsonResponse
    {
        $this->authorize('view', $assignment);

        $data = $request->validate(['student_id' => ['required', 'integer', 'exists:students,id']]);

        $student = Student::query()->findOrFail((int) $data['student_id']);
        abort_unless($assignment->reachesStudent($student->id), 422, 'This assignment was not posted to that student.');

        $participants = [$assignment->subjectAssignment?->employee?->user_id, $student->user_id];
        if ($request->user()->can('update', $assignment)) {
            $participants[] = $request->user()->id;
        }

        $conversation = $chat->forContext(
            'assignment',
            $assignment->id,
            (int) $assignment->school_id,
            $assignment->branch_id,
            array_values(array_filter($participants)),
            $student->id,
        );

        return response()->json(['data' => ['conversation_id' => $conversation->id]]);
    }

    /** Re-push every graded submission (after a marklist reopen). */
    public function sync(Assignment $assignment, GradebookSync $gradebook): JsonResponse
    {
        $this->authorize('update', $assignment);

        $count = $gradebook->resyncAssignment($assignment);

        return response()->json(['message' => "Gradebook updated for {$count} students.", 'meta' => ['count' => $count]]);
    }

    /**
     * The class list for LMS pickers (per-student targeting, thread lookup):
     * active enrollments of the anchor's section, names + ids only.
     */
    public function classStudents(Request $request, SubjectAssignment $subjectAssignment): JsonResponse
    {
        abort_unless($this->mayView($request, $subjectAssignment), 403);

        $students = StudentEnrollment::query()
            ->where('section_id', $subjectAssignment->section_id)
            ->where('academic_year_id', $subjectAssignment->academic_year_id)
            ->where('status', 'active')
            ->with('student:id,first_name,father_name,grandfather_name,public_id')
            ->get()
            ->map(fn (StudentEnrollment $e): ?array => $e->student !== null ? [
                'id' => $e->student->id,
                'full_name' => $e->student->full_name,
                'public_id' => $e->student->public_id,
            ] : null)
            ->filter()
            ->sortBy('full_name')
            ->values();

        return response()->json(['data' => $students]);
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function validatePayload(Request $request, ?Assignment $assignment = null): array
    {
        $creating = $assignment === null;
        $kind = $request->input('kind', $creating ? 'standard' : null);

        // The editor always posts the field; an empty string means "whole
        // class" — without it, clearing a targeted list could never persist.
        if ($request->input('target_student_ids') === '') {
            $request->merge(['target_student_ids' => null]);
        }

        // The start the deadline is judged against: the one being posted, or —
        // when an edit leaves the field alone — the one already saved. An edit
        // that explicitly clears the start leaves the deadline unconstrained.
        $startsAt = match (true) {
            $request->filled('available_from') => 'available_from',
            $request->has('available_from') => null,
            default => $assignment?->available_from?->toDateTimeString(),
        };

        return $request->validate([
            'subject_assignment_id' => [$creating ? 'required' : 'sometimes', 'integer', 'exists:subject_assignments,id'],
            'kind' => [$creating ? 'sometimes' : 'sometimes', Rule::in(Assignment::KINDS)],
            'quiz_id' => [Rule::requiredIf($kind === 'quiz'), 'nullable', 'integer', 'exists:quizzes,id'],
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:20000'],
            // Standard work names its accepted modes; quiz/offline kinds don't.
            'submission_types' => [Rule::requiredIf($creating && ($kind ?? 'standard') === 'standard'), 'array', 'min:1'],
            'submission_types.*' => [Rule::in(Assignment::SUBMISSION_TYPES)],
            'rubric' => ['sometimes', 'nullable', 'array', 'max:20'],
            'rubric.*.criterion' => ['required', 'string', 'max:255'],
            'rubric.*.max_points' => ['required', 'numeric', 'min:0.5', 'max:100'],
            'target_student_ids' => ['sometimes', 'nullable', 'array', 'max:200'],
            'target_student_ids.*' => ['integer'],
            'max_score' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            // Scheduling can't point at a day already gone — but an edit that
            // keeps an existing past date untouched still validates.
            'available_from' => ['nullable', 'date', 'after:2000-01-01', 'before:2100-01-01', new NotPastDay($assignment?->available_from)],
            // Work that closes the moment it opens is never what the teacher
            // meant: the deadline has to land strictly after the start.
            'due_at' => [
                'nullable', 'date', 'after:2000-01-01', 'before:2100-01-01',
                ...($startsAt === null ? [] : ['after:'.$startsAt]),
                new NotPastDay($assignment?->due_at),
            ],
            'late_policy' => ['sometimes', Rule::in(['accept', 'reject'])],
            'late_penalty_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'resubmission_policy' => ['sometimes', Rule::in(Assignment::RESUBMISSION_POLICIES)],
            'status' => ['sometimes', Rule::in([AssignmentStatus::Draft->value, AssignmentStatus::Published->value, AssignmentStatus::Closed->value])],
            'assessment_id' => ['nullable', 'integer', 'exists:assessments,id'],
            'attachments' => ['sometimes', 'array', 'max:10'],
            'attachments.*' => CourseworkFiles::rules(),
            'removed_paths' => ['sometimes', 'array'],
            'removed_paths.*' => ['string'],
        ]);
    }

    /** @return list<string> */
    private function submissionTypesFor(string $kind, array $data): array
    {
        return $kind === 'quiz' ? [] : array_values($data['submission_types'] ?? []);
    }

    /**
     * Targets as REAL integers (FormData posts strings, and JSONB keeps the
     * type it was given — string ids break both `whereJsonContains` matching
     * and the editor's checkbox state). Empty list = whole class = null.
     *
     * @return list<int>|null
     */
    private function intTargets(array $data): ?array
    {
        $ids = $data['target_student_ids'] ?? null;

        return $ids === null || $ids === []
            ? null
            : array_values(array_map('intval', $ids));
    }

    /** WYSIWYG instructions: sanitized, uploaded images stored as data-path. */
    private function cleanInstructions(?string $html): ?string
    {
        if ($html === null || (trim(strip_tags($html)) === '' && ! str_contains($html, '<img'))) {
            return null;
        }

        return QuestionRules::normalizeStemMedia(QuestionRules::sanitizeStem($html));
    }

    private function rubricTotal(array $rubric): float
    {
        return round(collect($rubric)->sum(fn (array $c): float => (float) ($c['max_points'] ?? 0)), 2);
    }

    /** A quiz-kind assignment must wrap a quiz of the SAME class. */
    private function assertQuizBelongs(array $data, SubjectAssignment $anchor): void
    {
        $quizId = $data['quiz_id'] ?? null;

        if ($quizId === null || ($data['kind'] ?? null) !== 'quiz') {
            return;
        }

        $ok = Quiz::query()
            ->whereKey($quizId)
            ->where('subject_assignment_id', $anchor->id)
            ->exists();

        if (! $ok) {
            throw ValidationException::withMessages([
                'quiz_id' => ['The quiz must belong to this class.'],
            ]);
        }
    }

    /** Targeted students must actually sit in the class's section this year. */
    private function assertTargetsEnrolled(?array $studentIds, SubjectAssignment $anchor): void
    {
        if ($studentIds === null || $studentIds === []) {
            return;
        }

        $enrolled = StudentEnrollment::query()
            ->where('section_id', $anchor->section_id)
            ->where('academic_year_id', $anchor->academic_year_id)
            ->where('status', 'active')
            ->whereIn('student_id', $studentIds)
            ->pluck('student_id')
            ->map(fn ($id): int => (int) $id);

        $missing = collect($studentIds)->map(fn ($id): int => (int) $id)->diff($enrolled);

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'target_student_ids' => ['Some selected students are not enrolled in this class.'],
            ]);
        }
    }

    /** @return list<array{name: string, path: string, size: int|null, mime_type: string|null}> */
    private function storeAttachments(Request $request): array
    {
        $stored = [];

        foreach ($request->file('attachments', []) as $file) {
            $path = $file->store('lms/assignment-attachments', ['disk' => config('filesystems.default')]);

            $stored[] = [
                'name' => $file->getClientOriginalName(),
                'path' => $path,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
            ];
        }

        return $stored;
    }

    private function assertAssessmentBelongs(?int $assessmentId, SubjectAssignment $anchor): void
    {
        if ($assessmentId === null) {
            return;
        }

        $ok = Assessment::query()
            ->whereKey($assessmentId)
            ->where('subject_assignment_id', $anchor->id)
            ->exists();

        if (! $ok) {
            throw ValidationException::withMessages([
                'assessment_id' => ['The gradebook slot must belong to this class.'],
            ]);
        }
    }

    private function mayView(Request $request, SubjectAssignment $anchor): bool
    {
        $user = $request->user();

        return $user->hasPermissionForScope('lms.view', $anchor->school_id, $anchor->branch_id)
            || ($user->hasPermissionForScope('lms.manage_own', $anchor->school_id, $anchor->branch_id)
                && $anchor->isOwnedBy($user));
    }

    private function mayManage(Request $request, SubjectAssignment $anchor): bool
    {
        $user = $request->user();

        return $user->hasPermissionForScope('lms.manage', $anchor->school_id, $anchor->branch_id)
            || ($user->hasPermissionForScope('lms.manage_own', $anchor->school_id, $anchor->branch_id)
                && $anchor->isOwnedBy($user));
    }
}
