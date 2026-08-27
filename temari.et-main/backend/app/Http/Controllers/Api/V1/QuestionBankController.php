<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TermStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\QuestionBankResource;
use App\Models\QuestionBank;
use App\Models\SubjectAssignment;
use App\Models\User;
use App\Support\SearchTerm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Question banks (ADR-016). Three scopes through one controller:
 * `?platform=1` = the national/exam-prep banks (Temari.et staff,
 * exam_prep.manage); otherwise the active school's banks — supervisory
 * roles see and manage all of them, teachers see them all (their quizzes
 * draw from shared banks) but manage only banks they created.
 */
class QuestionBankController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = QuestionBank::query()
            ->with(['subject:id,name', 'gradeLevel:id,name,sort_order', 'branch:id,name', 'creator:id,name'])
            ->withCount('questions')
            ->orderByDesc('id');

        if ($request->boolean('platform')) {
            abort_unless($user->hasPlatformPermission('exam_prep.manage'), 403);
            $query->whereNull('school_id');
        } elseif ($request->filled('school_id') && $user->hasPlatformPermission('exam_prep.manage')) {
            // Temari.et LMS staff may inspect any school's banks by naming it.
            $query->where('school_id', $request->integer('school_id'))
                ->with('school:id,name')
                ->when($request->filled('branch_id'), fn ($q) => $q->where(
                    fn ($w) => $w->whereNull('branch_id')->orWhere('branch_id', $request->integer('branch_id')),
                ));
        } else {
            $branch = $this->activeBranchOrNull($request);
            $schoolId = $branch?->school_id ?? $this->activeSchoolScopeId($request);
            abort_if($schoolId === null, 422, 'Select a school context to view question banks.');

            abort_unless(
                $user->hasPermissionForScope('lms.view', $schoolId, $branch?->id)
                || $user->hasPermissionForScope('lms.manage_own', $schoolId, $branch?->id),
                403,
            );

            $query->where('school_id', $schoolId)
                ->when($branch !== null, fn ($q) => $q->where(
                    fn ($w) => $w->whereNull('branch_id')->orWhere('branch_id', $branch->id),
                ))
                ->when($this->branchFilterId($request, $branch), fn ($q, $id) => $q->where(
                    fn ($w) => $w->whereNull('branch_id')->orWhere('branch_id', $id),
                ));
        }

        $banks = $query
            ->when($request->filled('subject_id'), fn ($q) => $q->where('subject_id', $request->integer('subject_id')))
            ->when($request->filled('grade_level_id'), fn ($q) => $q->where('grade_level_id', $request->integer('grade_level_id')))
            ->when(! $request->boolean('all'), fn ($q) => $q->where('is_active', true))
            ->tap(fn ($query) => SearchTerm::apply($query, $request->string('q')->trim()->value(), fn ($w, string $n) => $w
                ->where('name', 'ilike', SearchTerm::contains($n))))
            ->paginate(min($request->integer('per_page', 25), 100));

        return QuestionBankResource::collection($banks)->response();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'grade_level_id' => ['required', 'integer', 'exists:grade_levels,id'],
            'topics' => ['nullable', 'array', 'max:50'],
            'topics.*' => ['string', 'max:120'],
            'platform' => ['sometimes', 'boolean'],
            'school_wide' => ['sometimes', 'boolean'],
            'branch_id' => ['sometimes', 'nullable', 'integer'],
        ]);

        $user = $request->user();

        if ($request->boolean('platform')) {
            abort_unless($user->hasPlatformPermission('exam_prep.manage'), 403);
            $schoolId = null;
            $branchId = null;
        } elseif ($request->boolean('school_wide')) {
            // School-wide banks (every branch draws from them) — managers only.
            $branch = $this->activeBranchOrNull($request);
            $schoolId = $branch?->school_id ?? $this->activeSchoolScopeId($request);
            abort_if($schoolId === null, 422, 'Select a school context to add a question bank.');
            abort_unless($user->hasPermissionForScope('lms.manage', $schoolId, null), 403);
            $branchId = null;
        } else {
            $branch = $this->targetBranch($request);
            $schoolId = $branch->school_id;
            $branchId = $branch->id;
            $supervisor = $user->hasPermissionForScope('lms.manage', $schoolId, $branchId);
            abort_unless(
                $supervisor || $user->hasPermissionForScope('lms.manage_own', $schoolId, $branchId),
                403,
            );

            if (! $supervisor) {
                $this->assertTeachesSubject($user, $branchId, $data['subject_id'] ?? null);
            }
        }

        $bank = QuestionBank::create([
            'school_id' => $schoolId,
            'branch_id' => $branchId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'subject_id' => $data['subject_id'],
            'grade_level_id' => $data['grade_level_id'],
            'topics' => $this->cleanTopics($data['topics'] ?? null),
            'created_by' => $user->id,
        ]);

        return (new QuestionBankResource($bank->loadCount('questions')->load(['subject:id,name', 'gradeLevel:id,name,sort_order'])))
            ->additional(['message' => 'Question bank created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(QuestionBank $questionBank): QuestionBankResource
    {
        $this->authorize('view', $questionBank);

        return new QuestionBankResource(
            $questionBank->loadCount('questions')
                ->load(['subject:id,name', 'gradeLevel:id,name,sort_order', 'branch:id,name', 'creator:id,name']),
        );
    }

    public function update(Request $request, QuestionBank $questionBank): QuestionBankResource
    {
        $this->authorize('update', $questionBank);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'subject_id' => ['sometimes', 'required', 'integer', 'exists:subjects,id'],
            'grade_level_id' => ['sometimes', 'required', 'integer', 'exists:grade_levels,id'],
            'topics' => ['nullable', 'array', 'max:50'],
            'topics.*' => ['string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();

        if (array_key_exists('subject_id', $data)
            && ! $questionBank->isPlatform()
            && ! $user->hasPermissionForScope('lms.manage', $questionBank->school_id, $questionBank->branch_id)) {
            $this->assertTeachesSubject($user, (int) $questionBank->branch_id, $data['subject_id']);
        }

        if (array_key_exists('topics', $data)) {
            $data['topics'] = $this->cleanTopics($data['topics']);
        }

        $questionBank->update($data);

        return new QuestionBankResource($questionBank->loadCount('questions')->load(['subject:id,name', 'gradeLevel:id,name,sort_order', 'branch:id,name']));
    }

    /** Trimmed, de-duplicated (case-insensitive), order-preserving topics. */
    private function cleanTopics(?array $topics): ?array
    {
        if ($topics === null) {
            return null;
        }

        $clean = [];
        foreach ($topics as $topic) {
            $topic = trim((string) $topic);
            if ($topic === '' || in_array(mb_strtolower($topic), array_map('mb_strtolower', $clean), true)) {
                continue;
            }
            $clean[] = $topic;
        }

        return $clean === [] ? null : $clean;
    }

    /**
     * Teachers may only shape banks for subjects they currently teach (their
     * live subject assignments at the bank's branch, closed terms excluded) —
     * never an arbitrary subject, and never no subject at all.
     */
    private function assertTeachesSubject(User $user, int $branchId, ?int $subjectId): void
    {
        $taught = SubjectAssignment::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->whereHas('term', fn ($q) => $q->where('status', '!=', TermStatus::Closed->value))
            ->whereHas('employee', fn ($q) => $q->where('user_id', $user->id))
            ->pluck('subject_id')
            ->unique();

        if ($subjectId === null || ! $taught->contains($subjectId)) {
            throw ValidationException::withMessages([
                'subject_id' => ['Pick one of the subjects you are teaching this semester.'],
            ]);
        }
    }

    public function destroy(Request $request, QuestionBank $questionBank): JsonResponse
    {
        $this->authorize('delete', $questionBank);

        // Banks with quiz-referenced questions deactivate instead of deleting —
        // attempts keep pointing at real questions forever.
        $questionIds = $questionBank->questions()->select('id');
        $referenced = DB::table('quiz_questions')->whereIn('question_id', $questionIds)->exists()
            || DB::table('quiz_attempt_answers')->whereIn('question_id', $questionIds)->exists();

        if ($referenced) {
            $questionBank->update(['is_active' => false]);

            return response()->json(['message' => 'This bank has questions used in exams — it was deactivated instead of deleted.']);
        }

        $questionBank->questions()->delete();
        $questionBank->delete();

        return response()->json(['message' => 'Question bank deleted.']);
    }
}
